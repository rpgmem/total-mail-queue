<?php
/**
 * `wp_mail_failed` action handler.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Queue;

use TotalMailQueue\Settings\Options;

/**
 * Marks a queued row as `error` (or returns it to the queue for retry,
 * up to `max_retries`) when WordPress fires `wp_mail_failed`.
 *
 * Reads the current row id from {@see Tracker} so that this handler and
 * {@see MailInterceptor} stay in sync without sharing globals.
 */
final class MailFailedHandler {

	/**
	 * Hook the handler onto `wp_mail_failed`.
	 */
	public static function register(): void {
		add_action( 'wp_mail_failed', array( self::class, 'handle' ), 10, 1 );
	}

	/**
	 * Handle one failure event.
	 *
	 * @param object $wp_error WP_Error-shaped object with an `errors` property.
	 */
	public static function handle( $wp_error ): void {
		$mail_id = Tracker::get();
		if ( 0 === $mail_id ) {
			return;
		}

		$options       = Options::get();
		$max_retries   = (int) $options['max_retries'];
		$retry_state   = QueueRepository::retryStateFor( $mail_id );
		$retry_count   = $retry_state ? $retry_state['retry_count'] : 0;
		$error_message = self::messageFromWpError( $wp_error );

		if ( $max_retries > 0 && $retry_count < $max_retries ) {
			$new_retry = $retry_count + 1;
			/* translators: %1$d: current attempt number, %2$d: max attempts, %3$s: error message */
			$retry_info = sprintf( __( 'Retry %1$d/%2$d — %3$s', 'total-mail-queue' ), $new_retry, $max_retries, $error_message );
			QueueRepository::update(
				$mail_id,
				array(
					'timestamp'   => current_time( 'mysql', false ),
					'status'      => 'queue',
					'retry_count' => $new_retry,
					'info'        => $retry_info,
				),
				array( '%s', '%s', '%d', '%s' )
			);
			return;
		}

		// Final error.
		if ( $max_retries > 0 && $retry_count >= $max_retries ) {
			/* translators: %1$d: total attempts, %2$s: error message */
			$error_message = sprintf( __( 'Failed after %1$d attempt(s) — %2$s', 'total-mail-queue' ), $retry_count + 1, $error_message );
		}
		QueueRepository::update(
			$mail_id,
			array(
				'timestamp' => current_time( 'mysql', false ),
				'status'    => 'error',
				'info'      => $error_message,
			),
			array( '%s', '%s', '%s' )
		);
	}

	/**
	 * Pull the error message out of a WP_Error-shaped object, falling back
	 * to a translated "Unknown" placeholder.
	 *
	 * @param object $wp_error WP_Error-shaped object.
	 */
	private static function messageFromWpError( $wp_error ): string {
		if ( isset( $wp_error->errors ) && isset( $wp_error->errors['wp_mail_failed'][0] ) ) {
			return implode( '; ', $wp_error->errors['wp_mail_failed'] );
		}
		return '<em>' . __( 'Unknown', 'total-mail-queue' ) . '</em>';
	}
}
