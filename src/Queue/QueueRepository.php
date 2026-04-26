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
 * retention sweeps used to inline through `$wpdb` against
 * `$wp_tmq_options['tableName']`.
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
	 * IDs of the next batch to process. High-priority rows always come first;
	 * low retry counts before high (so newly-queued mails go ahead of repeated
	 * failures), then by id.
	 *
	 * @param int $limit Maximum number of ids to return.
	 * @return list<int>
	 */
	public static function pendingIds( int $limit ): array {
		global $wpdb;
		$table = Schema::queueTable();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT `id` FROM `$table` WHERE `status` = 'queue' OR `status` = 'high' ORDER BY `status` ASC, `retry_count` ASC, `id` ASC LIMIT %d", $limit ) );
		return array_map( 'intval', is_array( $ids ) ? $ids : array() );
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
}
