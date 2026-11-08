<?php
/**
 * A stand-in for WP-CLI's output API, so a command's methods can run in the
 * test suite and their output be read back. Loaded only when the real class
 * is absent.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

if ( ! class_exists( 'WP_CLI' ) ) {
	// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound, PEAR.NamingConventions.ValidClassName.Invalid -- mirrors the real WP-CLI class name
	final class WP_CLI {

		/**
		 * Every call, as [ method, message ].
		 *
		 * @var list<array{0: string, 1: string}>
		 */
		public static array $out = array();

		/**
		 * Commands registered.
		 *
		 * @var array<string,mixed>
		 */
		public static array $commands = array();

		public static function add_command( string $name, $callable ): void {
			self::$commands[ $name ] = $callable;
		}

		public static function line( string $message = '' ): void {
			self::$out[] = array( 'line', $message );
		}

		public static function success( string $message ): void {
			self::$out[] = array( 'success', $message );
		}

		public static function warning( string $message ): void {
			self::$out[] = array( 'warning', $message );
		}
	}
}
