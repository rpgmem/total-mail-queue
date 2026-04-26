<?php
/**
 * Plugin settings: defaults, parsing, and persistence.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Settings;

/**
 * Single source of truth for the `wp_tmq_settings` option.
 *
 * {@see Options::get()} merges the persisted user settings with the
 * built-in defaults and applies the queue interval / clear-queue
 * conversions every consumer expects.
 */
final class Options {

	/**
	 * Option name under which the user-configurable settings live in `wp_options`.
	 */
	public const OPTION_NAME = 'wp_tmq_settings';

	/**
	 * Default values for every setting.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return array(
			'enabled'             => '0',   // 0=disabled, 1=queue (retain+send), 2=block (retain only, no sending).
			'alert_enabled'       => '0',
			'email'               => get_option( 'admin_email' ),
			'email_amount'        => '10',
			'queue_amount'        => '1',
			'queue_interval'      => '5',
			'queue_interval_unit' => 'minutes',
			'clear_queue'         => '14',
			'log_max_records'     => '0',   // 0=unlimited, >0=max number of log entries to keep.
			'send_method'         => 'auto', // auto=SMTP if available then captured then default, smtp=only plugin SMTP, php=default wp_mail only.
			'max_retries'         => '3',   // 0=no retries, >0=auto-retry failed emails up to N times.
			'cron_lock_ttl'       => '300', // seconds — safety timeout for the cross-process cron lock.
			'smtp_timeout'        => '30',  // seconds — per-connection SMTP timeout during queue sending.
			'tableName'           => 'total_mail_queue',
			'smtpTableName'       => 'total_mail_queue_smtp',
			'triggercount'        => 0,
		);
	}

	/**
	 * Read the persisted option, merge with defaults, and normalise derived
	 * fields (queue_interval expressed in seconds, clear_queue expressed in
	 * hours).
	 *
	 * @return array<string,mixed>
	 */
	public static function get(): array {
		$args    = get_option( self::OPTION_NAME );
		$options = wp_parse_args( $args, self::defaults() );

		if ( 'seconds' === $options['queue_interval_unit'] ) {
			$options['queue_interval'] = intval( $options['queue_interval'] );
			if ( $options['queue_interval'] < 10 ) {
				// Minimum Interval 10 Seconds.
				$options['queue_interval'] = 10;
			}
		} else {
			$options['queue_interval'] = intval( $options['queue_interval'] ) * 60;
		}

		$options['clear_queue'] = intval( $options['clear_queue'] ) * 24;
		return $options;
	}
}
