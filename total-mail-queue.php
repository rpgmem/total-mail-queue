<?php
/**
 * Plugin Name:       Total Mail Queue
 * Plugin URI:        https://github.com/rpgmem/total-mail-queue
 * Description:       Take Control and improve Security of wp_mail(). Queue and log outgoing emails, and get alerted, if your website wants to send more emails than usual.
 * Version:           2.5.2
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

/*
 * Inline PSR-4-style autoloader for the plugin's TotalMailQueue\ namespace.
 *
 * Kept inline (rather than in a separate require'd file) so the plugin is
 * self-contained and survives partial deployments where only this file is
 * updated. The plugin has no third-party runtime dependencies; dev tooling
 * (PHPUnit, PHPCS, PHPStan, Brain Monkey) loads classes via Composer's
 * classmap from the test bootstraps.
 *
 * Path mapping:
 *   TotalMailQueue\Plugin                -> src/plugin.php
 *   TotalMailQueue\Admin\PluginPage      -> src/admin/plugin-page.php
 *   TotalMailQueue\Admin\Pages\SmtpPage  -> src/admin/pages/smtp-page.php
 */
spl_autoload_register(
	static function ( $class ) {
		$prefix = 'TotalMailQueue\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}
		$relative = substr( $class, strlen( $prefix ) );
		$parts    = explode( '\\', $relative );
		$basename = array_pop( $parts );
		$file     = strtolower( (string) preg_replace( '/(?<=[a-z0-9])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', '-', $basename ) );
		$dir      = $parts ? implode( '/', array_map( 'strtolower', $parts ) ) . '/' : '';
		$path     = __DIR__ . '/src/' . $dir . $file . '.php';
		if ( is_file( $path ) ) {
			require $path;
		}
	}
);

// All plugin behaviour — settings, support helpers, queue, cron, retention,
// SMTP, admin UI, REST, lifecycle — is owned by namespaced classes under
// TotalMailQueue\. Plugin::boot() wires every hook.
\TotalMailQueue\Plugin::boot( __FILE__ );
