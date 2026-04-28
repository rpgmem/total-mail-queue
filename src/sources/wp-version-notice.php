<?php
/**
 * Admin notice surfaced when the running WordPress major version
 * changes between requests.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Sources;

/**
 * Tracks the WordPress version the plugin most recently observed, and
 * shows an admin notice when WP is upgraded so the admin can re-verify
 * the wp_core template baseline (see {@see CoreTemplates}).
 *
 * The hardcoded baseline in {@see CoreTemplates} is locked to the WP
 * version it was authored against. When WP ships a major release that
 * tweaks an email template, the override the admin saved continues to
 * use OUR cached baseline as its "WP default" reference — which can
 * drift from what WP itself now sends. This module nudges the admin
 * to review.
 *
 * Storage: `wp_tmq_wp_version_seen` in `wp_options`. Updated when:
 *   - the admin clicks "Dismiss" on the notice
 *   - the admin opens the Sources tab (passive ack)
 */
final class WpVersionNotice {

	/**
	 * Option key under which we remember the last WP version we surfaced.
	 */
	public const OPTION_NAME = 'wp_tmq_wp_version_seen';

	/**
	 * Hook the notice on `admin_notices` and the dismiss handler on
	 * `admin_init` (so it runs before any output for redirect-friendly
	 * dismissal).
	 */
	public static function register(): void {
		add_action( 'admin_init', array( self::class, 'maybeDismiss' ) );
		add_action( 'admin_notices', array( self::class, 'render' ) );
	}

	/**
	 * Render the notice when the running WP version differs from the
	 * one we last saw and the admin can act on it.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$current = self::currentVersion();
		$seen    = (string) get_option( self::OPTION_NAME, '' );
		if ( '' === $seen ) {
			// First-run: silently stamp the current version. No notice on
			// fresh installs — only when the version observed across
			// requests actually changes.
			update_option( self::OPTION_NAME, $current, true );
			return;
		}
		if ( $seen === $current ) {
			return;
		}

		$dismiss_url = wp_nonce_url(
			add_query_arg( 'wp_tmq_wp_version_dismiss', '1' ),
			'wp_tmq_wp_version_dismiss'
		);
		$sources_url = admin_url( 'admin.php?page=wp_tmq_mail_queue-tab-sources' );

		echo '<div class="notice notice-info is-dismissible"><p>';
		echo wp_kses_post(
			sprintf(
				/* translators: 1: previous WP version, 2: current WP version, 3: opening Sources link, 4: closing link */
				__( '<strong>Total Mail Queue:</strong> WordPress was updated from %1$s to %2$s. The default templates for some core emails may have changed. %3$sReview the wp_core templates%4$s and re-verify your overrides if needed.', 'total-mail-queue' ),
				esc_html( $seen ),
				esc_html( $current ),
				'<a href="' . esc_url( $sources_url ) . '">',
				'</a>'
			)
		);
		echo ' <a href="' . esc_url( $dismiss_url ) . '" class="button button-small" style="margin-left:8px;">' . esc_html__( 'Dismiss', 'total-mail-queue' ) . '</a>';
		echo '</p></div>';
	}

	/**
	 * `admin_init` callback. Acks the notice when the admin clicks
	 * Dismiss or visits the Sources tab.
	 */
	public static function maybeDismiss(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified just below.
		if ( isset( $_GET['wp_tmq_wp_version_dismiss'] ) ) {
			if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'wp_tmq_wp_version_dismiss' ) ) {
				return;
			}
			update_option( self::OPTION_NAME, self::currentVersion(), true );
			wp_safe_redirect( remove_query_arg( array( 'wp_tmq_wp_version_dismiss', '_wpnonce' ) ) );
			exit;
		}

		// Passive ack: opening the Sources tab counts as "I've reviewed".
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only ack.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'wp_tmq_mail_queue-tab-sources' === $page ) {
			update_option( self::OPTION_NAME, self::currentVersion(), true );
		}
	}

	/**
	 * Current WordPress version. Falls back to the global on older WP.
	 */
	private static function currentVersion(): string {
		// `get_bloginfo('version')` is the documented public API; the
		// `$wp_version` global is the storage. Both are equivalent.
		return (string) get_bloginfo( 'version' );
	}
}
