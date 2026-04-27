<?php

declare(strict_types=1);

namespace TMQ\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Regression guard for the inline PSR-4-style autoloader registered by
 * `total-mail-queue.php`. Composer's classmap autoloader (loaded by the
 * test bootstraps) finds classes regardless of file naming conventions,
 * so the unit / integration / functional suites can pass while the
 * production environment — which has nothing but the inline
 * spl_autoload_register — bombs with `Class … not found`.
 *
 * This test re-implements the inline autoloader's mapping (same regex,
 * same lower-casing rule) and asserts that every namespaced class
 * declared under `src/` resolves to a file that actually exists on disk.
 *
 * If you add a new class and forget the kebab-case file name (e.g.
 * `WooCommerceTokens` → `woo-commerce-tokens.php`), this test fires.
 */
final class AutoloaderConsistencyTest extends TestCase {

	/**
	 * Mirror of the regex + path computation in total-mail-queue.php
	 * (the inline `spl_autoload_register` callback). Returns the path
	 * the autoloader will require for a given fully-qualified class
	 * name, or null if the class is outside the plugin's namespace.
	 */
	private function autoloaderPath( string $fqn ): ?string {
		$prefix = 'TotalMailQueue\\';
		if ( 0 !== strpos( $fqn, $prefix ) ) {
			return null;
		}
		$relative = substr( $fqn, strlen( $prefix ) );
		$parts    = explode( '\\', $relative );
		$basename = array_pop( $parts );
		$file     = strtolower( (string) preg_replace( '/(?<=[a-z0-9])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', '-', $basename ) );
		$dir      = $parts ? implode( '/', array_map( 'strtolower', $parts ) ) . '/' : '';
		return 'src/' . $dir . $file . '.php';
	}

	/**
	 * Walk every `.php` file under `src/`, parse its `namespace …;`
	 * and `class Foo` declarations, and require that the inline
	 * autoloader can find the file for each (namespace, class) pair.
	 */
	public function test_every_class_under_src_is_reachable_by_the_inline_autoloader(): void {
		$root = dirname( __DIR__, 2 );
		$rii  = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root . '/src' ) );

		$broken = array();
		foreach ( $rii as $file ) {
			if ( ! $file->isFile() || 'php' !== $file->getExtension() ) {
				continue;
			}
			$code = (string) file_get_contents( (string) $file );
			if ( ! preg_match( '/^namespace (TotalMailQueue\\\\[A-Za-z0-9\\\\]+);/m', $code, $ns ) ) {
				continue;
			}
			if ( ! preg_match_all( '/^(?:final |abstract )?class ([A-Za-z0-9_]+)/m', $code, $cls ) ) {
				continue;
			}
			foreach ( $cls[1] as $name ) {
				$fqn      = $ns[1] . '\\' . $name;
				$expected = $this->autoloaderPath( $fqn );
				if ( null === $expected ) {
					continue;
				}
				$abs = $root . '/' . $expected;
				if ( ! file_exists( $abs ) ) {
					$broken[] = sprintf( '%s → expected %s (missing)', $fqn, $expected );
				}
			}
		}

		self::assertSame(
			array(),
			$broken,
			"The inline autoloader in total-mail-queue.php cannot resolve the following classes — production sites will hit a Fatal:\n  " . implode( "\n  ", $broken )
		);
	}
}
