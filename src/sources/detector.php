<?php
/**
 * Resolves a `source_key` for every email passing through `pre_wp_mail`.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Sources;

/**
 * Two-strategy detector that tells {@see \TotalMailQueue\Queue\MailInterceptor}
 * which "source" a given outgoing email belongs to:
 *
 * - **Primary (named filters).** A handful of WordPress core filters fire
 *   immediately before `wp_mail()` is called. We hook each one and stash a
 *   {@see Detector::setCurrent()} marker. When `pre_wp_mail` then runs, the
 *   interceptor calls {@see Detector::consume()} to read (and clear) it.
 * - **Fallback (`debug_backtrace`).** When no listener has fired, walk the
 *   call stack to find the caller's plugin / theme / core directory and map
 *   it to a `plugin:<slug>` / `theme:<slug>` / `wp_core:*` key.
 *
 * The static state cleared on consume is intentionally per-process. If a
 * listener fires but `wp_mail()` is then short-circuited by another filter
 * before `pre_wp_mail`, the marker would carry over to the next email — an
 * acceptable corner case in practice and documented here so it isn't lost.
 */
final class Detector {

	/**
	 * Last source set by a primary listener. Cleared by {@see consume()}.
	 *
	 * @var array{key:string,label:string,group:string}|null
	 */
	private static ?array $current = null;

	/**
	 * Per-call context captured by the primary listeners that have access
	 * to dynamic data WP doesn't expose post-hoc — user object, reset
	 * key, request id, etc. Cleared by {@see consumeData()} alongside
	 * the source marker. Used by the wp_core template-override pipeline
	 * (see {@see \TotalMailQueue\Sources\CoreTemplates}) to populate
	 * tokens like `{username}` and `{reset_url}`.
	 *
	 * @var array<string,string>
	 */
	private static array $current_data = array();

	/**
	 * Whether {@see register()} already ran. Guards against double-wiring
	 * when `Plugin::boot()` is called more than once (e.g. inside tests).
	 *
	 * @var bool
	 */
	private static bool $registered = false;

	/**
	 * Hook the primary listeners. Called from `Plugin::boot()`.
	 */
	public static function register(): void {
		if ( self::$registered ) {
			return;
		}
		self::$registered = true;

		// All listeners read only the first argument (the filter payload they
		// must return unchanged); accepted_args bumped per filter where the
		// extra arguments carry user / request context the wp_core template
		// override pipeline (CoreTemplates) needs to populate tokens.
		add_filter( 'retrieve_password_notification_email', array( self::class, 'markPasswordReset' ), 1, 4 );
		add_filter( 'wp_new_user_notification_email', array( self::class, 'markNewUser' ), 1, 3 );
		add_filter( 'wp_new_user_notification_email_admin', array( self::class, 'markNewUserAdmin' ), 1, 3 );
		add_filter( 'password_change_email', array( self::class, 'markPasswordChange' ), 1, 3 );
		add_filter( 'wp_password_change_notification_email', array( self::class, 'markPasswordChangeAdminNotify' ), 1, 3 );
		add_filter( 'email_change_email', array( self::class, 'markEmailChange' ), 1, 3 );
		add_filter( 'new_admin_email_content', array( self::class, 'markAdminEmailChangeConfirm' ), 1, 2 );
		add_filter( 'auto_core_update_email', array( self::class, 'markAutoUpdate' ), 1, 1 );
		add_filter( 'auto_plugin_theme_update_email', array( self::class, 'markAutoUpdatePluginsThemes' ), 1, 1 );
		add_filter( 'comment_notification_text', array( self::class, 'markCommentNotification' ), 1, 1 );
		add_filter( 'comment_moderation_text', array( self::class, 'markCommentModeration' ), 1, 1 );
		add_filter( 'user_confirmed_action_email_content', array( self::class, 'markUserActionConfirm' ), 1, 2 );
		add_filter( 'wp_privacy_personal_data_email_content', array( self::class, 'markPrivacyExportReady' ), 1, 3 );
		add_filter( 'user_erasure_complete_email_content', array( self::class, 'markPrivacyErasureDone' ), 1, 3 );
		add_filter( 'recovery_mode_email', array( self::class, 'markRecoveryMode' ), 1, 2 );
	}

	/**
	 * Set the marker for the next `wp_mail()` call.
	 *
	 * @param string $key   Canonical source key (e.g. `wp_core:password_reset`).
	 * @param string $label Human-readable label.
	 * @param string $group Group label used by the admin UI.
	 */
	public static function setCurrent( string $key, string $label, string $group ): void {
		self::$current = array(
			'key'   => $key,
			'label' => $label,
			'group' => $group,
		);
	}

