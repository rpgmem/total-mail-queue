<?php
/**
 * "Test connection" admin AJAX handler.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Smtp;

use TotalMailQueue\Admin\Access;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use TotalMailQueue\Support\Encryption;

/**
 * Backs the "Test Connection" button on the SMTP Accounts admin tab.
 *
 * Validates the admin's permissions and the form nonce, opens a one-shot
 * SMTP connection with the supplied (or stored, on edit) credentials, and
 * returns a JSON success/error response.
 */
final class ConnectionTester {

	/**
	 * AJAX action name (matches the JS that posts to admin-ajax.php).
	 */
	public const ACTION = 'wp_tmq_test_smtp';

	/**
	 * Nonce action used by the form/JS.
	 */
	public const NONCE = 'wp_tmq_test_smtp';

	/**
	 * Connection timeout in seconds. Short on purpose — the user is staring
	 * at a spinner waiting for the answer.
	 */
	private const TIMEOUT = 15;

	/**
	 * Hook the AJAX handler into WordPress.
	 */
	public static function register(): void {
		add_action( 'wp_ajax_' . self::ACTION, array( self::class, 'handle' ) );
	}

	/**
	 * AJAX action handler. Responds with `wp_send_json_success` / `wp_send_json_error`.
	 */
	public static function handle(): void {
		if ( ! Access::isAdmin() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'total-mail-queue' ) ), 403 );
		}

		if ( ! check_ajax_referer( self::NONCE, '_nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed. Please reload the page and try again.', 'total-mail-queue' ) ), 403 );
		}

		$host       = sanitize_text_field( wp_unslash( $_POST['host'] ?? '' ) );
		$port       = intval( $_POST['port'] ?? 587 );
		$encryption = sanitize_key( $_POST['encryption'] ?? 'tls' );
		$auth       = intval( $_POST['auth'] ?? 0 );
		$username   = sanitize_text_field( wp_unslash( $_POST['username'] ?? '' ) );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- password must preserve special characters
		$password = wp_unslash( $_POST['password'] ?? '' );
		$smtp_id  = intval( $_POST['smtp_id'] ?? 0 );
		$m365     = M365Token::AUTH_TYPE === sanitize_key( wp_unslash( $_POST['auth_type'] ?? '' ) );
		$tenant   = sanitize_text_field( wp_unslash( $_POST['oauth_tenant'] ?? '' ) );
		$client   = sanitize_text_field( wp_unslash( $_POST['oauth_client_id'] ?? '' ) );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- a client secret must preserve special characters
		$secret = (string) wp_unslash( $_POST['oauth_client_secret'] ?? '' );

		if ( '' === $host ) {
			wp_send_json_error( array( 'message' => __( 'SMTP host is required.', 'total-mail-queue' ) ) );
		}

		// On edit, an empty password field means "keep the stored one".
		if ( '' === $password && 0 < $smtp_id ) {
			$stored = Repository::findPasswordById( $smtp_id );
			if ( '' !== $stored ) {
				$password = Encryption::decrypt( $stored );
			}
		}

		// The Microsoft 365 app: ask Microsoft first, uncached, so a wrong
		// secret or a missing consent is reported in Microsoft's words (#186).
		$token = '';
		if ( $auth && $m365 ) {
			if ( '' === $secret && 0 < $smtp_id ) {
				$secret = Encryption::decrypt( Repository::findOauthSecretById( $smtp_id ) );
			}
			$token = M365Token::fetch( $tenant, $client, $secret, false );
			if ( is_wp_error( $token ) ) {
				wp_send_json_error( array( 'message' => $token->get_error_message() ) );
			}
		}

		if ( ! class_exists( PHPMailer::class ) ) {
			require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
			require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
			require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
		}

		$mail = new PHPMailer( true );

		try {
			$mail->isSMTP();
			$mail->Host       = $host;
			$mail->Port       = $port;
			$mail->SMTPSecure = 'none' === $encryption ? '' : $encryption;
			$mail->SMTPAuth   = (bool) $auth;
			if ( $auth && $m365 ) {
				M365Credentials::apply( $mail, $username, (string) $token );
			} elseif ( $auth ) {
				$mail->Username = $username;
				$mail->Password = $password;
			}
			$mail->Timeout = self::TIMEOUT;

			$mail->smtpConnect();
			$mail->smtpClose();

			wp_send_json_success( array( 'message' => __( 'Connection successful! SMTP server responded correctly.', 'total-mail-queue' ) ) );
		} catch ( PHPMailerException $e ) {
			wp_send_json_error( array( 'message' => $mail->ErrorInfo ) );
		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}
}
