<?php
/**
 * Capture PHPMailer configuration set by other plugins via `phpmailer_init`.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Smtp;

use PHPMailer\PHPMailer\PHPMailer;
use TotalMailQueue\Support\Encryption;

/**
 * Snapshot whatever third-party plugins (WP Mail SMTP, Mailgun, etc.)
 * configure on a fresh PHPMailer.
 *
 * The cron loop stores the snapshot as a serialized header on each queued
 * row, so when the row is finally sent the plugin can replay the same
 * SMTP configuration the original `wp_mail()` would have used at submit
 * time. Without this, queued emails would silently fall back to PHP mail.
 */
final class PhpMailerCapturer {

	/**
	 * Whether we are currently inside a capture pass. Other plugins can
	 * inspect this global to skip side effects when their `phpmailer_init`
	 * filter runs against our throwaway PHPMailer.
	 *
	 * @var bool
	 */
	public static bool $capturing = false;

	/**
	 * Build a throwaway PHPMailer, fire `phpmailer_init`, and return the
	 * resulting configuration when something has changed.
	 *
	 * @return array<string,mixed>|null Captured config, or null when no hook touched the mailer.
	 */
	public static function capture(): ?array {
		self::$capturing = true;
		$config          = array();

		if ( ! class_exists( PHPMailer::class ) ) {
			require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
			require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
			require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
		}

		$test_mailer = new PHPMailer( true );

		$default_host = $test_mailer->Host;
		$default_port = $test_mailer->Port;
		$default_auth = $test_mailer->SMTPAuth;

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core action
		do_action_ref_array( 'phpmailer_init', array( &$test_mailer ) );

		if ( $test_mailer->Host !== $default_host || $test_mailer->Port !== $default_port || $test_mailer->SMTPAuth !== $default_auth ) {
			$config['Mailer']     = $test_mailer->Mailer;
			$config['Host']       = $test_mailer->Host;
			$config['Port']       = $test_mailer->Port;
			$config['SMTPSecure'] = $test_mailer->SMTPSecure;
			$config['SMTPAuth']   = $test_mailer->SMTPAuth;
			$config['Username']   = $test_mailer->Username;
			$config['Password']   = Encryption::encrypt( $test_mailer->Password );
			$config['From']       = $test_mailer->From;
			$config['FromName']   = $test_mailer->FromName;
		}

		self::$capturing = false;
		return ! empty( $config ) ? $config : null;
	}

	/**
	 * Clear the `$capturing` flag. The flag is normally toggled false at
	 * the end of {@see capture()}, but a thrown exception mid-capture would
	 * leave it stuck on; tests and {@see \TotalMailQueue\Support\RuntimeState}
	 * call this to guarantee a clean per-request slate.
	 */
	public static function reset(): void {
		self::$capturing = false;
	}
}
