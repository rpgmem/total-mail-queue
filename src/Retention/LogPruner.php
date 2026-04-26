<?php
/**
 * Log retention sweep.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Retention;

use TotalMailQueue\Database\Schema;
use TotalMailQueue\Settings\Options;

/**
 * Two complementary cleanups, both run at the end of every cron batch:
 *
 * - Age-based — delete log rows (status != queue/high) older than
 *   `clear_queue` hours. Always on; the user controls the retention
 *   window in days from the admin Settings.
 * - Cap-based — when `log_max_records` > 0, trim the oldest log rows so
 *   the surviving count never exceeds the cap.
 *
 * Pending rows (`queue` / `high`) are intentionally never deleted — only
 * sent / error / alert.
 */
final class LogPruner {

	/**
	 * Delete log rows older than the user-configured retention window.
	 *
	 * Acceptable to run multiple times per request (idempotent).
	 */
	public static function pruneByAge(): void {
		global $wpdb;
		$options = Options::get();
		$table   = Schema::queueTable();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM `$table` WHERE `status` != 'queue' AND `status` != 'high' AND `timestamp` < DATE_SUB(%s, INTERVAL %d HOUR)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				current_time( 'mysql', false ),
				(int) $options['clear_queue']
			)
		);
	}

	/**
	 * Trim the oldest log rows until at most `log_max_records` remain.
	 * No-op when the setting is 0 (unlimited).
	 */
	public static function pruneByCount(): void {
		global $wpdb;
		$options = Options::get();
		$cap     = (int) $options['log_max_records'];
		if ( $cap <= 0 ) {
			return;
		}
		$table = Schema::queueTable();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$table` WHERE `status` != 'queue' AND `status` != 'high'" );
		if ( $total <= $cap ) {
			return;
		}
		$excess = $total - $cap;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM `$table` WHERE `status` != 'queue' AND `status` != 'high' ORDER BY `timestamp` ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$excess
			)
		);
	}
}
