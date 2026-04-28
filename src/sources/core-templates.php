<?php
/**
 * Built-in catalog of WordPress core email templates.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Sources;

/**
 * Hardcoded inventory of the 11 WordPress core emails whose body / subject
 * can be overridden by the admin from the Sources tab.
 *
 * Baseline: WordPress 6.9.4 (verified against `wp-includes/pluggable.php`,
 * `wp-includes/user.php`, `wp-admin/includes/misc.php`,
 * `wp-includes/class-wp-recovery-mode-email-service.php`).
 *
 * **Maintenance contract.** The literal strings here mirror what WP ships
 * today. On every WP major release, re-verify against the upstream source
 * and bump strings if WP changed them. Block 10 of the 2.6.0 series adds a
 * CI WebFetch sanity test that fails when WP and our copy drift.
 *
 * **Out of scope (intentional cuts).** WP also generates emails for the
 * automatic-update routines and for comment notifications, but those bodies
 * are dynamically composed from many `sprintf` calls instead of a single
 * literal template — they cannot be hardcoded as a single editable string
 * without losing fidelity. They will keep going through WP core's own logic
 * and remain non-overridable. The 11 included here are the ones admins
 * actually want to brand.
 *
 * @see \TotalMailQueue\Sources\Detector
 * @see \TotalMailQueue\Sources\Repository
 */
final class CoreTemplates {

	/**
	 * Whether a `source_key` is one of the templates we ship overrides for.
	 *
	 * @param string $source_key Canonical source key.
	 * @return bool
	 */
	public static function isCoreTemplate( string $source_key ): bool {
		return array_key_exists( $source_key, self::all() );
	}

	/**
	 * Resolve the default WP-baseline tuple for a `source_key`. Returns
	 * `null` when the key is not a wp_core template we know about.
	 *
	 * @param string $source_key Canonical source key.
	 * @return array{subject:string,body:string,tokens:array<int,string>,label:string}|null
	 */
	public static function get( string $source_key ): ?array {
		$all = self::all();
		return $all[ $source_key ] ?? null;
	}

	/**
	 * The list of valid `{token}` names for a template — used by the admin
	 * UI to render a help row "Tokens: {site_title} {username} ...".
	 *
	 * @param string $source_key Canonical source key.
	 * @return array<int,string>
	 */
	public static function tokensFor( string $source_key ): array {
		$entry = self::get( $source_key );
		return null === $entry ? array() : $entry['tokens'];
	}

