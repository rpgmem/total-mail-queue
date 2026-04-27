<?php
/**
 * "Send test email" admin AJAX handler for the Templates tab.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Templates;

/**
 * Backs the "Send test email" button on the Templates admin tab.
 *
 * Sends a small message through `wp_mail()` to the address the admin types
 * in. Because Engine is registered on the same `wp_mail` filters, the
 * outgoing test goes through the exact pipeline a real email would —
 * caller sees the wrapped HTML in their inbox, with current template
 * settings applied.
 */
final class TestEmailSender {

	/**
	 * AJAX action name.
	 */
	public const ACTION = 'wp_tmq_send_template_test';

	/**
	 * Nonce action.
	 */
	public const NONCE = 'wp_tmq_send_template_test';

	/**
	 * Hook the AJAX handler.
	 */
	public static function register(): void {
		add_action( 'wp_ajax_' . self::ACTION, array( self::class, 'handle' ) );
	}

	/**
	 * AJAX action handler. Responds with `wp_send_json_success` /
	 * `wp_send_json_error`.
	 */
	public static function handle(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'total-mail-queue' ) ), 403 );
		}

		if ( ! check_ajax_referer( self::NONCE, '_nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed. Please reload the page and try again.', 'total-mail-queue' ) ), 403 );
		}

		$to = sanitize_email( wp_unslash( $_POST['to'] ?? '' ) );
		if ( '' === $to || ! is_email( $to ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid recipient email.', 'total-mail-queue' ) ), 400 );
		}

		$subject = sprintf(
			/* translators: %s: site title */
			__( '%s — template test', 'total-mail-queue' ),
			(string) get_bloginfo( 'name' )
		);

		$message = __( 'This is a test email from Total Mail Queue. If you are seeing this rendered as a styled card with a colored header, the template engine is wired up correctly.', 'total-mail-queue' );

		$sent = wp_mail( $to, $subject, $message );

		if ( ! $sent ) {
			wp_send_json_error( array( 'message' => __( 'wp_mail() returned false. The message may still have landed in the queue — check the Log tab.', 'total-mail-queue' ) ) );
		}

		wp_send_json_success(
			array(
				/* translators: %s: recipient address */
				'message' => sprintf( __( 'Test email queued / sent to %s.', 'total-mail-queue' ), $to ),
			)
		);
	}
}
