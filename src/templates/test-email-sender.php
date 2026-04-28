<?php
/**
 * "Send test email" admin AJAX handler for the Templates tab.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Templates;

use TotalMailQueue\Sources\CoreTemplates;
use TotalMailQueue\Sources\Detector;

/**
 * Backs the "Send test email" buttons on:
 *
 *   - the Templates admin tab (no source_key → generic styled test)
 *   - the Sources tab edit form (source_key supplied → preview the
 *     wp_core template override the admin is editing, with fake but
 *     realistic dynamic values for {username}, {reset_url}, etc.)
 *
 * In both cases the message goes through the live `wp_mail()` pipeline
 * (Engine wrap → MailInterceptor → queue / direct send), so the
 * recipient sees exactly what a real recipient would.
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

		$source_key = sanitize_text_field( wp_unslash( $_POST['source_key'] ?? '' ) );

		if ( '' !== $source_key && CoreTemplates::isCoreTemplate( $source_key ) ) {
			self::sendCoreTemplatePreview( $to, $source_key );
			return;
		}

		self::sendGenericTest( $to );
	}

	/**
	 * Send the generic "is the engine wired?" test from the Templates tab.
	 *
	 * @param string $to Recipient.
	 */
	private static function sendGenericTest( string $to ): void {
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

	/**
	 * Preview a specific wp_core template by faking the source marker
	 * + context with realistic test values, then dispatching the
	 * matching default subject/body. The MailInterceptor pipeline picks
	 * up the marker, applies any saved override, and produces an email
	 * the admin can compare against the WP default.
	 *
	 * @param string $to         Recipient.
	 * @param string $source_key One of the keys CoreTemplates::isCoreTemplate accepts.
	 */
	private static function sendCoreTemplatePreview( string $to, string $source_key ): void {
		$defaults = CoreTemplates::get( $source_key );
		if ( null === $defaults ) {
			wp_send_json_error( array( 'message' => __( 'Unknown source.', 'total-mail-queue' ) ), 400 );
		}

		// Synthesize a realistic context. We never let these values look
		// like a real reset key / login URL — the prefix is unmistakable
		// and the URLs point to the current site so testing on production
		// can't accidentally hit a third-party endpoint.
		$site_url      = (string) get_option( 'siteurl' );
		$current_user  = wp_get_current_user();
		$preview_login = $current_user instanceof \WP_User && '' !== $current_user->user_login
			? (string) $current_user->user_login
			: 'preview-user';
		$preview_email = $current_user instanceof \WP_User && '' !== $current_user->user_email
			? (string) $current_user->user_email
			: 'preview@example.test';

		Detector::setCurrent(
			$source_key,
			$defaults['label'],
			'WordPress Core'
		);
		Detector::setData(
			array(
				'username'           => $preview_login,
				'user_email'         => $preview_email,
				'reset_url'          => $site_url . '/wp-login.php?action=rp&key=PREVIEW-FAKE-KEY&login=' . rawurlencode( $preview_login ),
				'set_password_url'   => $site_url . '/wp-login.php?action=rp&key=PREVIEW-FAKE-KEY&login=' . rawurlencode( $preview_login ),
				'login_url'          => wp_login_url(),
				'requester_ip'       => '127.0.0.1',
				'admin_email'        => (string) get_option( 'admin_email' ),
				'recipient'          => $to,
				'new_email'          => 'new-' . $preview_email,
				'admin_url'          => admin_url(),
				'description'        => __( '(preview action description)', 'total-mail-queue' ),
				'confirm_url'        => $site_url . '/?preview-confirm=fake',
				'export_url'         => $site_url . '/?preview-export=fake',
				'expiration'         => gmdate( 'Y-m-d', time() + ( 14 * DAY_IN_SECONDS ) ),
				'privacy_policy_url' => (string) get_privacy_policy_url(),
				'recovery_url'       => admin_url( 'tools.php?recovery=fake' ),
				'expires_time'       => __( '24 hours', 'total-mail-queue' ),
				'cause'              => __( '(preview cause)', 'total-mail-queue' ),
				'details'            => __( '(preview details)', 'total-mail-queue' ),
				'pageurl'            => $site_url,
			)
		);

		$preview_subject = '[PREVIEW] ' . $defaults['subject'];
		$preview_body    = $defaults['body'];

		$sent = wp_mail( $to, $preview_subject, $preview_body );

		if ( ! $sent ) {
			wp_send_json_error( array( 'message' => __( 'wp_mail() returned false. The message may still have landed in the queue — check the Log tab.', 'total-mail-queue' ) ) );
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: 1: source key, 2: recipient address */
					__( 'Preview of %1$s queued / sent to %2$s.', 'total-mail-queue' ),
					$source_key,
					$to
				),
			)
		);
	}
}
