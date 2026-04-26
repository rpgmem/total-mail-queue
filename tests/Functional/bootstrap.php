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
 *
 * The `global` declarations are required because `require_once` here
 * runs inside the closure's scope, so the plugin's top-level
 * `$wp_tmq_options = wp_tmq_get_settings();` would otherwise create a
 * closure-local variable instead of the real global.
 */
tests_add_filter( 'muplugins_loaded', static function () use ( $project_root ) : void {
    global $wp_tmq_version, $wp_tmq_options, $wp_tmq_mailid, $wp_tmq_pre_wp_mail_priority, $wp_tmq_next_cron_timestamp, $wp_tmq_capturing_phpmailer;

    require_once $project_root . '/total-mail-queue.php';
    require_once $project_root . '/total-mail-queue-options.php';
    require_once $project_root . '/total-mail-queue-smtp.php';

    // Run the activation hook so the plugin's tables exist.
    \TotalMailQueue\Lifecycle\Activator::activate();
} );

require $tests_dir . '/includes/bootstrap.php';
