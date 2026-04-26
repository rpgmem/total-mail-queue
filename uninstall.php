<?php
/**
 * Uninstall handler invoked by WordPress when the plugin is deleted.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// The main plugin file isn't loaded by WP during uninstall, so the
// autoloader has to be registered here too.
require_once __DIR__ . '/autoload.php';

\TotalMailQueue\Lifecycle\Uninstaller::uninstall( __DIR__ . '/total-mail-queue.php' );
