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

// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- needed for self-hosted installs and custom language paths
/**
 * Load textdomain.
 *
 * @since 2.3.0
 *
 * @return void
 */
function wp_tmq_load_textdomain() {
	load_plugin_textdomain( 'total-mail-queue', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'wp_tmq_load_textdomain' );




/*
 ***************************************************************
Options Page
****************************************************************
*/
if ( is_admin() ) {
	require_once plugin_dir_path( __FILE__ ) . 'total-mail-queue-options.php';
	require_once plugin_dir_path( __FILE__ ) . 'total-mail-queue-smtp.php';
}






/**
 * **************************************************************
REST API
 * ***************************************************************
 */
function wp_tmq_add_rest_endpoints() {
	register_rest_route(
		'tmq/v1',
		'/message/(?P<id>[\d]+)',
		array(
			'methods'             => 'GET',
			'callback'            => 'wp_tmq_rest_get_message',
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		)
	);
}
add_action( 'rest_api_init', 'wp_tmq_add_rest_endpoints', 10, 0 );
/**
 * Rest get message.
 *
 * @since 2.3.0
 *
 * @param mixed $request Parameter description.
 *
 * @return mixed Function output.
 */
function wp_tmq_rest_get_message( $request ) {
	global $wpdb, $wp_tmq_options;
	$table_name = $wpdb->prefix . $wp_tmq_options['tableName'];
	$id         = intval( $request['id'] );
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `$table_name` WHERE `id` = %d", $id ), ARRAY_A );
	if ( $row ) {
		// Search for content-type header to detect html emails.
		$is_content_type_html = false;
		$headers              = \TotalMailQueue\Support\Serializer::decode( $row['headers'] );
		if ( is_string( $headers ) ) {
			$headers = array( $headers );
		} elseif ( ! is_array( $headers ) ) {
			$headers = array();
		}
		foreach ( $headers as $header ) {
			if ( preg_match( '/content-type: ?text\/html/i', $header ) ) {
				$is_content_type_html = true;
				break;
			}
		}
		return array(
			'status' => 'ok',
			'data'   => array(
				'html' => \TotalMailQueue\Support\HtmlPreview::renderListMessage( \TotalMailQueue\Support\Serializer::decode( $row['message'] ), $is_content_type_html ),
			),
		);
	} else {
		return new WP_Error( 'no_message', __( 'Message not found', 'total-mail-queue' ), array( 'status' => 404 ) );
	}
}
// HTML preview helpers moved to \TotalMailQueue\Support\HtmlPreview.
