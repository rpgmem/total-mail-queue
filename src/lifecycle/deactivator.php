<?php
/**
 * Plugin deactivation handler.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Lifecycle;

/**
 * Callback invoked by WordPress when the plugin is deactivated.
 *
 * Tears down only what the plugin actively schedules — the queue cron event.
 * Tables and options are preserved so a future re-activation picks the user's
 * data right back up.
 */
final class Deactivator {

	/**
	 * The cron hook name. Mirrored here (not pulled from a constant elsewhere)
	 * so the deactivation handler does not have to load any other class on a
	 * static-method call from WordPress.
	 */
	public const CRON_HOOK = 'wp_tmq_mail_queue_hook';

	/**
	 * Clear the queue-processing cron event.
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}
}
