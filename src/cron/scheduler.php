<?php
/**
 * WP-Cron schedule registration for the queue worker.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Cron;

use TotalMailQueue\Settings\Options;

/**
 * Registers a custom cron schedule whose interval mirrors the
 * `queue_interval` setting, and (un)schedules the queue-processing event
 * based on whether the plugin is in queue mode.
 */
final class Scheduler {

	/**
	 * The action that fires the queue worker — also the cron event name.
	 */
	public const HOOK = 'wp_tmq_mail_queue_hook';

	/**
	 * Custom schedule slug. Registered on `cron_schedules` filter.
	 */
	public const INTERVAL_SLUG = 'wp_tmq_interval';

	/**
	 * Hook everything: the `cron_schedules` filter and the conditional
	 * event (un)scheduling that depends on whether the plugin is enabled.
	 *
	 * Called from {@see \TotalMailQueue\Plugin::boot()}.
	 */
	public static function register(): void {
		add_filter( 'cron_schedules', array( self::class, 'addInterval' ) );
		add_action( self::HOOK, array( BatchProcessor::class, 'run' ) );

		// Schedule / unschedule based on the current plugin mode.
		// Run on init so options + cron table are loaded.
		self::syncSchedule();
	}

	/**
	 * `cron_schedules` filter — append the plugin's custom interval.
	 *
	 * Note: `cron_schedules` can fire as early as `plugins_loaded` (before
	 * the `init` action), so this callback must NOT call any i18n function.
	 * Doing so would trigger WP 6.7+'s `_load_textdomain_just_in_time`
	 * notice. The display string is the plugin's brand name and stays as
	 * a plain literal.
	 *
	 * @param array<string,array<string,mixed>> $schedules Existing schedules.
	 * @return array<string,array<string,mixed>>
	 */
	public static function addInterval( $schedules ) {
		$options                          = Options::get();
		$schedules[ self::INTERVAL_SLUG ] = array(
			'interval' => (int) $options['queue_interval'],
			'display'  => 'Total Mail Queue',
		);
		return $schedules;
	}

	/**
	 * Make the schedule match the plugin mode:
	 * - `enabled = 1` (queue mode): event must exist.
	 * - any other mode: event must NOT exist.
	 */
	public static function syncSchedule(): void {
		$options    = Options::get();
		$next_run   = wp_next_scheduled( self::HOOK );
		$is_enabled = '1' === $options['enabled'];

		if ( $next_run && ! $is_enabled ) {
			wp_unschedule_event( $next_run, self::HOOK );
		} elseif ( ! $next_run && $is_enabled ) {
			wp_schedule_event( time(), self::INTERVAL_SLUG, self::HOOK );
		}
	}
}
