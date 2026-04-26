<?php
/**
 * Pre-seeded catalog entries for popular plugins + plugin-internal sources.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Sources;

use TotalMailQueue\Cron\AlertSender;

/**
 * Seeds the sources catalog and resolves translated labels at display time.
 *
 * Two responsibilities:
 *
 * 1. **Pre-seeding** ({@see seed()}). Runs on activation and every version
 *    bump. Inserts a known set of `(source_key, label, group_label)` tuples
 *    into the catalog so the admin sees them in the Sources tab from day
 *    one — no need to wait for the first email of each kind to fire.
 *    Idempotent: existing rows (including any admin-toggled `enabled` value
 *    or label override) are left untouched.
 *
 * 2. **Translatable display labels** ({@see translatedLabel()},
 *    {@see translatedGroup()}). The `label` / `group_label` columns store
 *    the canonical English string, but the admin UI prefers a translated
 *    string at display time. The lookup methods below pair every known
 *    `source_key` (or group prefix) with a literal `__()` call so Loco
 *    Translate (or any `.po` scanner) picks them up — without forcing the
 *    locale of the install request to match the active admin locale.
 */
final class KnownSources {

	/**
	 * The catalog rows to seed. Each tuple is `[source_key, label, group]`.
	 *
	 * @return array<int,array{0:string,1:string,2:string}>
	 */
	public static function entries(): array {
		return array(
			// Plugin-internal source — appears in the catalog from day one
			// so the admin sees it (rendered as a non-toggleable system row
			// in the Sources tab).
			array( AlertSender::SOURCE_KEY, 'Total Mail Queue alert', 'Total Mail Queue' ),

			// WordPress core emails — every default outgoing email a stock
			// single-site WordPress install can produce. Each entry matches
			// a primary listener in Sources\Detector. Pre-seeding these
			// means the admin can disable any of them BEFORE the first
			// send (especially useful for things like the new-user welcome
			// email that you may want off from day one).
			array( 'wp_core:password_reset', 'Password reset', 'WordPress Core' ),
			array( 'wp_core:new_user', 'New user — welcome', 'WordPress Core' ),
			array( 'wp_core:new_user_admin', 'New user — admin notification', 'WordPress Core' ),
			array( 'wp_core:password_change', 'Password changed — user notification', 'WordPress Core' ),
			array( 'wp_core:password_change_admin_notify', 'Password change — admin notification', 'WordPress Core' ),
			array( 'wp_core:email_change', 'Email changed — user notification', 'WordPress Core' ),
			array( 'wp_core:admin_email_change_confirm', 'Admin email change — confirmation', 'WordPress Core' ),
			array( 'wp_core:auto_update', 'Automatic core update', 'WordPress Core' ),
			array( 'wp_core:auto_update_plugins_themes', 'Plugin/theme auto-update report', 'WordPress Core' ),
			array( 'wp_core:comment_notification', 'Comment notification', 'WordPress Core' ),
			array( 'wp_core:comment_moderation', 'Comment moderation', 'WordPress Core' ),
			array( 'wp_core:user_action_confirm', 'Personal data — request confirmation', 'WordPress Core' ),
			array( 'wp_core:privacy_export_ready', 'Personal data — export ready', 'WordPress Core' ),
			array( 'wp_core:privacy_erasure_done', 'Personal data — erasure complete', 'WordPress Core' ),
			array( 'wp_core:recovery_mode', 'Recovery mode', 'WordPress Core' ),

			// Popular third-party plugins. Backtrace detection would also
			// produce these keys the first time each plugin sends, but
			// pre-seeding lets the admin disable them up front.
			array( 'plugin:woocommerce', 'WooCommerce', 'Plugins' ),
			array( 'plugin:contact-form-7', 'Contact Form 7', 'Plugins' ),
			array( 'plugin:wpforms', 'WPForms', 'Plugins' ),
			array( 'plugin:wpforms-lite', 'WPForms Lite', 'Plugins' ),
		);
	}

	/**
	 * Insert the seed entries into the catalog. Idempotent: existing rows
	 * are left untouched (keeps any admin-toggled `enabled` value or label
	 * override).
	 */
	public static function seed(): void {
		foreach ( self::entries() as $entry ) {
			Repository::register( $entry[0], $entry[1], $entry[2] );
		}
	}

