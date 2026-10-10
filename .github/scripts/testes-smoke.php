<?php
/**
 * Post-deploy smoke for the testes host.
 *
 * Runs ON the testes server, over SSH, right after `deploy-develop.yml` has
 * rsynced `develop`. It boots the real WordPress install and checks the three
 * things only the host can answer:
 *
 *   1. The plugin is active. Files landing in a directory WordPress does not
 *      load look healthy and do nothing, which is also what a
 *      TESTES_REMOTE_PATH pointing at the wrong folder looks like.
 *   2. The running Plugin::VERSION equals the commit being deployed. A deploy
 *      that silently did not land leaves the host on an old version while the
 *      workflow reports success.
 *   3. The plugin's three tables exist in the host's own database. The names
 *      come from `Schema`'s constants after boot, never from a copy here.
 *
 * It is an alarm, not a merge gate: the workflow runs after the merge.
 *
 * Usage: php testes-smoke.php <absolute-plugin-dir> <expected-version>
 * Exit codes: 0 = pass or deliberately skipped, 1 = a check failed.
 *
 * @package TotalMailQueue\CI
 */

declare(strict_types=1);

// The plugin's floor. A managed host may ship an older CLI binary than its web
// SAPI; that is not a plugin defect, so skip with an explanation.
const TMQ_SMOKE_MIN_PHP = '8.1';

/**
 * Print one check result.
 *
 * @param bool   $ok     Whether the check passed.
 * @param string $label  Short check name.
 * @param string $detail Context shown after the label.
 * @return bool The same $ok, for chaining into the failure flag.
 */
function tmq_smoke_check( bool $ok, string $label, string $detail = '' ): bool {
	printf( "  [%-4s] %-30s %s\n", $ok ? 'PASS' : 'FAIL', $label, $detail );
	return $ok;
}

/**
 * Locate wp-load.php by walking up from the plugin directory.
 *
 * @param string $plugin_dir Absolute plugin directory.
 * @return string|null Absolute path, or null when not found.
 */
function tmq_smoke_find_wp_load( string $plugin_dir ): ?string {
	$dir = $plugin_dir;
	for ( $i = 0; $i < 6; $i++ ) {
		$dir = dirname( $dir );
		if ( '/' === $dir || '' === $dir ) {
			break;
		}
		if ( is_readable( $dir . '/wp-load.php' ) ) {
			return $dir . '/wp-load.php';
		}
	}
	return null;
}

$plugin_dir = isset( $argv[1] ) ? rtrim( (string) $argv[1], '/' ) : '';
$expected   = isset( $argv[2] ) ? trim( (string) $argv[2] ) : '';

if ( '' === $plugin_dir || '' === $expected ) {
	fwrite( STDERR, "usage: testes-smoke.php <plugin-dir> <expected-version>\n" );
	exit( 1 );
}

echo "TMQ post-deploy smoke\n";
echo '  expected version: ' . $expected . "\n";
echo '  php (cli)       : ' . PHP_VERSION . "\n\n";

if ( version_compare( PHP_VERSION, TMQ_SMOKE_MIN_PHP, '<' ) ) {
	echo 'SKIPPED: the host PHP CLI is older than the plugin floor (' . TMQ_SMOKE_MIN_PHP . ").\n"
		. "Point the workflow at the host's newer binary to enable the smoke.\n";
	exit( 0 );
}

$main_file = $plugin_dir . '/total-mail-queue.php';
if ( ! tmq_smoke_check( is_readable( $main_file ), 'plugin file present', basename( $main_file ) ) ) {
	echo "\nAborting: the deploy did not land a usable plugin directory.\n";
	exit( 1 );
}

// The resolved path is not printed: it carries the site's hostname.
$wp_load = tmq_smoke_find_wp_load( $plugin_dir );
if ( ! tmq_smoke_check( null !== $wp_load, 'wp-load.php located', null !== $wp_load ? 'found' : 'not found' ) ) {
	echo "\nAborting: no WordPress install found above the plugin directory.\n";
	exit( 1 );
}

// WordPress reads these even on the CLI; unset, they flood the report.
$_SERVER['HTTP_HOST']      = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI']    = '/';
$_SERVER['SCRIPT_NAME']    = '/index.php';

// WordPress can stop the process during boot without an error status:
// wp_die() on a database it cannot reach prints its page and exits 0, which
// would read as a pass. So anything that ends the run before the verdict
// below is a failure.
$GLOBALS['tmq_smoke_verdict'] = false;
register_shutdown_function(
	static function (): void {
		if ( ! $GLOBALS['tmq_smoke_verdict'] ) {
			echo "\nSMOKE FAILED: WordPress stopped before the checks ran (see the output above).\n";
			exit( 1 );
		}
	}
);

// A fatal in the plugin surfaces here as PHP's own error.
require_once $wp_load;

echo "\n";
$ok = true;

// 1. Active, on the site or network-wide.
$slug      = basename( $plugin_dir ) . '/total-mail-queue.php';
$active    = (array) get_option( 'active_plugins', array() );
$network   = is_multisite() ? array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) : array();
$is_active = in_array( $slug, $active, true ) || in_array( $slug, $network, true );
$ok        = tmq_smoke_check( $is_active, 'plugin active', $slug ) && $ok;

if ( ! $is_active || ! class_exists( \TotalMailQueue\Plugin::class ) ) {
	$GLOBALS['tmq_smoke_verdict'] = true;
	echo "\nSMOKE FAILED: the deployed copy is not the one WordPress loads.\n"
		. "Check that TESTES_REMOTE_PATH names the active plugin's folder.\n";
	exit( 1 );
}

// 2. The running version is the deployed one.
$live = (string) \TotalMailQueue\Plugin::VERSION;
$ok   = tmq_smoke_check(
	$live === $expected,
	'live version matches deploy',
	$live === $expected ? $live : $live . ' on host, expected ' . $expected
) && $ok;

// 3. The tables exist.
global $wpdb;
$tables  = array(
	\TotalMailQueue\Database\Schema::QUEUE_TABLE,
	\TotalMailQueue\Database\Schema::SMTP_TABLE,
	\TotalMailQueue\Database\Schema::SOURCES_TABLE,
);
$missing = array();
foreach ( $tables as $table ) {
	$full  = $wpdb->prefix . $table;
	$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $full ) ) );
	if ( $found !== $full ) {
		$missing[] = $table;
	}
}
$ok = tmq_smoke_check(
	array() === $missing,
	'schema tables present',
	array() === $missing ? count( $tables ) . '/' . count( $tables ) : 'missing: ' . implode( ', ', $missing )
) && $ok;

$GLOBALS['tmq_smoke_verdict'] = true;
echo "\n" . ( $ok ? 'SMOKE PASSED' : 'SMOKE FAILED' ) . "\n";
exit( $ok ? 0 : 1 );