	/**
	 * Read the current marker and clear it. Returns `null` when no listener
	 * fired since the last consumption.
	 *
	 * @return array{key:string,label:string,group:string}|null
	 */
	public static function consume(): ?array {
		$current       = self::$current;
		self::$current = null;
		return $current;
	}

	/**
	 * Stash per-call context for the wp_core template-override pipeline.
	 * Called by primary listeners that have access to dynamic data
	 * (user object, reset key, etc.) the {@see consume()} marker doesn't
	 * carry on its own.
	 *
	 * @param array<string,string> $data Token name → value map.
	 */
	public static function setData( array $data ): void {
		self::$current_data = $data;
	}

	/**
	 * Read the current context map and clear it. Returns an empty array
	 * when no listener stashed anything for this call.
	 *
	 * @return array<string,string>
	 */
	public static function consumeData(): array {
		$data               = self::$current_data;
		self::$current_data = array();
		return $data;
	}

	/**
	 * Drop any pending marker. Tests use this between cases; production code
	 * never needs to call it directly.
	 *
	 * @internal
	 */
	public static function reset(): void {
		self::$current      = null;
		self::$current_data = array();
		self::$registered   = false;
	}

	/**
	 * Walk `debug_backtrace()` to find the caller. Used by
	 * {@see \TotalMailQueue\Queue\MailInterceptor} when {@see consume()}
	 * returns null.
	 *
	 * @return array{key:string,label:string,group:string}
	 */
	public static function inferFromBacktrace(): array {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- intentional production use; only fires once per email when no primary listener tagged the source.
		$trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 25 );
		foreach ( $trace as $frame ) {
			$file = isset( $frame['file'] ) ? (string) $frame['file'] : '';
			if ( '' === $file ) {
				continue;
			}
			$source = self::classify( $file );
			if ( null !== $source ) {
				return $source;
			}
		}
		return array(
			'key'   => 'wp_core:unknown',
			'label' => 'Unknown caller',
			'group' => 'WordPress Core',
		);
	}

	/**
	 * Map a single backtrace frame's file path to a source descriptor (or
	 * null when the frame is internal to WordPress / our own code and should
	 * be skipped).
	 *
	 * @param string $file Absolute path of the frame being classified.
	 * @return array{key:string,label:string,group:string}|null
	 */
	private static function classify( string $file ): ?array {
		// Normalise slashes for cross-platform comparison.
		$normalised = str_replace( '\\', '/', $file );

		// Skip our own files — we don't classify ourselves as the source.
		if ( false !== strpos( $normalised, '/total-mail-queue/' ) ) {
			return null;
		}

		// Skip wp-includes (pluggable.php hosts wp_mail itself, plus the rest
		// of WP's mail plumbing). The interesting frame is upstream of that.
		if ( false !== strpos( $normalised, '/wp-includes/' ) ) {
			return null;
		}

		if ( preg_match( '#/wp-content/mu-plugins/([^/]+)#', $normalised, $matches ) ) {
			$slug = self::slugFromMatch( (string) $matches[1] );
			return array(
				'key'   => 'mu_plugin:' . $slug,
				'label' => 'Must-use plugin: ' . $slug,
				'group' => 'Plugins',
			);
		}

		if ( preg_match( '#/wp-content/plugins/([^/]+)/#', $normalised, $matches ) ) {
			$slug = (string) $matches[1];
			return array(
				'key'   => 'plugin:' . $slug,
				'label' => 'Plugin: ' . $slug,
				'group' => 'Plugins',
			);
		}

		if ( preg_match( '#/wp-content/themes/([^/]+)/#', $normalised, $matches ) ) {
			$slug = (string) $matches[1];
			return array(
				'key'   => 'theme:' . $slug,
				'label' => 'Theme: ' . $slug,
				'group' => 'Themes',
			);
		}

		if ( false !== strpos( $normalised, '/wp-admin/' ) ) {
			return array(
				'key'   => 'wp_core:admin',
				'label' => 'WordPress admin',
				'group' => 'WordPress Core',
			);
		}

		return null;
	}

	/**
	 * For mu-plugins the match group can be either a directory or a single
	 * file (e.g. `single-file.php`). Strip the `.php` suffix when present.
	 *
	 * @param string $match Captured group from the mu-plugin path regex.
	 */
	private static function slugFromMatch( string $match ): string {
		if ( substr( $match, -4 ) === '.php' ) {
			return substr( $match, 0, -4 );
		}
		return $match;
	}

	/*
	 * --------------------------------------------------------------
	 * Primary listeners. Each one is a thin pass-through that records
	 * the source and returns the filter argument unchanged.
	 * --------------------------------------------------------------
	 */

	/**
	 * Listener for `retrieve_password_notification_email`. Captures the
	 * dynamic context the wp_core override pipeline needs to populate
	 * `{username}`, `{user_email}`, `{reset_url}`, and `{requester_ip}`.
	 *
	 * @param mixed  $email      Filter payload.
	 * @param string $key        Reset key.
	 * @param string $user_login Account being reset.
	 * @param mixed  $user_data  WP_User instance, when supplied.
	 * @return mixed
	 */
	public static function markPasswordReset( $email, $key = '', $user_login = '', $user_data = null ) {
		self::setCurrent( 'wp_core:password_reset', 'Password reset', 'WordPress Core' );

		$reset_url = '';
		if ( '' !== (string) $key && '' !== (string) $user_login ) {
			$reset_url = network_site_url(
				'wp-login.php?action=rp&key=' . rawurlencode( (string) $key ) . '&login=' . rawurlencode( (string) $user_login )
			);
		}

		self::setData(
			array(
				'username'     => (string) $user_login,
				'user_email'   => is_object( $user_data ) && isset( $user_data->user_email ) ? (string) $user_data->user_email : '',
				'reset_url'    => $reset_url,
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below
				'requester_ip' => isset( $_SERVER['REMOTE_ADDR'] ) ? (string) sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
			)
		);

		return $email;
	}

	/**
	 * Listener for `wp_new_user_notification_email`.
	 *
	 * @param mixed $email    Filter payload.
	 * @param mixed $user     WP_User instance, when supplied.
	 * @param mixed $blogname Site name (unused).
	 * @return mixed
	 */
	public static function markNewUser( $email, $user = null, $blogname = '' ) {
		unset( $blogname );
		self::setCurrent( 'wp_core:new_user', 'New user — welcome', 'WordPress Core' );

		if ( is_object( $user ) ) {
			self::setData(
				array(
					'username'   => isset( $user->user_login ) ? (string) $user->user_login : '',
					'user_email' => isset( $user->user_email ) ? (string) $user->user_email : '',
					'login_url'  => wp_login_url(),
				)
			);
		}

		return $email;
	}

	/**
	 * Listener for `wp_new_user_notification_email_admin`.
	 *
	 * @param mixed $email    Filter payload.
	 * @param mixed $user     WP_User instance, when supplied.
	 * @param mixed $blogname Site name (unused).
	 * @return mixed
	 */
	public static function markNewUserAdmin( $email, $user = null, $blogname = '' ) {
		unset( $blogname );
		self::setCurrent( 'wp_core:new_user_admin', 'New user — admin notification', 'WordPress Core' );

		if ( is_object( $user ) ) {
			self::setData(
				array(
					'username'   => isset( $user->user_login ) ? (string) $user->user_login : '',
					'user_email' => isset( $user->user_email ) ? (string) $user->user_email : '',
				)
			);
		}

		return $email;
	}

	/**
	 * Listener for `password_change_email`.
	 *
	 * @param mixed $email    Filter payload.
	 * @param mixed $user     WP_User instance, when supplied.
	 * @param mixed $userdata Updated user fields (unused).
	 * @return mixed
	 */
	public static function markPasswordChange( $email, $user = null, $userdata = null ) {
		unset( $userdata );
		self::setCurrent( 'wp_core:password_change', 'Password changed', 'WordPress Core' );

		if ( is_object( $user ) ) {
			self::setData(
				array(
					'username'  => isset( $user->user_login ) ? (string) $user->user_login : '',
					'recipient' => isset( $user->user_email ) ? (string) $user->user_email : '',
				)
			);
		}

		return $email;
	}

	/**
	 * Listener for `email_change_email`.
	 *
	 * @param mixed $email    Filter payload.
	 * @param mixed $user     WP_User instance, when supplied.
	 * @param mixed $userdata Updated user fields containing the new email.
	 * @return mixed
	 */
	public static function markEmailChange( $email, $user = null, $userdata = null ) {
		self::setCurrent( 'wp_core:email_change', 'Email changed', 'WordPress Core' );

		$data = array();
		if ( is_object( $user ) ) {
			$data['username']  = isset( $user->user_login ) ? (string) $user->user_login : '';
			$data['recipient'] = isset( $user->user_email ) ? (string) $user->user_email : '';
		}
		if ( is_array( $userdata ) && isset( $userdata['user_email'] ) ) {
			$data['new_email'] = (string) $userdata['user_email'];
		} elseif ( is_object( $userdata ) && isset( $userdata->user_email ) ) {
			$data['new_email'] = (string) $userdata->user_email;
		}

		if ( ! empty( $data ) ) {
			self::setData( $data );
		}

		return $email;
	}

	/**
	 * Listener for `auto_core_update_email`.
	 *
	 * @param mixed $email Filter payload.
	 * @return mixed
	 */
	public static function markAutoUpdate( $email ) {
		self::setCurrent( 'wp_core:auto_update', 'Automatic core update', 'WordPress Core' );
		return $email;
	}

	/**
	 * Listener for `comment_notification_text`.
	 *
	 * @param mixed $text Filter payload.
	 * @return mixed
	 */
	public static function markCommentNotification( $text ) {
		self::setCurrent( 'wp_core:comment_notification', 'Comment notification', 'WordPress Core' );
		return $text;
	}

	/**
	 * Listener for `comment_moderation_text`.
	 *
	 * @param mixed $text Filter payload.
	 * @return mixed
	 */
	public static function markCommentModeration( $text ) {
		self::setCurrent( 'wp_core:comment_moderation', 'Comment moderation', 'WordPress Core' );
		return $text;
	}

	/**
	 * Listener for `wp_password_change_notification_email` (WP 5.3+).
	 * Sent to the site admin whenever ANY user resets their password.
	 *
	 * @param mixed $email    Filter payload.
	 * @param mixed $user     WP_User instance, when supplied (unused).
	 * @param mixed $blogname Site name (unused).
	 * @return mixed
	 */
	public static function markPasswordChangeAdminNotify( $email, $user = null, $blogname = '' ) {
		unset( $user, $blogname );
		self::setCurrent( 'wp_core:password_change_admin_notify', 'Password change — admin notification', 'WordPress Core' );
		return $email;
	}

	/**
	 * Listener for `new_admin_email_content` (WP 4.9+). Sent to the new
	 * site-admin address when the admin email is changed in Settings →
	 * General, asking the recipient to confirm.
	 *
	 * @param mixed $content         Filter payload.
	 * @param mixed $new_admin_email Pending admin email address (unused).
	 * @return mixed
	 */
	public static function markAdminEmailChangeConfirm( $content, $new_admin_email = '' ) {
		unset( $new_admin_email );
		self::setCurrent( 'wp_core:admin_email_change_confirm', 'Admin email change — confirmation', 'WordPress Core' );
		return $content;
	}

	/**
	 * Listener for `auto_plugin_theme_update_email` (WP 5.5+). Summary
	 * email after WP runs plugin/theme auto-updates.
	 *
	 * @param mixed $email Filter payload.
	 * @return mixed
	 */
	public static function markAutoUpdatePluginsThemes( $email ) {
		self::setCurrent( 'wp_core:auto_update_plugins_themes', 'Plugin/theme auto-update report', 'WordPress Core' );
		return $email;
	}

	/**
	 * Listener for `user_confirmed_action_email_content` (WP 4.9.6+).
	 * The "click to confirm" email sent before personal-data export or
	 * erasure requests are processed.
	 *
	 * @param mixed $content    Filter payload.
	 * @param mixed $email_data Companion data array (unused).
	 * @return mixed
	 */
	public static function markUserActionConfirm( $content, $email_data = null ) {
		unset( $email_data );
		self::setCurrent( 'wp_core:user_action_confirm', 'Personal data — request confirmation', 'WordPress Core' );
		return $content;
	}

	/**
	 * Listener for `wp_privacy_personal_data_email_content` (WP 4.9.6+).
	 * Notifies the user that their personal-data export is ready for
	 * download.
	 *
	 * @param mixed $content    Filter payload.
	 * @param mixed $request_id Privacy request post id (unused).
	 * @param mixed $email_data Companion data array (unused).
	 * @return mixed
	 */
	public static function markPrivacyExportReady( $content, $request_id = 0, $email_data = null ) {
		unset( $request_id, $email_data );
		self::setCurrent( 'wp_core:privacy_export_ready', 'Personal data — export ready', 'WordPress Core' );
		return $content;
	}

	/**
	 * Listener for `user_erasure_complete_email_content` (WP 5.1+).
	 * Notifies the user that their personal-data erasure request was
	 * fulfilled.
	 *
	 * @param mixed $content       Filter payload.
	 * @param mixed $email_address Recipient (unused).
	 * @param mixed $request_id    Privacy request post id (unused).
	 * @return mixed
	 */
	public static function markPrivacyErasureDone( $content, $email_address = '', $request_id = 0 ) {
		unset( $email_address, $request_id );
		self::setCurrent( 'wp_core:privacy_erasure_done', 'Personal data — erasure complete', 'WordPress Core' );
		return $content;
	}

	/**
	 * Listener for `recovery_mode_email` (WP 5.2+). Sent to the site
	 * admin when a fatal error triggers the recovery-mode link.
	 *
	 * @param mixed $email Filter payload.
	 * @param mixed $url   Recovery link URL (unused).
	 * @return mixed
	 */
	public static function markRecoveryMode( $email, $url = '' ) {
		unset( $url );
		self::setCurrent( 'wp_core:recovery_mode', 'Recovery mode', 'WordPress Core' );
		return $email;
	}
}
