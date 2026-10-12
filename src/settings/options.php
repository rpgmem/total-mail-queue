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
	 * Values the `dark_mode` setting accepts: never, always, or whatever the
	 * operating system prefers.
	 */
	public const DARK_MODES = array( 'off', 'on', 'auto' );

	/**
	 * Numeric settings and the smallest value each one accepts.
	 *
	 * A cleared number field posts an empty string, and `intval( '' )` is 0.
	 * For these keys 0 is not a neutral value: an empty `clear_queue` deleted
	 * every log row on the next cron run and an empty `queue_amount` sent
	 * nothing. {@see self::number()} applies these floors on save and on read.
	 *
	 * @var array<string,int>
	 */
	public const NUMBER_FLOORS = array(
		'email_amount'    => 1,
		'queue_amount'    => 1,
		'queue_interval'  => 1,
		'clear_queue'     => 1,
		'log_max_records' => 0,
		'max_retries'     => 0,
		'smtp_timeout'    => 5,
		'cron_lock_ttl'   => 30,
	);

	/**
	 * Default values for every setting.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return array(
			'enabled'              => '0',   // 0=disabled, 1=queue (retain+send), 2=block (retain only, no sending).
			'alert_enabled'        => '0',
			'email'                => get_option( 'admin_email' ),
			'email_amount'         => '10',
			'queue_amount'         => '1',
			'queue_interval'       => '5',
			'queue_interval_unit'  => 'minutes',
			'clear_queue'          => '14',
			'log_max_records'      => '0',   // 0=unlimited, >0=max number of log entries to keep.
			'send_method'          => 'auto', // auto=SMTP if available then captured then default, smtp=only plugin SMTP, php=default wp_mail only.
			'max_retries'          => '3',   // 0=no retries, >0=auto-retry failed emails up to N times.
			'cron_lock_ttl'        => '300', // seconds — safety timeout for the cross-process cron lock.
			'smtp_timeout'         => '30',  // seconds — per-connection SMTP timeout during queue sending.
			'smtp_debug'           => '0',   // 0=off, 1=capture the full SMTP conversation into each row's error log.
			'tableName'            => 'total_mail_queue',
			'smtpTableName'        => 'total_mail_queue_smtp',
			'triggercount'         => 0,
			'from_email'           => '',
			'from_name'            => '',
			'dark_mode'            => 'off', // off|on|auto — the plugin's admin screens only.
			'auto_block'           => '0',   // 1=switch to Block mode when the queue alert fires.
			'block_password_reset' => '1',  // 1=password resets are sent even in Block mode.
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
		$args     = get_option( self::OPTION_NAME );
		$defaults = self::defaults();
		$options  = wp_parse_args( $args, $defaults );

		// A value stored before the save path refused empty strings must not
		// reach the consumers as 0.
		foreach ( self::NUMBER_FLOORS as $key => $floor ) {
			$options[ $key ] = self::number( $options[ $key ], $key, (string) $defaults[ $key ] );
		}

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

	/**
	 * Normalise one numeric setting.
	 *
	 * A value that is not a number, the empty string included, means "not
	 * supplied" and yields the fallback. A number below the key's floor is
	 * raised to it.
	 *
	 * @param mixed  $raw      Value to normalise.
	 * @param string $key      A key of {@see self::NUMBER_FLOORS}.
	 * @param string $fallback Value to use when `$raw` is not a number.
	 * @return string The normalised value, as the option stores it.
	 */
	public static function number( $raw, string $key, string $fallback ): string {
		if ( ! is_numeric( $raw ) ) {
			return $fallback;
		}
		return (string) max( self::NUMBER_FLOORS[ $key ] ?? 0, (int) $raw );
	}
}