	/**
	 * Full catalog of supported wp_core templates.
	 *
	 * The bodies are intentionally concatenated across multiple lines for
	 * source-readability; `wp i18n make-pot` and Loco both fold these into
	 * a single translatable string. WPCS' i18n sniffer is stricter and
	 * rejects the pattern, so we silence it for the literal map.
	 *
	 * @return array<string,array{subject:string,body:string,tokens:array<int,string>,label:string}>
	 *
	 * phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralText
	 */
	public static function all(): array {
		return array(

			'wp_core:password_reset'               => array(
				'label'   => __( 'Password reset (user)', 'total-mail-queue' ),
				'subject' => __( '[{site_title}] Password Reset', 'total-mail-queue' ),
				'body'    => __(
					"Someone has requested a password reset for the following account:\n\n"
					. "Site Name: {site_title}\n"
					. "Username: {username}\n\n"
					. "If this was a mistake, ignore this email and nothing will happen.\n\n"
					. "To reset your password, visit the following address:\n\n"
					. "{reset_url}\n\n"
					. 'This password reset request originated from the IP address {requester_ip}.',
					'total-mail-queue'
				),
				'tokens'  => array( 'site_title', 'site_url', 'username', 'user_email', 'reset_url', 'requester_ip' ),
			),

			'wp_core:new_user'                     => array(
				'label'   => __( 'New user — welcome', 'total-mail-queue' ),
				'subject' => __( '[{site_title}] Login Details', 'total-mail-queue' ),
				'body'    => __(
					"Username: {username}\n\n"
					. "To set your password, visit the following address:\n\n"
					. "{set_password_url}\n\n"
					. '{login_url}',
					'total-mail-queue'
				),
				'tokens'  => array( 'site_title', 'site_url', 'username', 'user_email', 'set_password_url', 'login_url' ),
			),

			'wp_core:new_user_admin'               => array(
				'label'   => __( 'New user — admin notification', 'total-mail-queue' ),
				'subject' => __( '[{site_title}] New User Registration', 'total-mail-queue' ),
				'body'    => __(
					"New user registration on your site {site_title}:\n\n"
					. "Username: {username}\n\n"
					. 'Email: {user_email}',
					'total-mail-queue'
				),
				'tokens'  => array( 'site_title', 'site_url', 'username', 'user_email' ),
			),

			'wp_core:password_change'              => array(
				'label'   => __( 'Password changed — user notification', 'total-mail-queue' ),
				'subject' => __( '[{site_title}] Password Changed', 'total-mail-queue' ),
				'body'    => __(
					"Hi {username},\n\n"
					. "This notice confirms that your password was changed on {site_title}.\n\n"
					. "If you did not change your password, please contact the Site Administrator at\n"
					. "{admin_email}\n\n"
					. "This email has been sent to {recipient}\n\n"
					. "Regards,\n"
					. "All at {site_title}\n"
					. '{site_url}',
					'total-mail-queue'
				),
				'tokens'  => array( 'site_title', 'site_url', 'username', 'admin_email', 'recipient' ),
			),

			'wp_core:password_change_admin_notify' => array(
				'label'   => __( 'Password changed — admin notification', 'total-mail-queue' ),
				'subject' => __( '[{site_title}] Password Changed', 'total-mail-queue' ),
				'body'    => __(
					'Password changed for user: {username}',
					'total-mail-queue'
				),
				'tokens'  => array( 'site_title', 'username' ),
			),

			'wp_core:email_change'                 => array(
				'label'   => __( 'Email changed — user notification', 'total-mail-queue' ),
				'subject' => __( '[{site_title}] Email Changed', 'total-mail-queue' ),
				'body'    => __(
					"Hi {username},\n\n"
					. "This notice confirms that your email address on {site_title} was changed to {new_email}.\n\n"
					. "If you did not change your email, please contact the Site Administrator at\n"
					. "{admin_email}\n\n"
					. "This email has been sent to {recipient}\n\n"
					. "Regards,\n"
					. "All at {site_title}\n"
					. '{site_url}',
					'total-mail-queue'
				),
				'tokens'  => array( 'site_title', 'site_url', 'username', 'admin_email', 'new_email', 'recipient' ),
			),

			'wp_core:admin_email_change_confirm'   => array(
				'label'   => __( 'Admin email change — confirmation', 'total-mail-queue' ),
				'subject' => __( '[{site_title}] New Admin Email Address', 'total-mail-queue' ),
				'body'    => __(
					"Howdy,\n\n"
					. "A site administrator ({username}) recently requested to have the\n"
					. "administration email address changed on this site:\n"
					. "{site_url}\n\n"
					. "To confirm this change, please click on the following link:\n"
					. "{admin_url}\n\n"
					. "You can safely ignore and delete this email if you do not want to\n"
					. "take this action.\n\n"
					. "This email has been sent to {recipient}\n\n"
					. "Regards,\n"
					. "All at {site_title}\n"
					. '{site_url}',
					'total-mail-queue'
				),
				'tokens'  => array( 'site_title', 'site_url', 'username', 'admin_url', 'recipient' ),
			),

			'wp_core:user_action_confirm'          => array(
				'label'   => __( 'Personal data — request confirmation', 'total-mail-queue' ),
				'subject' => __( '[{site_title}] Confirm Action: {description}', 'total-mail-queue' ),
				'body'    => __(
					"Howdy,\n\n"
					. "A request has been made to perform the following action on your account:\n\n"
					. "     {description}\n\n"
					. "To confirm this, please click on the following link:\n"
					. "{confirm_url}\n\n"
					. "You can safely ignore and delete this email if you do not want to\n"
					. "take this action.\n\n"
					. "Regards,\n"
					. "All at {site_title}\n"
					. '{site_url}',
					'total-mail-queue'
				),
				'tokens'  => array( 'site_title', 'site_url', 'description', 'confirm_url' ),
			),

			'wp_core:privacy_export_ready'         => array(
				'label'   => __( 'Personal data — export ready', 'total-mail-queue' ),
				'subject' => __( '[{site_title}] Personal Data Export', 'total-mail-queue' ),
				'body'    => __(
					"Howdy,\n\n"
					. "Your request for an export of personal data has been completed. You may\n"
					. "download your personal data by clicking on the link below. For privacy\n"
					. "and security, we will automatically delete the file on {expiration}, so\n"
					. "please download it before then.\n\n"
					. "{export_url}\n\n"
					. "Regards,\n"
					. "All at {site_title}\n"
					. '{site_url}',
					'total-mail-queue'
				),
				'tokens'  => array( 'site_title', 'site_url', 'export_url', 'expiration', 'privacy_policy_url' ),
			),

			'wp_core:privacy_erasure_done'         => array(
				'label'   => __( 'Personal data — erasure complete', 'total-mail-queue' ),
				'subject' => __( '[{site_title}] Erasure Request Fulfilled', 'total-mail-queue' ),
				'body'    => __(
					"Howdy,\n\n"
					. "Your request to erase your personal data on {site_title} has been completed.\n\n"
					. "If you have any follow-up questions or concerns, please contact the site administrator.\n\n"
					. "Regards,\n"
					. "All at {site_title}\n"
					. '{site_url}',
					'total-mail-queue'
				),
				'tokens'  => array( 'site_title', 'site_url', 'privacy_policy_url' ),
			),

			'wp_core:recovery_mode'                => array(
				'label'   => __( 'Recovery mode', 'total-mail-queue' ),
				'subject' => __( '[{site_title}] Your Site is Experiencing a Technical Issue', 'total-mail-queue' ),
				'body'    => __(
					"Howdy!\n\n"
					. "WordPress has a built-in feature that detects when a plugin or theme causes a fatal error on your site, and notifies you with this automated email.\n\n"
					. "{cause}\n"
					. "First, visit your website ({site_url}) and check for any visible issues. Next, visit the page where the error was caught ({pageurl}) and check for any visible issues.\n\n"
					. "If your site appears broken and you can't access your dashboard normally, WordPress now has a special \"recovery mode\". This lets you safely login to your dashboard and investigate further.\n\n"
					. "{recovery_url}\n\n"
					. "To keep your site safe, this link will expire in {expires_time}. Don't worry about that, though: a new link will be emailed to you if the error occurs again after it expires.\n\n"
					. '{details}',
					'total-mail-queue'
				),
				'tokens'  => array( 'site_title', 'site_url', 'recovery_url', 'expires_time', 'cause', 'details', 'pageurl' ),
			),

		);
		// phpcs:enable WordPress.WP.I18n.NonSingularStringLiteralText
	}
}
