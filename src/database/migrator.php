<?php
/**
 * Database upgrade routine.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Database;

use TotalMailQueue\Plugin;

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
	 * Apply the schema unconditionally and stamp the version.
	 *
	 * Used by the activation hook.
	 */
	public static function install(): void {
		Schema::install();
		update_option( self::VERSION_OPTION, Plugin::VERSION, true );
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
