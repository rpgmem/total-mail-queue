<?php
/**
 * PHPUnit bootstrap for Total Mail Queue tests.
 *
 * Provides minimal WordPress stubs required to load the plugin's source files
 * so that pure PHP functions can be tested in isolation, without spinning up
 * a full WordPress environment.
 */

declare(strict_types=1);

// Composer autoloader (PHPUnit + test classes).
require_once __DIR__ . '/../vendor/autoload.php';

// Pretend we are inside WordPress so the plugin's ABSPATH guard passes.
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}
if ( ! defined( 'WPINC' ) ) {
    define( 'WPINC', 'wp-includes' );
}

require_once __DIR__ . '/Stubs/wordpress-stubs.php';

// Loading the plugin file at bootstrap time runs code in the global scope
// (registers hooks, schedules cron). With the stubs above all of those calls
// become no-ops, leaving the function definitions available for tests.
require_once __DIR__ . '/../total-mail-queue.php';
