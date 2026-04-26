<?php
/**
 * PHPUnit bootstrap for Total Mail Queue tests.
 *
 * Provides minimal WordPress stubs required to load the plugin's source files
 * so that pure PHP functions can be tested in isolation, without spinning up
 * a full WordPress environment.
 */

declare(strict_types=1);

// Patchwork must load before any function definitions it might need to
// redefine (Brain Monkey relies on it). It also has to come before the
// Composer autoloader, otherwise autoload.files entries (Mockery, Brain
// Monkey API, etc.) define functions before Patchwork can instrument them.
require_once __DIR__ . '/../vendor/antecedent/patchwork/Patchwork.php';

// Composer autoloader (PHPUnit + test classes + Brain Monkey).
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
// become no-ops, leaving the namespaced classes loaded for tests.
require_once __DIR__ . '/../total-mail-queue.php';