	/**
	 * Translated display label for a known `source_key`, or null when the
	 * key isn't part of the seeded catalog (e.g. a plugin auto-registered
	 * via the backtrace fallback).
	 *
	 * Each branch uses a literal `__()` call so `.pot` scanners pick it up.
	 *
	 * @param string $source_key Canonical key.
	 */
	public static function translatedLabel( string $source_key ): ?string {
		switch ( $source_key ) {
			case AlertSender::SOURCE_KEY:
				return __( 'Total Mail Queue alert', 'total-mail-queue' );
			case 'wp_core:password_reset':
				return __( 'Password reset', 'total-mail-queue' );
			case 'wp_core:new_user':
				return __( 'New user — welcome', 'total-mail-queue' );
			case 'wp_core:new_user_admin':
				return __( 'New user — admin notification', 'total-mail-queue' );
			case 'wp_core:password_change':
				return __( 'Password changed — user notification', 'total-mail-queue' );
			case 'wp_core:password_change_admin_notify':
				return __( 'Password change — admin notification', 'total-mail-queue' );
			case 'wp_core:email_change':
				return __( 'Email changed — user notification', 'total-mail-queue' );
			case 'wp_core:admin_email_change_confirm':
				return __( 'Admin email change — confirmation', 'total-mail-queue' );
			case 'wp_core:auto_update':
				return __( 'Automatic core update', 'total-mail-queue' );
			case 'wp_core:auto_update_plugins_themes':
				return __( 'Plugin/theme auto-update report', 'total-mail-queue' );
			case 'wp_core:comment_notification':
				return __( 'Comment notification', 'total-mail-queue' );
			case 'wp_core:comment_moderation':
				return __( 'Comment moderation', 'total-mail-queue' );
			case 'wp_core:user_action_confirm':
				return __( 'Personal data — request confirmation', 'total-mail-queue' );
			case 'wp_core:privacy_export_ready':
				return __( 'Personal data — export ready', 'total-mail-queue' );
			case 'wp_core:privacy_erasure_done':
				return __( 'Personal data — erasure complete', 'total-mail-queue' );
			case 'wp_core:recovery_mode':
				return __( 'Recovery mode', 'total-mail-queue' );
			case 'plugin:woocommerce':
				return __( 'WooCommerce', 'total-mail-queue' );
			case 'plugin:contact-form-7':
				return __( 'Contact Form 7', 'total-mail-queue' );
			case 'plugin:wpforms':
				return __( 'WPForms', 'total-mail-queue' );
			case 'plugin:wpforms-lite':
				return __( 'WPForms Lite', 'total-mail-queue' );
		}
		return null;
	}

	/**
	 * Translated group label for a `source_key`. Resolved by prefix so even
	 * unseeded keys (e.g. a `plugin:akismet` discovered via backtrace) get
	 * a translated group label like "Plugins".
	 *
	 * @param string $source_key Canonical key.
	 */
	public static function translatedGroup( string $source_key ): ?string {
		if ( AlertSender::SOURCE_KEY === $source_key ) {
			return __( 'Total Mail Queue', 'total-mail-queue' );
		}
		if ( 0 === strpos( $source_key, 'wp_core:' ) ) {
			return __( 'WordPress Core', 'total-mail-queue' );
		}
		if ( 0 === strpos( $source_key, 'plugin:' ) || 0 === strpos( $source_key, 'mu_plugin:' ) ) {
			return __( 'Plugins', 'total-mail-queue' );
		}
		if ( 0 === strpos( $source_key, 'theme:' ) ) {
			return __( 'Themes', 'total-mail-queue' );
		}
		return null;
	}

	/**
	 * Translate a raw group label (`group_label` column value) to its
	 * localised form. Lets the group-filter dropdown render translated
	 * option text while keeping the option value canonical so filtering
	 * + group-bulk SQL queries match the stored row.
	 *
	 * @param string $group Raw group label as stored in the DB.
	 */
	public static function translateRawGroup( string $group ): string {
		switch ( $group ) {
			case 'Total Mail Queue':
				return __( 'Total Mail Queue', 'total-mail-queue' );
			case 'WordPress Core':
				return __( 'WordPress Core', 'total-mail-queue' );
			case 'Plugins':
				return __( 'Plugins', 'total-mail-queue' );
			case 'Themes':
				return __( 'Themes', 'total-mail-queue' );
		}
		return $group;
	}

	/**
	 * The display label the admin sees, with the priority order:
	 * 1. `label_override` (admin-set), if non-empty.
	 * 2. {@see translatedLabel()} of the source_key, if known.
	 * 3. The raw stored `label`, if any.
	 * 4. The `source_key` itself as a last-resort fallback.
	 *
	 * @param array<string,mixed> $row Row from the sources table.
	 */
	public static function displayLabel( array $row ): string {
		$override = isset( $row['label_override'] ) ? (string) $row['label_override'] : '';
		if ( '' !== $override ) {
			return $override;
		}
		$translated = self::translatedLabel( (string) ( $row['source_key'] ?? '' ) );
		if ( null !== $translated ) {
			return $translated;
		}
		$stored = isset( $row['label'] ) ? (string) $row['label'] : '';
		if ( '' !== $stored ) {
			return $stored;
		}
		return (string) ( $row['source_key'] ?? '' );
	}

	/**
	 * The display group the admin sees. Same priority order as
	 * {@see displayLabel()}.
	 *
	 * @param array<string,mixed> $row Row from the sources table.
	 */
	public static function displayGroup( array $row ): string {
		$override = isset( $row['group_override'] ) ? (string) $row['group_override'] : '';
		if ( '' !== $override ) {
			return $override;
		}
		$translated = self::translatedGroup( (string) ( $row['source_key'] ?? '' ) );
		if ( null !== $translated ) {
			return $translated;
		}
		return isset( $row['group_label'] ) ? (string) $row['group_label'] : '';
	}
}
