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
	 *   interval has elapsed since the current cycle *began* for accounts with
	 *   `send_interval > 0`. The cycle start is tracked in `last_cycle_reset`,
	 *   stamped by {@see Repository::incrementCounter()} on the first send of a
	 *   cycle — NOT in `last_sent_at`, which advances on every send and would
	 *   slide the window forward indefinitely, so a steadily-trickling account
	 *   would never roll its cycle over and would eventually stick at the cap.
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
		// Interval accounts roll over once the window measured from the cycle's
		// start (`last_cycle_reset`) has elapsed. The next send re-anchors the
		// window via incrementCounter(), so the start is left untouched here.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE `$smtp_table` SET `cycle_sent` = 0 WHERE `send_interval` > 0 AND DATE_ADD(`last_cycle_reset`, INTERVAL `send_interval` MINUTE) <= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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
	 * On the first send of a cycle (i.e. while `cycle_sent` is still 0) the
	 * send also stamps `last_cycle_reset` to anchor the cycle window at the
	 * moment sending began. {@see Repository::resetCounters()} measures the
	 * `send_interval` rollover from that anchor, so the window is fixed to the
	 * cycle start rather than sliding forward with every subsequent send. The
	 * `last_cycle_reset` assignment is ordered before the `cycle_sent` bump so
	 * its `IF` still sees the pre-increment value.
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
				"UPDATE `$smtp_table` SET `last_cycle_reset` = IF(`cycle_sent` = 0, %s, `last_cycle_reset`), `daily_sent` = `daily_sent` + 1, `monthly_sent` = `monthly_sent` + 1, `cycle_sent` = `cycle_sent` + 1, `last_sent_at` = %s WHERE `id` = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$now,
				$now,
				intval( $smtp_id )
			)
		);
	}

	/**
	 * Earliest moment (UTC unix timestamp) at which some enabled account will
	 * regain capacity, given that none can send right now. Used by the
	 * scheduler to defer the queue worker to the next cycle/quota window
	 * instead of waking it every interval while every account is capped.
	 *
	 * Returns null when there are no enabled accounts at all — there is then
	 * no time-based recovery to wait for (the admin must enable one, which
	 * re-arms the worker through {@see \TotalMailQueue\Cron\Scheduler::reschedule()}).
	 *
	 * @param int $queue_interval The configured cron interval in seconds — used
	 *                            as the recovery horizon for global-cycle
	 *                            accounts, which reset at the next worker run.
	 * @return int|null Unix timestamp, or null when no enabled account exists.
	 */
	public static function nextAvailableAt( int $queue_interval ): ?int {
		global $wpdb;
		$smtp_table = Schema::smtpTable();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$accounts = $wpdb->get_results( "SELECT * FROM `$smtp_table` WHERE `enabled` = 1", ARRAY_A );
		if ( empty( $accounts ) ) {
			return null;
		}

		$now      = time();
		$earliest = null;
		foreach ( $accounts as $acct ) {
			$free = self::accountFreeAt( $acct, $now, $queue_interval );
			if ( null === $earliest || $free < $earliest ) {
				$earliest = $free;
			}
		}
		return $earliest;
	}

	/**
	 * Human-readable explanation of why no SMTP account can send right now,
	 * used to fill a waiting row's `info` so the operator can see exactly which
	 * limit is holding the queue (per-cycle / daily / monthly) and roughly when
	 * each account recovers — instead of a bare "no account available".
	 *
	 * Always contains the phrase "no SMTP account available" so any code (or
	 * habit) that scans for it keeps working.
	 *
	 * @param int $queue_interval Cron interval in seconds (recovery horizon for global-cycle accounts).
	 */
	public static function blockSummary( int $queue_interval ): string {
		global $wpdb;
		$smtp_table = Schema::smtpTable();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$accounts = $wpdb->get_results( "SELECT * FROM `$smtp_table` WHERE `enabled` = 1 ORDER BY `priority` ASC, `id` ASC", ARRAY_A );

		if ( empty( $accounts ) ) {
			return __( 'Waiting: no SMTP account available — no account is enabled. Add or enable one on the SMTP tab.', 'total-mail-queue' );
		}

		$now   = time();
		$parts = array();
		foreach ( $accounts as $acct ) {
			$reason = self::accountBlockReason( $acct, $now, $queue_interval );
			if ( null !== $reason ) {
				$parts[] = $reason;
			}
		}

		if ( empty( $parts ) ) {
			// Every account reports capacity yet none was usable (e.g. a
			// transient race against the snapshot) — fall back to the generic
			// hint rather than claiming a limit that isn't set.
			return __( 'Waiting: no SMTP account available (check that accounts are enabled and limits are not exceeded).', 'total-mail-queue' );
		}

		return __( 'Waiting: no SMTP account available — every account is at a limit.', 'total-mail-queue' ) . ' ' . implode( ' | ', $parts );
	}

	/**
	 * One-line reason an account can't send, plus roughly when it recovers — or
	 * null when the account actually has capacity. Mirrors the limit checks in
	 * {@see Repository::hasCapacity()} but renders them for humans.
	 *
	 * @param array<string,mixed> $acct           Account row.
	 * @param int                 $now            Current UTC unix timestamp.
	 * @param int                 $queue_interval Cron interval in seconds.
	 */
	private static function accountBlockReason( array $acct, int $now, int $queue_interval ): ?string {
		$reasons = array();

		$bulk = intval( $acct['send_bulk'] );
		if ( $bulk > 0 && intval( $acct['cycle_sent'] ) >= $bulk ) {
			/* translators: %1$d: emails sent this cycle, %2$d: per-cycle limit */
			$reasons[] = sprintf( __( 'per-cycle quota reached (%1$d/%2$d)', 'total-mail-queue' ), intval( $acct['cycle_sent'] ), $bulk );
		}
		$daily_limit = intval( $acct['daily_limit'] );
		if ( $daily_limit > 0 && intval( $acct['daily_sent'] ) >= $daily_limit ) {
			/* translators: %1$d: emails sent today, %2$d: daily limit */
			$reasons[] = sprintf( __( 'daily limit reached (%1$d/%2$d)', 'total-mail-queue' ), intval( $acct['daily_sent'] ), $daily_limit );
		}
		$monthly_limit = intval( $acct['monthly_limit'] );
		if ( $monthly_limit > 0 && intval( $acct['monthly_sent'] ) >= $monthly_limit ) {
			/* translators: %1$d: emails sent this month, %2$d: monthly limit */
			$reasons[] = sprintf( __( 'monthly limit reached (%1$d/%2$d)', 'total-mail-queue' ), intval( $acct['monthly_sent'] ), $monthly_limit );
		}

		if ( empty( $reasons ) ) {
			return null;
		}

		$when = wp_date( 'Y-m-d H:i', self::accountFreeAt( $acct, $now, $queue_interval ) );
		/* translators: %1$s: account name, %2$s: comma-separated limit reasons, %3$s: recovery date/time */
		return sprintf( __( '%1$s: %2$s, resumes ~%3$s', 'total-mail-queue' ), (string) ( $acct['name'] ?? '' ), implode( ', ', $reasons ), $when );
	}

	/**
	 * Compute when a single account regains capacity: the latest reset among
	 * the limits it is currently capped on (cycle / daily / monthly), or now
	 * if it isn't capped at all.
	 *
	 * @param array<string,mixed> $acct           Account row.
	 * @param int                 $now            Current UTC unix timestamp.
	 * @param int                 $queue_interval Cron interval in seconds.
	 * @return int Unix timestamp at which the account can send again.
	 */
	private static function accountFreeAt( array $acct, int $now, int $queue_interval ): int {
		$free = $now;

		$bulk = intval( $acct['send_bulk'] );
		if ( $bulk > 0 && intval( $acct['cycle_sent'] ) >= $bulk ) {
			$interval = intval( $acct['send_interval'] );
			if ( $interval > 0 ) {
				// Recovery is measured from the cycle's start, mirroring the
				// rollover in resetCounters(); last_sent_at would defer the
				// wake-up too late on accounts that kept sending mid-cycle.
				$cycle_start = (int) get_gmt_from_date( (string) $acct['last_cycle_reset'], 'U' );
				$free        = max( $free, $cycle_start + ( $interval * MINUTE_IN_SECONDS ) );
			} else {
				// Global-cycle accounts reset at the top of the next worker run.
				$free = max( $free, $now + $queue_interval );
			}
		}

		$daily_limit = intval( $acct['daily_limit'] );
		if ( $daily_limit > 0 && intval( $acct['daily_sent'] ) >= $daily_limit ) {
			$free = max( $free, self::nextLocalMidnight( $now ) );
		}

		$monthly_limit = intval( $acct['monthly_limit'] );
		if ( $monthly_limit > 0 && intval( $acct['monthly_sent'] ) >= $monthly_limit ) {
			$free = max( $free, self::nextLocalMonthStart( $now ) );
		}

		return $free;
	}

	/**
	 * UTC unix timestamp of the next local-midnight rollover — when daily
	 * counters reset.
	 *
	 * @param int $now Current UTC unix timestamp.
	 */
	private static function nextLocalMidnight( int $now ): int {
		$tomorrow_local = wp_date( 'Y-m-d', $now + DAY_IN_SECONDS );
		return (int) get_gmt_from_date( $tomorrow_local . ' 00:00:00', 'U' );
	}

	/**
	 * UTC unix timestamp of the first local day of next month — when monthly
	 * counters reset.
	 *
	 * @param int $now Current UTC unix timestamp.
	 */
	private static function nextLocalMonthStart( int $now ): int {
		$year  = (int) wp_date( 'Y', $now );
		$month = (int) wp_date( 'n', $now );
		++$month;
		if ( $month > 12 ) {
			$month = 1;
			++$year;
		}
		$local = sprintf( '%04d-%02d-01 00:00:00', $year, $month );
		return (int) get_gmt_from_date( $local, 'U' );
	}

	/**
	 * Every SMTP account, ordered by priority then name — the admin listing
	 * and the settings export both consume the full catalog.
	 *
	 * @return list<array<string,mixed>>
	 */
	public static function all(): array {
		global $wpdb;
		$smtp_table = Schema::smtpTable();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( "SELECT * FROM `$smtp_table` ORDER BY `priority` ASC, `name` ASC", ARRAY_A );
		return is_array( $rows ) ? array_values( $rows ) : array();
	}

	/**
	 * Map of account id => display name, for resolving the SMTP column in the
	 * Log table without loading every column.
	 *
	 * @return array<int,string>
	 */
	public static function namesById(): array {
		global $wpdb;
		$smtp_table = Schema::smtpTable();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( "SELECT `id`, `name` FROM `$smtp_table`", ARRAY_A );
		$map  = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$map[ (int) $row['id'] ] = (string) $row['name'];
		}
		return $map;
	}

	/**
	 * Fetch a single account by id.
	 *
	 * @param int $smtp_id Account id.
	 * @return array<string,mixed>|null Row, or null when not found.
	 */
	public static function findById( int $smtp_id ): ?array {
		global $wpdb;
		$smtp_table = Schema::smtpTable();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `$smtp_table` WHERE `id` = %d", $smtp_id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Insert a new account row, returning its id (0 on failure).
	 *
	 * @param array<string,mixed> $data Column => value map.
	 */
	public static function insert( array $data ): int {
		global $wpdb;
		$smtp_table = Schema::smtpTable();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$inserted = $wpdb->insert( $smtp_table, $data );
		return false === $inserted ? 0 : (int) $wpdb->insert_id;
	}

	/**
	 * Update an existing account row by id.
	 *
	 * @param int                 $smtp_id Account id.
	 * @param array<string,mixed> $data    Column => value map.
	 */
	public static function update( int $smtp_id, array $data ): void {
		global $wpdb;
		$smtp_table = Schema::smtpTable();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update( $smtp_table, $data, array( 'id' => $smtp_id ), null, array( '%d' ) );
	}

	/**
	 * Delete an account by id.
	 *
	 * @param int $smtp_id Account id.
	 */
	public static function delete( int $smtp_id ): void {
		global $wpdb;
		$smtp_table = Schema::smtpTable();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $smtp_table, array( 'id' => $smtp_id ), array( '%d' ) );
	}

	/**
	 * Zero the cumulative `daily_sent` / `monthly_sent` counters on every
	 * account. Backs the admin "Reset Counters" button (distinct from
	 * {@see Repository::resetCounters()}, which only rolls counters over when
	 * their period actually elapses).
	 */
	public static function zeroSentCounters(): void {
		global $wpdb;
		$smtp_table = Schema::smtpTable();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "UPDATE `$smtp_table` SET `daily_sent` = 0, `monthly_sent` = 0" );
	}

	/**
	 * Column names of the SMTP table — used by the XML import to discard
	 * unknown fields before inserting.
	 *
	 * @return list<string>
	 */
	public static function tableColumns(): array {
		global $wpdb;
		$smtp_table = Schema::smtpTable();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$columns = $wpdb->get_col( "DESCRIBE `$smtp_table`", 0 );
		return is_array( $columns ) ? array_values( array_map( 'strval', $columns ) ) : array();
	}

	/**
	 * Empty the SMTP table — used by the XML import before re-inserting the
	 * imported accounts.
	 */
	public static function truncate(): void {
		global $wpdb;
		$smtp_table = Schema::smtpTable();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "TRUNCATE TABLE `$smtp_table`" );
	}

	/**
	 * Enabled accounts whose per-cycle bulk exceeds the global queue amount —
	 * surfaced as a Settings-tab warning that the global limit caps them.
	 *
	 * @param int $global_amount The global per-run queue amount.
	 * @return list<array<string,mixed>> Rows with `name` and `send_bulk`.
	 */
	public static function accountsExceedingBulk( int $global_amount ): array {
		global $wpdb;
		$smtp_table = Schema::smtpTable();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from Schema::smtpTable()
				"SELECT `name`, `send_bulk` FROM `$smtp_table` WHERE `enabled` = 1 AND `send_bulk` > %d AND `send_bulk` > 0",
				$global_amount
			),
			ARRAY_A
		);
		return is_array( $rows ) ? array_values( $rows ) : array();
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
