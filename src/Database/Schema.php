<?php
/**
 * Plugin database schema.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Database;

/**
 * Authoritative definition of the plugin's database tables.
 *
 * Two tables are managed: `{$prefix}total_mail_queue` (the queue + log) and
 * `{$prefix}total_mail_queue_smtp` (configured SMTP accounts). The schema is
 * applied via WordPress's `dbDelta()` so it's safe to run on every upgrade —
 * existing columns and indexes are preserved, additions are rolled forward.
 */
final class Schema {

	/**
	 * Unprefixed name of the queue/log table.
	 */
	public const QUEUE_TABLE = 'total_mail_queue';

	/**
	 * Unprefixed name of the SMTP accounts table.
	 */
	public const SMTP_TABLE = 'total_mail_queue_smtp';

	/**
	 * Apply the schema via `dbDelta()`.
	 *
	 * Idempotent: running twice in a row is a no-op against an up-to-date
	 * database, and is safe against an older one.
	 */
	public static function install(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$queue_table     = self::queueTable();
		$smtp_table      = self::smtpTable();

		$sql = "CREATE TABLE $queue_table (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		timestamp TIMESTAMP NOT NULL,
		status varchar(55) DEFAULT '' NOT NULL,
		recipient varchar(255) DEFAULT '' NOT NULL,
		subject varchar(255) DEFAULT '' NOT NULL,
		message mediumtext NOT NULL,
		headers text NOT NULL,
		attachments text NOT NULL,
		info varchar(255) DEFAULT '' NOT NULL,
		retry_count smallint(5) DEFAULT 0 NOT NULL,
		smtp_account_id mediumint(9) DEFAULT 0 NOT NULL,
		PRIMARY KEY  (id),
		KEY idx_status_retry (status, retry_count, id),
		KEY idx_status_timestamp (status, timestamp)
		) $charset_collate;";

		$sql .= "CREATE TABLE $smtp_table (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		name varchar(100) DEFAULT '' NOT NULL,
		host varchar(255) DEFAULT '' NOT NULL,
		port smallint(5) DEFAULT 587 NOT NULL,
		encryption varchar(10) DEFAULT 'tls' NOT NULL,
		auth tinyint(1) DEFAULT 1 NOT NULL,
		username varchar(255) DEFAULT '' NOT NULL,
		password text NOT NULL,
		from_email varchar(255) DEFAULT '' NOT NULL,
		from_name varchar(255) DEFAULT '' NOT NULL,
		priority mediumint(9) DEFAULT 0 NOT NULL,
		daily_limit int(11) DEFAULT 0 NOT NULL,
		monthly_limit int(11) DEFAULT 0 NOT NULL,
		daily_sent int(11) DEFAULT 0 NOT NULL,
		monthly_sent int(11) DEFAULT 0 NOT NULL,
		last_daily_reset date DEFAULT '2000-01-01' NOT NULL,
		last_monthly_reset date DEFAULT '2000-01-01' NOT NULL,
		enabled tinyint(1) DEFAULT 1 NOT NULL,
		send_interval int(11) DEFAULT 0 NOT NULL,
		send_bulk int(11) DEFAULT 0 NOT NULL,
		last_sent_at datetime DEFAULT '2000-01-01 00:00:00' NOT NULL,
		cycle_sent int(11) DEFAULT 0 NOT NULL,
		PRIMARY KEY  (id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Drop both tables. Called by the uninstaller.
	 */
	public static function drop(): void {
		global $wpdb;

		$queue_table = self::queueTable();
		$smtp_table  = self::smtpTable();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS `$queue_table`" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS `$smtp_table`" );
	}

	/**
	 * Return the prefixed queue table name (for callers that build their own SQL).
	 *
	 * @return string e.g. `wp_total_mail_queue`.
	 */
	public static function queueTable(): string {
		global $wpdb;
		return $wpdb->prefix . self::QUEUE_TABLE;
	}

	/**
	 * Return the prefixed SMTP accounts table name.
	 *
	 * @return string e.g. `wp_total_mail_queue_smtp`.
	 */
	public static function smtpTable(): string {
		global $wpdb;
		return $wpdb->prefix . self::SMTP_TABLE;
	}
}
