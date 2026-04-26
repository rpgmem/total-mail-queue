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

// PSR-4-style autoloader for the plugin's TotalMailQueue\ namespace. No
// third-party runtime dependencies, so vendor/autoload.php is purely a
// dev-time concern.
require_once __DIR__ . '/autoload.php';

// All plugin behaviour — settings, support helpers, queue, cron, retention,
// SMTP, admin UI, REST, lifecycle — is owned by namespaced classes under
// TotalMailQueue\. Plugin::boot() wires every hook.
\TotalMailQueue\Plugin::boot( __FILE__ );
