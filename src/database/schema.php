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
 * Three tables are managed:
 * - `{$prefix}total_mail_queue` — the queue + log.
 * - `{$prefix}total_mail_queue_smtp` — configured SMTP accounts.
 * - `{$prefix}total_mail_queue_sources` — registered "origins" (e.g. WP
 *   core password reset, WooCommerce new order). Each enqueued message
 *   references one of these via the `source_key` column on the queue
 *   table, and the admin can toggle delivery per-source.
 *
 * The schema is applied via WordPress's `dbDelta()` so it's safe to run on
 * every upgrade — existing columns and indexes are preserved, additions are
 * rolled forward.
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
	 * Unprefixed name of the message-sources catalog table.
	 */
	public const SOURCES_TABLE = 'total_mail_queue_sources';

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
		$sources_table   = self::sourcesTable();

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
		source_key varchar(120) DEFAULT '' NOT NULL,
		PRIMARY KEY  (id),
		KEY idx_status_retry (status, retry_count, id),
		KEY idx_status_timestamp (status, timestamp),
		KEY idx_source_key (source_key)
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

		$sql .= "CREATE TABLE $sources_table (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		source_key varchar(120) DEFAULT '' NOT NULL,
		label varchar(255) DEFAULT '' NOT NULL,
		group_label varchar(120) DEFAULT '' NOT NULL,
		label_override varchar(255) DEFAULT '' NOT NULL,
		group_override varchar(120) DEFAULT '' NOT NULL,
		enabled tinyint(1) DEFAULT 1 NOT NULL,
		detected_at datetime DEFAULT '2000-01-01 00:00:00' NOT NULL,
		last_seen_at datetime DEFAULT '2000-01-01 00:00:00' NOT NULL,
		total_count int(11) DEFAULT 0 NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY source_key (source_key)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Drop every plugin table. Called by the uninstaller.
	 */
	public static function drop(): void {
		global $wpdb;

		$queue_table   = self::queueTable();
		$smtp_table    = self::smtpTable();
		$sources_table = self::sourcesTable();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS `$queue_table`" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS `$smtp_table`" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS `$sources_table`" );
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

	/**
	 * Return the prefixed message-sources catalog table name.
	 *
	 * @return string e.g. `wp_total_mail_queue_sources`.
	 */
	public static function sourcesTable(): string {
		global $wpdb;
		return $wpdb->prefix . self::SOURCES_TABLE;
	}
}
