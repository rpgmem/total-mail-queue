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

require_once __DIR__ . '/vendor/autoload.php';

\TotalMailQueue\Lifecycle\Uninstaller::uninstall( __DIR__ . '/total-mail-queue.php' );
