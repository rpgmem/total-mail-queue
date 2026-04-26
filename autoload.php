<?php
/**
 * PSR-4-style autoloader for the plugin's TotalMailQueue\ namespace.
 *
 * The plugin has no third-party runtime dependencies (composer.json's
 * "require" lists only "php"), so we don't ship — or rely on — Composer's
 * vendor/autoload.php at runtime. Dev tooling (PHPUnit, PHPCS, PHPStan,
 * Brain Monkey) still uses Composer's autoloader from the test bootstraps,
 * and Composer is configured to require this file via "autoload.files" so
 * the same lookup works for tests too.
 *
 * Path mapping (Linux-friendly, case-coherent):
 *
 *   TotalMailQueue\Plugin                -> src/plugin.php
 *   TotalMailQueue\Admin\PluginPage      -> src/admin/plugin-page.php
 *   TotalMailQueue\Admin\Pages\SmtpPage  -> src/admin/pages/smtp-page.php
 *
 * Subnamespaces become lowercase directories; the trailing class name is
 * converted from PascalCase to kebab-case.
 *
 * @package TotalMailQueue
 */

spl_autoload_register(
	static function ( $class ) {
		$prefix = 'TotalMailQueue\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}
		$relative = substr( $class, strlen( $prefix ) );
		$parts    = explode( '\\', $relative );
		$basename = array_pop( $parts );
		// Convert "FooBar" / "ABTest" -> "foo-bar" / "ab-test".
		$file = strtolower( (string) preg_replace( '/(?<=[a-z0-9])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', '-', $basename ) );

		$dir  = '';
		if ( $parts ) {
			$dir = implode( '/', array_map( 'strtolower', $parts ) ) . '/';
		}
		$path = __DIR__ . '/src/' . $dir . $file . '.php';
		if ( is_file( $path ) ) {
			require $path;
		}
	}
);
