<?php
/**
 * Switch the plugin to Block mode when the queue alert fires.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Cron;

use TotalMailQueue\Settings\Options;

/**
 * Opt-in reaction to a queue alert: stop sending until an administrator has
 * looked.
 *
 * A queue that suddenly overflows is usually a site sending mail it should
 * not (a spam bot on a form, a compromised plugin). The alert only tells
 * someone; with `auto_block` on, the plugin also switches the operation mode
 * to Block, so nothing more leaves while they read it. Block keeps every
 * email, and switching back to Queue sends what was held.
 *
 * {@see self::OPTION} records when it happened, so the admin notice can say
 * why sending stopped. It is cleared as soon as the mode leaves Block, by the
 * administrator or anything else.
 */
final class AutoBlock {

	/**
	 * Option holding the moment (UTC unix seconds) the plugin blocked itself.
	 */
	public const OPTION = 'wp_tmq_auto_blocked';

	/**
	 * Clear the marker when the operation mode leaves Block.
	 */
	public static function register(): void {
		add_action( 'update_option_' . Options::OPTION_NAME, array( self::class, 'clearWhenResumed' ), 10, 2 );
	}

	/**
	 * Switch to Block mode when the setting is on and the plugin is in Queue
	 * mode. Called by {@see AlertSender} only when an alert is actually sent.
	 *
	 * @return bool True when the mode was switched.
	 */
	public static function maybeTrigger(): bool {
		$options = Options::get();
		if ( '1' !== (string) ( $options['auto_block'] ?? '0' ) || '1' !== (string) $options['enabled'] ) {
			return false;
		}

		$stored = get_option( Options::OPTION_NAME, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		$stored['enabled'] = '2';
		update_option( self::OPTION, time(), false );
		update_option( Options::OPTION_NAME, $stored );
		return true;
	}

	/**
	 * When the plugin blocked itself, or null.
	 */
	public static function activeSince(): ?int {
		$since = (int) get_option( self::OPTION, 0 );
		return $since > 0 ? $since : null;
	}

	/**
	 * `update_option_wp_tmq_settings` callback: drop the marker once the mode
	 * is no longer Block.
	 *
	 * @param mixed $old_value Previous settings.
	 * @param mixed $new_value New settings.
	 */
	public static function clearWhenResumed( $old_value, $new_value ): void {
		unset( $old_value );
		$mode = is_array( $new_value ) && isset( $new_value['enabled'] ) ? (string) $new_value['enabled'] : '0';
		if ( '2' !== $mode ) {
			delete_option( self::OPTION );
		}
	}
}
