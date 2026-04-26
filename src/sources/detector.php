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
		// must return unchanged); accepted_args = 1 is intentional even when
		// the underlying filter passes more context.
		add_filter( 'retrieve_password_notification_email', array( self::class, 'markPasswordReset' ), 1, 1 );
		add_filter( 'wp_new_user_notification_email', array( self::class, 'markNewUser' ), 1, 1 );
		add_filter( 'wp_new_user_notification_email_admin', array( self::class, 'markNewUserAdmin' ), 1, 1 );
		add_filter( 'password_change_email', array( self::class, 'markPasswordChange' ), 1, 1 );
		add_filter( 'email_change_email', array( self::class, 'markEmailChange' ), 1, 1 );
		add_filter( 'auto_core_update_email', array( self::class, 'markAutoUpdate' ), 1, 1 );
		add_filter( 'comment_notification_text', array( self::class, 'markCommentNotification' ), 1, 1 );
		add_filter( 'comment_moderation_text', array( self::class, 'markCommentModeration' ), 1, 1 );
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
	 * Drop any pending marker. Tests use this between cases; production code
	 * never needs to call it directly.
	 *
	 * @internal
	 */
	public static function reset(): void {
		self::$current    = null;
		self::$registered = false;
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
	 * Listener for `retrieve_password_notification_email`.
	 *
	 * @param mixed $email Filter payload.
	 * @return mixed
	 */
	public static function markPasswordReset( $email ) {
		self::setCurrent( 'wp_core:password_reset', 'Password reset', 'WordPress Core' );
		return $email;
	}

	/**
	 * Listener for `wp_new_user_notification_email`.
	 *
	 * @param mixed $email Filter payload.
	 * @return mixed
	 */
	public static function markNewUser( $email ) {
		self::setCurrent( 'wp_core:new_user', 'New user — welcome', 'WordPress Core' );
		return $email;
	}

	/**
	 * Listener for `wp_new_user_notification_email_admin`.
	 *
	 * @param mixed $email Filter payload.
	 * @return mixed
	 */
	public static function markNewUserAdmin( $email ) {
		self::setCurrent( 'wp_core:new_user_admin', 'New user — admin notification', 'WordPress Core' );
		return $email;
	}

	/**
	 * Listener for `password_change_email`.
	 *
	 * @param mixed $email Filter payload.
	 * @return mixed
	 */
	public static function markPasswordChange( $email ) {
		self::setCurrent( 'wp_core:password_change', 'Password changed', 'WordPress Core' );
		return $email;
	}

	/**
	 * Listener for `email_change_email`.
	 *
	 * @param mixed $email Filter payload.
	 * @return mixed
	 */
	public static function markEmailChange( $email ) {
		self::setCurrent( 'wp_core:email_change', 'Email changed', 'WordPress Core' );
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
}
