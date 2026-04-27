<?php
/**
 * Plugin text domain loader.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Admin;

/**
 * Loads the plugin's translation files early enough that any `__()` /
 * `_e()` call later in the request hits a populated `$GLOBALS['l10n']`
 * entry instead of WordPress 6.7+'s just-in-time loader.
 *
 * Why `plugins_loaded` priority 0 (and not the conventional `init`):
 *
 * - WP 6.7 added a `_doing_it_wrong` notice in
 *   `_load_textdomain_just_in_time()` that fires when a translation is
 *   requested **before** the `init` action runs and the textdomain has
 *   not been loaded yet.
 * - Several hooks the plugin (and third-party callers exercising
 *   plugin code) listen to fire before `init` — `cron_schedules` /
 *   `pre_wp_mail` / `wp_mail_*` / `plugin_action_links_*`, plus
 *   activation hooks and `plugins_loaded` callbacks.
 * - Loading the textdomain on `plugins_loaded` priority 0 makes
 *   `$GLOBALS['l10n']['total-mail-queue']` available before any of
 *   our other registered callbacks run, so the just-in-time loader
 *   short-circuits without firing the notice.
 *
 * Self-hosted installs and custom language paths still need an explicit
 * call to `load_plugin_textdomain()` (WordPress.org distribution otherwise
 * auto-loads, but we keep this for parity with non-WP.org installs).
 */
final class TextDomain {

	/**
	 * Hook the loader. `plugins_loaded` priority 0 runs before any of the
	 * plugin's other callbacks on `plugins_loaded` (Migrator) and well
	 * before `init`, which is exactly when we want translations to be
	 * available.
	 */
	public static function register(): void {
		add_action( 'plugins_loaded', array( self::class, 'load' ), 0 );
	}

	/**
	 * Load the plugin text domain.
	 */
	public static function load(): void {
		// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- needed for self-hosted installs and custom language paths
		load_plugin_textdomain( 'total-mail-queue', false, dirname( plugin_basename( \TotalMailQueue\Plugin::container()->get( 'plugin.file' ) ) ) . '/languages' );
	}
}
