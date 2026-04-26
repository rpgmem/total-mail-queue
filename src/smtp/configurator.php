<?php
/**
 * Applies a stored SMTP account configuration to a PHPMailer instance.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Smtp;

use PHPMailer\PHPMailer\PHPMailer;
use TotalMailQueue\Settings\Options;
use TotalMailQueue\Support\Encryption;

/**
 * Mutates a PHPMailer instance so it sends through the supplied SMTP account.
 *
 * The cron loop creates one PHPMailer per request (WordPress reuses it
 * across `wp_mail()` calls), so before applying our account we close any
 * already-open SMTP connection — otherwise the second send would try to
 * reuse the previous server's socket.
 */
final class Configurator {

	/**
	 * Configure $phpmailer to send through $smtp_account.
	 *
	 * @param PHPMailer           $phpmailer    Mailer instance — modified in place.
	 * @param array<string,mixed> $smtp_account Row from the SMTP accounts table.
	 */
	public static function apply( PHPMailer $phpmailer, array $smtp_account ): void {
		// Close any existing SMTP connection so we start fresh.
		if ( method_exists( $phpmailer, 'smtpClose' ) ) {
			$phpmailer->smtpClose();
		}
		$phpmailer->isSMTP();
		$phpmailer->Host       = $smtp_account['host'];
		$phpmailer->Port       = intval( $smtp_account['port'] );
		$phpmailer->SMTPSecure = 'none' === $smtp_account['encryption'] ? '' : $smtp_account['encryption'];
		$phpmailer->SMTPAuth   = (bool) $smtp_account['auth'];
		if ( $smtp_account['auth'] ) {
			$phpmailer->Username = $smtp_account['username'];
			$phpmailer->Password = Encryption::decrypt( $smtp_account['password'] );
		}
		if ( ! empty( $smtp_account['from_email'] ) ) {
			$phpmailer->From     = $smtp_account['from_email'];
			$phpmailer->Sender   = $smtp_account['from_email'];
			$phpmailer->FromName = ! empty( $smtp_account['from_name'] ) ? $smtp_account['from_name'] : $phpmailer->FromName;
		}

		// Apply the configurable SMTP timeout to prevent a stalled connection
		// from blocking the entire cron batch.
		$options      = Options::get();
		$smtp_timeout = intval( $options['smtp_timeout'] );
		if ( $smtp_timeout > 0 ) {
			$phpmailer->Timeout = $smtp_timeout;
		}
	}
}
