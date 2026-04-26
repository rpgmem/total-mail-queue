<?php
/**
 * `wp_mail_succeeded` action handler.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Queue;

/**
 * For "instant" priority emails — the only ones that flow through both
 * `MailInterceptor::handle()` (which records the row id) AND `wp_mail()`'s
 * normal send — flip the queue row from `instant` to `sent` once
 * WordPress confirms the send succeeded.
 *
 * Accepted_args = 0 because the wp_mail_succeeded payload isn't useful
 * here; we identify the row via {@see Tracker}.
 */
final class MailSucceededHandler {

	/**
	 * Hook the handler onto `wp_mail_succeeded`.
	 */
	public static function register(): void {
		add_action( 'wp_mail_succeeded', array( self::class, 'handle' ), 10, 0 );
	}

	/**
	 * Handle one success event.
	 */
	public static function handle(): void {
		$mail_id = Tracker::get();
		if ( 0 === $mail_id ) {
			return;
		}
		if ( 'instant' === QueueRepository::statusFor( $mail_id ) ) {
			QueueRepository::update(
				$mail_id,
				array(
					'timestamp' => current_time( 'mysql', false ),
					'status'    => 'sent',
					'info'      => '',
				),
				array( '%s', '%s', '%s' )
			);
		}
		Tracker::reset();
	}
}
