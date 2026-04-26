<?php
/**
 * In-process state shared between the mail interceptor and the success/fail handlers.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Queue;

/**
 * Replaces the legacy `$wp_tmq_mailid` global.
 *
 * `MailInterceptor::handle()` records the queue row id of the email
 * currently flowing through `wp_mail()` so that {@see MailFailedHandler}
 * and {@see MailSucceededHandler} can update the same row when WordPress
 * fires `wp_mail_failed` / `wp_mail_succeeded`. The lifecycle is strictly
 * synchronous — PHP-single-thread per request — so a static property
 * is enough.
 */
final class Tracker {

	/**
	 * ID of the queue row currently being processed, or 0 between sends.
	 *
	 * @var int
	 */
	private static int $current_id = 0;

	/**
	 * Record the id of the row currently flowing through wp_mail().
	 *
	 * @param int $id Queue row id.
	 */
	public static function set( int $id ): void {
		self::$current_id = $id;
	}

	/**
	 * Read the in-flight row id, or 0 if none.
	 */
	public static function get(): int {
		return self::$current_id;
	}

	/**
	 * Clear the in-flight id. Called at the end of a successful instant send,
	 * or by tests between cases.
	 */
	public static function reset(): void {
		self::$current_id = 0;
	}
}
