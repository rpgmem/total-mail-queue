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
		// `recipient` and `error_log` are TEXT columns, which can't carry a SQL
		// default — so guarantee a value here rather than in every caller, which
		// keeps inserts working under MySQL strict mode.
		foreach ( array( 'recipient', 'error_log' ) as $text_column ) {
			if ( ! array_key_exists( $text_column, $data ) ) {
				$data[ $text_column ] = '';
			}
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
	 * Build the WHERE fragment for a list: the Log (everything that has left
	 * the queue, optionally one status) or the Retention queue (`queue` /
	 * `high`), narrowed by the filter. Every dynamic value goes through
	 * `$wpdb->prepare`, and the status only when it is a known log status.
	 *
	 * @param ListFilter $filter  The filters.
	 * @param bool       $pending True for the Retention queue, false for the Log.
	 */
	private static function listWhere( ListFilter $filter, bool $pending ): string {
		global $wpdb;
		if ( $pending ) {
			$where = "(`status` = 'queue' OR `status` = 'high')";
		} elseif ( '' !== $filter->status && in_array( $filter->status, self::LOG_STATUSES, true ) ) {
			$where = $wpdb->prepare( '`status` = %s', $filter->status );
		} else {
			$where = "`status` != 'queue' AND `status` != 'high'";
		}
		if ( '' !== $filter->source ) {
			$where .= ' AND ' . $wpdb->prepare( '`source_key` = %s', $filter->source );
		}
		if ( '' !== $filter->search ) {
			// The recipient column holds JSON, where an accented name or a
			// slash is escaped (`Jo\u00e3o`), so the term is matched in both
			// spellings. Bodies are not searched: lists never load them.
			$like    = '%' . $wpdb->esc_like( $filter->search ) . '%';
			$escaped = trim( (string) wp_json_encode( $filter->search ), '"' );
			$json    = '%' . $wpdb->esc_like( $escaped ) . '%';
			$where  .= ' AND ' . $wpdb->prepare( '(`subject` LIKE %s OR `recipient` LIKE %s OR `recipient` LIKE %s)', $like, $like, $json );
		}
		// `timestamp` is site-local wall time, like the dates an admin picks.
		if ( '' !== $filter->from ) {
			$where .= ' AND ' . $wpdb->prepare( '`timestamp` >= %s', $filter->from . ' 00:00:00' );
		}
		if ( '' !== $filter->to ) {
			$where .= ' AND ' . $wpdb->prepare( '`timestamp` <= %s', $filter->to . ' 23:59:59' );
		}
		return $where;
	}

	/**
	 * Count Log rows matching the filter.
	 *
	 * @param ListFilter $filter The filters.
	 */
	public static function logCount( ListFilter $filter ): int {
		global $wpdb;
		$table = Schema::queueTable();
		$where = self::listWhere( $filter, false );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$table` WHERE $where" );
	}

	/**
	 * Fetch a page of Log rows (newest first) matching the filter.
	 *
	 * @param ListFilter $filter   The filters.
	 * @param int        $per_page Page size.
	 * @param int        $offset   Row offset.
	 * @return array<int,array<string,mixed>>
	 */
	public static function logPage( ListFilter $filter, int $per_page, int $offset ): array {
		global $wpdb;
		$table = Schema::queueTable();
		$where = self::listWhere( $filter, false );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `$table` WHERE $where ORDER BY `timestamp` DESC LIMIT %d OFFSET %d", $per_page, $offset ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Count pending rows matching the filter. The status filter does not
	 * apply: every row here is waiting.
	 *
	 * @param ListFilter $filter The filters.
	 */
	public static function queueCount( ListFilter $filter ): int {
		global $wpdb;
		$table = Schema::queueTable();
		$where = self::listWhere( $filter, true );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$table` WHERE $where" );
	}

	/**
	 * Fetch a page of pending rows in send order (priority, then retries,
	 * then id) matching the filter.
	 *
	 * @param ListFilter $filter   The filters.
	 * @param int        $per_page Page size.
	 * @param int        $offset   Row offset.
	 * @return array<int,array<string,mixed>>
	 */
	public static function queuePage( ListFilter $filter, int $per_page, int $offset ): array {
		global $wpdb;
		$table = Schema::queueTable();
		$where = self::listWhere( $filter, true );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `$table` WHERE $where ORDER BY `priority` ASC, `retry_count` ASC, `id` ASC LIMIT %d OFFSET %d", $per_page, $offset ), ARRAY_A );
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
