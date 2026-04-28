<?php
/**
 * Database upgrade routine.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Database;

use TotalMailQueue\Plugin;
use TotalMailQueue\Sources\KnownSources;

/**
 * Detects schema bumps between the persisted plugin version and the running
 * code, and applies them through {@see Schema::install()}.
 *
 * Two callers:
 * - Activation hook → runs eagerly so a freshly activated plugin has its
 *   tables before anything else touches the DB.
 * - `plugins_loaded` action → runs on every request when a stored version
 *   is detected, catching upgrade scenarios where the plugin was updated
 *   via WP-Admin or via SFTP without an explicit "activate" click.
 */
final class Migrator {

	/**
	 * Option key under which the last-installed plugin version is persisted.
	 */
	public const VERSION_OPTION = 'wp_tmq_version';

	/**
	 * Apply the schema unconditionally, seed the known-sources catalog, and
	 * stamp the version.
	 *
	 * Used by the activation hook. Seeding happens after the schema install
	 * so the sources table is guaranteed to exist; it's idempotent so a
	 * version-bump-driven re-run is a no-op against rows the admin may
	 * have already toggled.
	 */
	public static function install(): void {
		Schema::install();
		KnownSources::seed();
		self::migrateSenderToSettings();
		update_option( self::VERSION_OPTION, Plugin::VERSION, true );
	}

	/**
	 * V2.6.0 migration: Sender override moved from `wp_tmq_template_options`
	 * (Templates tab) to `wp_tmq_settings` (Settings tab → Default Sender).
	 *
	 * Idempotent: only copies when the new location is empty AND the old
	 * location has a non-empty value. Existing settings entries (e.g.
	 * upgrade-then-edit) are never overwritten.
	 */
	private static function migrateSenderToSettings(): void {
		$old = get_option( 'wp_tmq_template_options' );
		if ( ! is_array( $old ) ) {
			return;
		}
		$old_email = isset( $old['from_email'] ) ? (string) $old['from_email'] : '';
		$old_name  = isset( $old['from_name'] ) ? (string) $old['from_name'] : '';
		if ( '' === $old_email && '' === $old_name ) {
			return;
		}

		$settings = get_option( 'wp_tmq_settings' );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		$changed = false;
		if ( '' !== $old_email && empty( $settings['from_email'] ) ) {
			$settings['from_email'] = $old_email;
			$changed                = true;
		}
		if ( '' !== $old_name && empty( $settings['from_name'] ) ) {
			$settings['from_name'] = $old_name;
			$changed               = true;
		}
		if ( $changed ) {
			update_option( 'wp_tmq_settings', $settings );
		}

		// Drop the legacy keys from template options so the source of truth
		// is unambiguously the Settings tab from now on.
		unset( $old['from_email'], $old['from_name'] );
		update_option( 'wp_tmq_template_options', $old );
	}

	/**
	 * Apply the schema only if the persisted version is different from the
	 * currently running code (i.e. an upgrade or a fresh install).
	 *
	 * Used by the `plugins_loaded` action.
	 */
	public static function maybeMigrate(): void {
		if ( get_option( self::VERSION_OPTION ) === Plugin::VERSION ) {
			return;
		}
		self::install();
	}
}
