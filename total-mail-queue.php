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
	exit; }

// Composer autoloader (provides the TotalMailQueue\* namespace + dev-mode test classes).
require_once __DIR__ . '/vendor/autoload.php';

\TotalMailQueue\Plugin::boot( __FILE__ );

/*
 ***************************************************************
PLUGIN VERSION
****************************************************************
*/

$wp_tmq_version = \TotalMailQueue\Plugin::VERSION;





// Settings, support helpers, queue/cron/retention services and SMTP services
// are all owned by namespaced classes under TotalMailQueue\, autoloaded via
// Composer. Plugin::boot() wires every hook (pre_wp_mail / wp_mail_failed /
// wp_mail_succeeded / cron_schedules / wp_tmq_mail_queue_hook).
//
// The legacy $wp_tmq_options global stays populated below so consumers in the
// procedural admin files (total-mail-queue-options.php / -smtp.php) keep
// reading the same array. It will be retired once N7 migrates those files.
$wp_tmq_options = \TotalMailQueue\Settings\Options::get();


/*
 ***************************************************************
Install/Uninstall/Upgrade
****************************************************************
*/

// Lifecycle is now driven by \TotalMailQueue\Plugin::boot():
// - Activation:   \TotalMailQueue\Lifecycle\Activator::activate (registered via register_activation_hook).
// - Deactivation: \TotalMailQueue\Lifecycle\Deactivator::deactivate (registered via register_deactivation_hook).
// - Uninstall:    \TotalMailQueue\Lifecycle\Uninstaller::uninstall (driven by uninstall.php at the plugin root).
// - Upgrades:     \TotalMailQueue\Database\Migrator::maybeMigrate on every plugins_loaded.

// Text domain loading is handled by \TotalMailQueue\Admin\TextDomain (registered
// in Plugin::boot()).

/*
 ***************************************************************
Options Page
****************************************************************
*/
if ( is_admin() ) {
	require_once plugin_dir_path( __FILE__ ) . 'total-mail-queue-options.php';
	require_once plugin_dir_path( __FILE__ ) . 'total-mail-queue-smtp.php';
}






// REST endpoints are registered by \TotalMailQueue\Rest\MessageController
// (wired in Plugin::boot()).
