<?php
/**
 * Bootstrap for functional tests that load a real WordPress.
 *
 * Expects:
 *   - vendor/roots/wordpress-no-content/ — WordPress core
 *   - vendor/wp-phpunit/wp-phpunit/      — WP test framework
 *   - wp-tests-config.php at the project root (created by bin/install-wp-tests.sh)
 *
 * The functional suite is intentionally separate from unit/integration
 * (see phpunit.functional.xml.dist) because WP loads many globals that
 * conflict with the lightweight stubs used by the other suites.
 */

declare(strict_types=1);

$project_root = dirname( __DIR__, 2 );

// Composer autoloader (PHPUnit, polyfills, etc.).
require_once $project_root . '/vendor/autoload.php';

$tests_dir  = $project_root . '/vendor/wp-phpunit/wp-phpunit';
$config     = $project_root . '/wp-tests-config.php';

if ( ! file_exists( $config ) ) {
    fwrite( STDERR, "wp-tests-config.php not found at $config — run bin/install-wp-tests.sh first.\n" );
    exit( 1 );
}

// wp-phpunit reads the path to wp-tests-config.php from this constant.
if ( ! defined( 'WP_TESTS_CONFIG_FILE_PATH' ) ) {
    define( 'WP_TESTS_CONFIG_FILE_PATH', $config );
}

// Required by wp-phpunit's bootstrap for plugin-mode loading.
require_once $tests_dir . '/includes/functions.php';

/**
 * Activate Total Mail Queue early so its hooks are registered before
 * WP fires any of its own.
 */
tests_add_filter( 'muplugins_loaded', static function () use ( $project_root ) : void {
    require_once $project_root . '/total-mail-queue.php';
    require_once $project_root . '/total-mail-queue-options.php';
    require_once $project_root . '/total-mail-queue-smtp.php';

    // Run the activation hook so the plugin's tables exist.
    if ( function_exists( 'wp_tmq_activate' ) ) {
        wp_tmq_activate();
    }
} );

require $tests_dir . '/includes/bootstrap.php';
