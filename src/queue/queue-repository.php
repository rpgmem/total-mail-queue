<?php
/**
 * Repository for the queue/log table.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Queue;

use TotalMailQueue\Database\Schema;

/**
 * Read/write access to `{$prefix}total_mail_queue`.
 *
 * Holds every SQL query the cron loop, the mail interceptor and the
 * retention sweeps issue. Table name is resolved through
 * {@see Schema::queueTable()}.
 */
final class QueueRepository {

	/**
	 * Insert a new row, returning the auto-increment id (or 0 on failure).
	 *
	 * @param array<string,mixed> $data Queue row.
	 * @return int Insert id, or 0 when the insert failed.
	 */
	public static function insert( array $data ): int {
		global $wpdb;
		$table = Schema::queueTable();
		// `error_log` is `longtext NOT NULL`, and TEXT columns can't carry a SQL
		// default — so guarantee a value here rather than in every caller, which
		// keeps inserts working under MySQL strict mode.
		if ( ! array_key_exists( 'error_log', $data ) ) {
			$data['error_log'] = '';
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$inserted = $wpdb->insert( $table, $data );
		if ( false === $inserted ) {
			return 0;
		}
		return (int) $wpdb->insert_id;
	}

	/**
	 * Update an existing row by id.
	 *
	 * @param int                 $id      Row id.
	 * @param array<string,mixed> $data    Columns to update.
	 * @param array<int,string>   $formats Optional `wpdb->update` $format array.
	 * @return int|false Number of rows affected, or false on error.
	 */
	public static function update( int $id, array $data, ?array $formats = null ) {
		global $wpdb;
		$table = Schema::queueTable();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->update( $table, $data, array( 'id' => $id ), $formats, array( '%d' ) );
	}

	/**
	 * Fetch a single row by id.
	 *
	 * @param int $id Row id.
	 * @return array<string,mixed>|null Row, or null when not found.
	 */
	public static function findById( int $id ): ?array {
		global $wpdb;
		$table = Schema::queueTable();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `$table` WHERE `id` = %d", $id ), ARRAY_A );
		return $row ? $row : null;
	}

	/**
	 * Total number of rows currently waiting in the queue.
	 *
	 * @return int Count of rows whose status is `queue` or `high`.
	 */
	public static function pendingCount(): int {
		global $wpdb;
		$table = Schema::queueTable();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$table` WHERE `status` = 'queue' OR `status` = 'high'" );
	}

	/**
	 * IDs of the next batch to process, in send order: most urgent first on the
	 * unified {@see Priority} scale (lower number wins), then low retry counts
	 * before high (so newly-queued mails go ahead of repeated failures), then
	 * by id.
	 *
	 * @param int $limit Maximum number of ids to return.
	 * @return list<int>
	 */
	public static function pendingIds( int $limit ): array {
		global $wpdb;
		$table = Schema::queueTable();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT `id` FROM `$table` WHERE `status` = 'queue' OR `status` = 'high' ORDER BY `priority` ASC, `retry_count` ASC, `id` ASC LIMIT %d", $limit ) );
		return array_values( array_map( 'intval', is_array( $ids ) ? $ids : array() ) );
	}

	/**
	 * Whether an `alert` row was already inserted in the last 6 hours.
	 *
	 * Used to throttle the queue-overflow alert.
	 *
	 * @return bool True when a recent alert exists.
	 */
	public static function recentAlertExists(): bool {
		global $wpdb;
		$table = Schema::queueTable();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `$table` WHERE `status` = 'alert' AND `timestamp` > DATE_SUB(%s, INTERVAL 6 HOUR)", current_time( 'mysql', false ) ), ARRAY_A );
		return ! empty( $rows );
	}

	/**
	 * Read the `info` column for the given row, or '' when the row is gone.
	 *
	 * @param int $id Row id.
	 */
	public static function infoFor( int $id ): string {
		global $wpdb;
		$table = Schema::queueTable();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$info = $wpdb->get_var( $wpdb->prepare( "SELECT `info` FROM `$table` WHERE `id` = %d", $id ) );
		return is_string( $info ) ? $info : '';
	}

	/**
	 * Read the `error_log` column for the given row, or '' when the row is gone.
	 *
	 * @param int $id Row id.
	 */
	public static function errorLogFor( int $id ): string {
		global $wpdb;
		$table = Schema::queueTable();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$log = $wpdb->get_var( $wpdb->prepare( "SELECT `error_log` FROM `$table` WHERE `id` = %d", $id ) );
		return is_string( $log ) ? $log : '';
	}

	/**
	 * Append an entry to a row's persistent `error_log`, keeping the history of
	 * every failed attempt. Done as an atomic `CONCAT` so concurrent cron runs
	 * can't clobber each other's appends. The log is cleared (set to '') by the
	 * success paths, so a row that eventually sends keeps no stale errors.
	 *
	 * @param int    $id    Row id.
	 * @param string $entry Text to append (a trailing newline is added).
	 */
	public static function appendErrorLog( int $id, string $entry ): void {
		global $wpdb;
		$table = Schema::queueTable();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE `$table` SET `error_log` = CONCAT(`error_log`, %s) WHERE `id` = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$entry . "\n",
				$id
			)
		);
	}

	/**
	 * Read the `status` column for the given row, or '' when the row is gone.
	 *
	 * @param int $id Row id.
	 */
	public static function statusFor( int $id ): string {
		global $wpdb;
		$table = Schema::queueTable();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$status = $wpdb->get_var( $wpdb->prepare( "SELECT `status` FROM `$table` WHERE `id` = %d", $id ) );
		return is_string( $status ) ? $status : '';
	}

	/**
	 * Read the (`retry_count`, `status`) tuple for the given row.
	 *
	 * @param int $id Row id.
	 * @return array{retry_count:int,status:string}|null Null when the row is gone.
	 */
	public static function retryStateFor( int $id ): ?array {
		global $wpdb;
		$table = Schema::queueTable();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT `retry_count`, `status` FROM `$table` WHERE `id` = %d", $id ), ARRAY_A );
		if ( ! $row ) {
			return null;
		}
		return array(
			'retry_count' => (int) $row['retry_count'],
			'status'      => (string) $row['status'],
		);
	}

	/**
	 * Delete a row by id.
	 *
	 * @param int $id Row id.
	 */
	public static function delete( int $id ): void {
		global $wpdb;
		$table = Schema::queueTable();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Statuses that count as "log" rows (everything that has left the queue).
	 * Used to validate the admin status filter so an arbitrary value can't be
	 * spliced into the WHERE clause.
	 */
	private const LOG_STATUSES = array( 'sent', 'error', 'alert', 'blocked_by_source' );

	/**
	 * Build the WHERE fragment for the Log view: an optional exact-status
	 * filter (falling back to "anything not still pending") plus an optional
	 * source filter. Both dynamic values go through `$wpdb->prepare`.
	 *
	 * @param string $status_filter Optional status to match exactly.
	 * @param string $source_filter Optional `source_key` to match exactly.
	 */
	private static function logWhere( string $status_filter, string $source_filter ): string {
		global $wpdb;
		if ( '' !== $status_filter && in_array( $status_filter, self::LOG_STATUSES, true ) ) {
			$base = $wpdb->prepare( '`status` = %s', $status_filter );
		} else {
			$base = "`status` != 'queue' AND `status` != 'high'";
		}
		if ( '' !== $source_filter ) {
			$base .= ' AND ' . $wpdb->prepare( '`source_key` = %s', $source_filter );
		}
		return $base;
	}

	/**
	 * Count Log rows matching the status / source filter.
	 *
	 * @param string $status_filter Optional status filter.
	 * @param string $source_filter Optional source filter.
	 */
	public static function logCount( string $status_filter, string $source_filter ): int {
		global $wpdb;
		$table = Schema::queueTable();
		$where = self::logWhere( $status_filter, $source_filter );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$table` WHERE $where" );
	}

	/**
	 * Fetch a page of Log rows (newest first) matching the filter.
	 *
	 * @param string $status_filter Optional status filter.
	 * @param string $source_filter Optional source filter.
	 * @param int    $per_page      Page size.
	 * @param int    $offset        Row offset.
	 * @return array<int,array<string,mixed>>
	 */
	public static function logPage( string $status_filter, string $source_filter, int $per_page, int $offset ): array {
		global $wpdb;
		$table = Schema::queueTable();
		$where = self::logWhere( $status_filter, $source_filter );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `$table` WHERE $where ORDER BY `timestamp` DESC LIMIT %d OFFSET %d", $per_page, $offset ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Fetch a page of pending rows in send order (priority, then retries, then id).
	 *
	 * @param int $per_page Page size.
	 * @param int $offset   Row offset.
	 * @return array<int,array<string,mixed>>
	 */
	public static function queuePage( int $per_page, int $offset ): array {
		global $wpdb;
		$table = Schema::queueTable();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `$table` WHERE `status` = 'queue' OR `status` = 'high' ORDER BY `priority` ASC, `retry_count` ASC, `id` ASC LIMIT %d OFFSET %d", $per_page, $offset ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * The most recent non-pending row (sent / error / alert / blocked), or
	 * null when the log is empty. Backs the "WordPress can't send email"
	 * admin notice.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function lastLogRow(): ?array {
		global $wpdb;
		$table = Schema::queueTable();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row( "SELECT * FROM `$table` WHERE `status` != 'queue' AND `status` != 'high' ORDER BY `id` DESC", ARRAY_A );
		return is_array( $row ) ? $row : null;
	}
}
