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

// Reuse the same PSR-4 autoloader the runtime bootstrap installs. The main
// plugin file isn't loaded by WP during uninstall, so register it here too.
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

\TotalMailQueue\Lifecycle\Uninstaller::uninstall( __DIR__ . '/total-mail-queue.php' );
