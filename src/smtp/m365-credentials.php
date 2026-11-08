<?php
/**
 * XOAUTH2 credentials for PHPMailer.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Smtp;

if ( ! interface_exists( \PHPMailer\PHPMailer\OAuthTokenProvider::class ) ) {
	// WordPress ships the interface beside PHPMailer but does not load it.
	require_once ABSPATH . WPINC . '/PHPMailer/OAuthTokenProvider.php';
}

/**
 * Hands PHPMailer the XOAUTH2 string for one mailbox and one token (#186):
 * `user=<mailbox>^Aauth=Bearer <token>^A^A`, base64-encoded, the format
 * Exchange Online expects after `AUTH XOAUTH2`.
 */
final class M365Credentials implements \PHPMailer\PHPMailer\OAuthTokenProvider {

	/**
	 * Mailbox the emails are sent as.
	 *
	 * @var string
	 */
	private string $mailbox;

	/**
	 * Access token.
	 *
	 * @var string
	 */
	private string $token;

	/**
	 * Build the credentials.
	 *
	 * @param string $mailbox Mailbox the emails are sent as.
	 * @param string $token   Access token from {@see M365Token}.
	 */
	public function __construct( string $mailbox, string $token ) {
		$this->mailbox = $mailbox;
		$this->token   = $token;
	}

	/**
	 * The base64-encoded XOAUTH2 string.
	 */
	public function getOauth64(): string {
		return base64_encode( 'user=' . $this->mailbox . "\001auth=Bearer " . $this->token . "\001\001" ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- XOAUTH2 is base64 by protocol, not obfuscation
	}

	/**
	 * Point a mailer at Microsoft 365 with these credentials.
	 *
	 * @param \PHPMailer\PHPMailer\PHPMailer $mailer  Mailer.
	 * @param string                         $mailbox Mailbox the emails are sent as.
	 * @param string                         $token   Access token.
	 */
	public static function apply( \PHPMailer\PHPMailer\PHPMailer $mailer, string $mailbox, string $token ): void {
		$mailer->SMTPAuth = true;
		$mailer->AuthType = 'XOAUTH2';
		$mailer->Username = $mailbox;
		$mailer->Password = '';
		$mailer->setOAuth( new self( $mailbox, $token ) );
	}
}
