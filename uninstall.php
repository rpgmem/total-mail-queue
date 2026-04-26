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
// autoloader has to be registered here too. Kept inline (mirroring
// total-mail-queue.php) so uninstall is robust against partial deployments.
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

\TotalMailQueue\Lifecycle\Uninstaller::uninstall( __DIR__ . '/total-mail-queue.php' );
