<?php
/**
 * Save an on/off setting the moment its toggle flips.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Admin;

use TotalMailQueue\Settings\Options;
use TotalMailQueue\Templates\Options as TemplateOptions;

/**
 * AJAX endpoint behind the autosave toggles (`wp_ajax_tmq_update_setting`).
 *
 * Only the keys in {@see self::KEYS} can be written, each to the option it
 * belongs to, and only as a boolean. A write is a merge: it reads the stored
 * option, changes that one key and saves the whole thing back through the
 * same path the form uses — `update_option()` on the settings, whose
 * registered sanitizer and update hooks (worker reschedule, auto-block
 * marker) then run as on a form save; `TemplateOptions::update()` on the
 * template settings.
 *
 * The toggles also stay named fields of their form, with a hidden "off"
 * value, so the form's Save writes their current state instead of resetting
 * what autosave stored.
 */
final class SettingsAutosave {

	/**
	 * The AJAX action.
	 */
	public const ACTION = 'tmq_update_setting';

	/**
	 * Nonce action the page localizes and the endpoint checks.
	 */
	public const NONCE = 'tmq_autosave';

	/**
	 * Writable keys: `target:key` => the key inside that target's option.
	 */
	public const KEYS = array(
		'settings:alert_enabled'        => 'alert_enabled',
		'settings:auto_block'           => 'auto_block',
		'settings:block_password_reset' => 'block_password_reset',
		'settings:smtp_debug'           => 'smtp_debug',
		'template:enabled'              => 'enabled',
		'template:footer_powered_by'    => 'footer_powered_by',
	);

	/**
	 * Hook the endpoint.
	 */
	public static function register(): void {
		add_action( 'wp_ajax_' . self::ACTION, array( self::class, 'handle' ) );
	}

	/**
	 * Handle one toggle flip. Replies with JSON and ends the request.
	 */
	public static function handle(): void {
		if ( ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session expired. Reload the page and try again.', 'total-mail-queue' ) ), 403 );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to change these settings.', 'total-mail-queue' ) ), 403 );
		}

		$key   = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';
		$value = isset( $_POST['value'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['value'] ) ) ? '1' : '0';
		if ( ! isset( self::KEYS[ $key ] ) ) {
			wp_send_json_error( array( 'message' => __( 'This setting cannot be saved this way.', 'total-mail-queue' ) ), 400 );
		}

		self::write( $key, $value );
		wp_send_json_success(
			array(
				'key'   => $key,
				'value' => $value,
			)
		);
	}

	/**
	 * Merge one allowlisted boolean into its option.
	 *
	 * @param string $key   An entry of {@see self::KEYS}.
	 * @param string $value '1' or '0'.
	 */
	public static function write( string $key, string $value ): void {
		$field = self::KEYS[ $key ];
		if ( 0 === strpos( $key, 'template:' ) ) {
			$stored           = TemplateOptions::get();
			$stored[ $field ] = '1' === $value;
			TemplateOptions::update( $stored );
			return;
		}

		$stored = get_option( Options::OPTION_NAME, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		$stored[ $field ] = $value;
		update_option( Options::OPTION_NAME, $stored );
	}
}
