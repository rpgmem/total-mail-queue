<?php
/**
 * Sanitizer for the plugin's settings form.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Settings;

/**
 * Whitelists the keys allowed through the Settings API form so that an
 * attacker cannot inject extra option keys (e.g. `tableName`) by crafting
 * the POST payload, and runs `sanitize_text_field()` over each kept value.
 */
final class Sanitizer {

	/**
	 * Keys the user is allowed to set from the Settings page.
	 *
	 * @var array<int,string>
	 */
	private const ALLOWED_KEYS = array(
		'enabled',
		'alert_enabled',
		'email',
		'email_amount',
		'queue_amount',
		'queue_interval',
		'queue_interval_unit',
		'clear_queue',
		'log_max_records',
		'send_method',
		'max_retries',
		'cron_lock_ttl',
		'smtp_timeout',
	);

	/**
	 * `register_setting()` `sanitize_callback`. Drops unknown keys and runs
	 * `sanitize_text_field()` over every kept value.
	 *
	 * @param mixed $input Raw input from `$_POST` (typically array, may be anything).
	 * @return array<string,string> Sanitized settings, ready to merge into the option.
	 */
	public static function sanitize( $input ): array {
		$sanitized = array();
		if ( ! is_array( $input ) ) {
			return $sanitized;
		}
		foreach ( $input as $key => $value ) {
			if ( in_array( $key, self::ALLOWED_KEYS, true ) ) {
				$sanitized[ $key ] = sanitize_text_field( $value );
			}
		}
		return $sanitized;
	}
}
