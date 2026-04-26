<?php
/**
 * Plugin Name:       Total Mail Queue
 * Plugin URI:        https://github.com/rpgmem/total-mail-queue
 * Description:       Take Control and improve Security of wp_mail(). Queue and log outgoing emails, and get alerted, if your website wants to send more emails than usual.
 * Version:           2.3.0
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Alex Meusburger
 * Author URI:        https://github.com/rpgmem
 * License:           GPLv3 or later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       total-mail-queue
 *
 * This plugin is a fork of Mail Queue by WDM (https://www.webdesign-muenchen.de).
 * Original plugin: https://wordpress.org/plugins/mail-queue/
 *
 * @package TotalMailQueue
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// PSR-4 autoloader for the plugin's own TotalMailQueue\ namespace.
// The plugin has no third-party runtime dependencies, so it doesn't ship
// (or rely on) a Composer-generated vendor/. Dev tooling (PHPUnit, PHPCS,
// PHPStan, Brain Monkey) still uses vendor/autoload.php from the test
// bootstraps; production loads only this inline autoloader.
spl_autoload_register(
	static function ( $class ) {
		$prefix = 'TotalMailQueue\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}
		$relative = substr( $class, strlen( $prefix ) );
		$path     = __DIR__ . '/src/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_file( $path ) ) {
			require $path;
		}
	}
);

// All plugin behaviour — settings, support helpers, queue, cron, retention,
// SMTP, admin UI, REST, lifecycle — is owned by namespaced classes under
// TotalMailQueue\. Plugin::boot() wires every hook.
\TotalMailQueue\Plugin::boot( __FILE__ );
