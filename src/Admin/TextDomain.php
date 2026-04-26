<?php
/**
 * Plugin text domain loader.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Admin;

/**
 * Loads the plugin's translation files on `init`.
 *
 * Self-hosted installs and custom language paths still need an explicit
 * call to `load_plugin_textdomain()` (WordPress.org distribution otherwise
 * auto-loads, but we keep this for parity with non-WP.org installs).
 */
final class TextDomain {

	/**
	 * Hook the loader onto the `init` action.
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'load' ) );
	}

	/**
	 * Load the plugin text domain.
	 */
	public static function load(): void {
		// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- needed for self-hosted installs and custom language paths
		load_plugin_textdomain( 'total-mail-queue', false, dirname( plugin_basename( \TotalMailQueue\Plugin::container()->get( 'plugin.file' ) ) ) . '/languages' );
	}
}
