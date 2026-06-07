<?php
/**
 * Repository for SMTP accounts and their per-account counters.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Smtp;

use TotalMailQueue\Database\Schema;

/**
 * Read/write access to the `{$prefix}total_mail_queue_smtp` table.
 *
 * Encapsulates the queries that the procedural code used to scatter across
 * the plugin: counter resets at the top of every cron run, the "available
 * accounts" view, the per-send DB increment, and the in-memory counter
 * helpers used during a single batch.
 */
final class Repository {

	/**
	 * Reset counters that are due for rollover.
	 *
	 * - `cycle_sent` is reset every cron run for accounts with
	 *   `send_interval = 0` (global cycle), and only after the configured
	 *   interval has elapsed for accounts with `send_interval > 0`.
	 * - `daily_sent` rolls over when the calendar day changes.
	 * - `monthly_sent` rolls over when the calendar month changes.
	 */
	public static function resetCounters(): void {
		global $wpdb;
		$smtp_table = Schema::smtpTable();
		$today      = current_time( 'Y-m-d' );
		$this_month = current_time( 'Y-m' );
		$now        = current_time( 'mysql', false );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			"UPDATE `$smtp_table` SET `cycle_sent` = 0 WHERE `send_interval` = 0" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE `$smtp_table` SET `cycle_sent` = 0 WHERE `send_interval` > 0 AND DATE_ADD(`last_sent_at`, INTERVAL `send_interval` MINUTE) <= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$now
			)
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE `$smtp_table` SET `daily_sent` = 0, `last_daily_reset` = %s WHERE `last_daily_reset` < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$today,
				$today
			)
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE `$smtp_table` SET `monthly_sent` = 0, `last_monthly_reset` = %s WHERE DATE_FORMAT(`last_monthly_reset`, '%%Y-%%m') < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$today,
				$this_month
			)
		);
	}

	/**
	 * Return enabled SMTP accounts whose daily/monthly/cycle limits aren't
	 * exhausted, **least-recently-used first**.
	 *
	 * The ordering is what spreads load across accounts over time: the
	 * account idle the longest sorts to the front, so a batch rotates through
	 * every enabled account instead of draining the highest-priority one
	 * first. `priority` is only a tie-breaker (e.g. when two accounts have
	 * never sent, or share the same `last_sent_at`).
	 *
	 * Note: `send_interval` is intentionally NOT checked here — it only
	 * controls when {@see Repository::resetCounters()} clears `cycle_sent`,
	 * not whether mid-cycle sends can proceed.
	 *
	 * @return list<array<string,mixed>>
	 */
	public static function available(): array {
		global $wpdb;
		$smtp_table = Schema::smtpTable();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$accounts = $wpdb->get_results( "SELECT * FROM `$smtp_table` WHERE `enabled` = 1 AND (`daily_limit` = 0 OR `daily_sent` < `daily_limit`) AND (`monthly_limit` = 0 OR `monthly_sent` < `monthly_limit`) AND (`send_bulk` = 0 OR `cycle_sent` < `send_bulk`) ORDER BY `last_sent_at` ASC, `priority` ASC, `id` ASC", ARRAY_A );
		return $accounts ? $accounts : array();
	}

	/**
	 * Pick the next account to send through: the first one in $accounts (the
	 * list is kept in least-recently-used order, front to back) that still
	 * has capacity on every limit — per-cycle bulk, daily, and monthly.
	 *
	 * Pure / database-free — operates on the in-memory list returned by
	 * {@see Repository::available()}. After a send the caller bumps the
	 * counters with {@see Repository::bumpMemoryCounter()} and rotates the
	 * account to the back with {@see Repository::markUsed()}, so repeated
	 * calls within one batch hand out different accounts in rotation.
	 *
	 * @param list<array<string,mixed>> $accounts Candidates, least-recently-used first.
	 * @return array<string,mixed>|null Selected row, or null when all are exhausted.
	 */
	public static function pickAvailable( array $accounts ): ?array {
		foreach ( $accounts as $acct ) {
			if ( self::hasCapacity( $acct ) ) {
				return $acct;
			}
		}
		return null;
	}

	/**
	 * Whether an in-memory account row still has room on every configured
	 * limit. A limit of 0 means "unlimited" and never blocks.
	 *
	 * @param array<string,mixed> $acct Account row (possibly partial in tests).
	 */
	private static function hasCapacity( array $acct ): bool {
		$daily_limit = intval( $acct['daily_limit'] ?? 0 );
		if ( $daily_limit > 0 && intval( $acct['daily_sent'] ?? 0 ) >= $daily_limit ) {
			return false;
		}
		$monthly_limit = intval( $acct['monthly_limit'] ?? 0 );
		if ( $monthly_limit > 0 && intval( $acct['monthly_sent'] ?? 0 ) >= $monthly_limit ) {
			return false;
		}
		$bulk = intval( $acct['send_bulk'] ?? 0 );
		if ( $bulk > 0 && intval( $acct['cycle_sent'] ?? 0 ) >= $bulk ) {
			return false;
		}
		return true;
	}

	/**
	 * Increment the in-memory usage counters (`cycle_sent`, `daily_sent`,
	 * `monthly_sent`) for the account matching $smtp_id. No DB hit — mirrors
	 * the persisted bump in {@see Repository::incrementCounter()} so the
	 * within-batch capacity checks in {@see Repository::hasCapacity()} stay
	 * accurate and a single account can't blow past its daily / monthly cap
	 * mid-batch.
	 *
	 * @param list<array<string,mixed>> $accounts In-memory account list, modified by reference.
	 * @param int|string                $smtp_id  ID of the account whose counters should bump.
	 */
	public static function bumpMemoryCounter( array &$accounts, $smtp_id ): void {
		$target_id = intval( $smtp_id );
		foreach ( $accounts as $key => $acct ) {
			if ( intval( $acct['id'] ) === $target_id ) {
				$accounts[ $key ]['cycle_sent']   = intval( $acct['cycle_sent'] ?? 0 ) + 1;
				$accounts[ $key ]['daily_sent']   = intval( $acct['daily_sent'] ?? 0 ) + 1;
				$accounts[ $key ]['monthly_sent'] = intval( $acct['monthly_sent'] ?? 0 ) + 1;
				break;
			}
		}
	}

	/**
	 * Move the account matching $smtp_id to the back of the in-memory list so
	 * the next {@see Repository::pickAvailable()} hands out a different
	 * account. This is the rotation that turns the priority-ordered snapshot
	 * into round-robin delivery: callers invoke it after every send attempt
	 * (success or failure) so a flaky account doesn't get hammered for the
	 * whole batch either.
	 *
	 * @param list<array<string,mixed>> $accounts In-memory account list, modified by reference.
	 * @param int|string                $smtp_id  ID of the account that was just used.
	 */
	public static function markUsed( array &$accounts, $smtp_id ): void {
		$target_id = intval( $smtp_id );
		$moved     = null;
		$remaining = array();
		foreach ( $accounts as $acct ) {
			if ( null === $moved && intval( $acct['id'] ) === $target_id ) {
				$moved = $acct;
				continue;
			}
			$remaining[] = $acct;
		}
		if ( null !== $moved ) {
			$remaining[] = $moved;
			$accounts    = $remaining;
		}
	}

	/**
	 * Persist a successful send for the given account: bump daily, monthly
	 * and cycle counters and record `last_sent_at`.
	 *
	 * @param int|string $smtp_id Account id.
	 */
	public static function incrementCounter( $smtp_id ): void {
		global $wpdb;
		$smtp_table = Schema::smtpTable();
		$now        = current_time( 'mysql', false );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE `$smtp_table` SET `daily_sent` = `daily_sent` + 1, `monthly_sent` = `monthly_sent` + 1, `cycle_sent` = `cycle_sent` + 1, `last_sent_at` = %s WHERE `id` = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$now,
				intval( $smtp_id )
			)
		);
	}

	/**
	 * Look up the encrypted password column for the given account id.
	 *
	 * Used by the connection-tester admin endpoint when the form has an
	 * empty password field on edit (meaning "use the stored value").
	 *
	 * @param int $smtp_id Account id.
	 * @return string Encrypted payload, or '' when the account isn't found.
	 */
	public static function findPasswordById( int $smtp_id ): string {
		global $wpdb;
		$smtp_table = Schema::smtpTable();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$stored = $wpdb->get_var( $wpdb->prepare( "SELECT `password` FROM `$smtp_table` WHERE `id` = %d", $smtp_id ) );
		return is_string( $stored ) ? $stored : '';
	}
}
