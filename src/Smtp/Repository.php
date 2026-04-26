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
	 * exhausted, ordered by priority.
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
		$accounts = $wpdb->get_results( "SELECT * FROM `$smtp_table` WHERE `enabled` = 1 AND (`daily_limit` = 0 OR `daily_sent` < `daily_limit`) AND (`monthly_limit` = 0 OR `monthly_sent` < `monthly_limit`) AND (`send_bulk` = 0 OR `cycle_sent` < `send_bulk`) ORDER BY `priority` ASC", ARRAY_A );
		return $accounts ? $accounts : array();
	}

	/**
	 * Pick the first account in $accounts whose `cycle_sent` is below
	 * `send_bulk` (or whose `send_bulk` is 0, meaning unlimited).
	 *
	 * Pure / database-free — operates on the in-memory list returned by
	 * {@see Repository::available()} so a single batch can iterate without
	 * re-querying.
	 *
	 * @param list<array<string,mixed>> $accounts Candidates in priority order.
	 * @return array<string,mixed>|null Selected row, or null when all are exhausted.
	 */
	public static function pickAvailable( array $accounts ): ?array {
		foreach ( $accounts as $acct ) {
			$bulk = intval( $acct['send_bulk'] );
			if ( 0 === $bulk || intval( $acct['cycle_sent'] ) < $bulk ) {
				return $acct;
			}
		}
		return null;
	}

	/**
	 * Increment `cycle_sent` for the account matching $smtp_id in the
	 * in-memory $accounts list. No DB hit.
	 *
	 * @param list<array<string,mixed>> $accounts In-memory account list, modified by reference.
	 * @param int|string                $smtp_id  ID of the account whose counter should bump.
	 */
	public static function bumpMemoryCounter( array &$accounts, $smtp_id ): void {
		$target_id = intval( $smtp_id );
		foreach ( $accounts as $key => $acct ) {
			if ( intval( $acct['id'] ) === $target_id ) {
				$accounts[ $key ]['cycle_sent'] = intval( $acct['cycle_sent'] ) + 1;
				break;
			}
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
