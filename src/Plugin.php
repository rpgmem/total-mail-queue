<?php
/**
 * Plugin orchestrator and global entry point.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue;

/**
 * Boots the plugin and exposes its service container.
 *
 * The class itself is intentionally tiny — its only job is to instantiate
 * the {@see Container}, register service factories on it (added by later
 * phases of the rebuild), and hold the singleton reference so any consumer
 * can grab a service via {@see Plugin::container()}.
 */
final class Plugin {

	/**
	 * Plugin version. Kept in sync with the header in total-mail-queue.php
	 * and used by the upgrade routine to detect schema bumps.
	 */
	public const VERSION = '2.3.0';

	/**
	 * Minimum required PHP version (mirrors the plugin header).
	 */
	public const REQUIRES_PHP = '7.4';

	/**
	 * Minimum required WordPress version (mirrors readme.txt).
	 */
	public const REQUIRES_WP = '5.9';

	/**
	 * Singleton container instance, populated by {@see Plugin::boot()}.
	 *
	 * @var Container|null
	 */
	private static ?Container $container = null;

	/**
	 * Bootstrap the plugin: build the container and register its services.
	 *
	 * Called once from total-mail-queue.php after the Composer autoloader
	 * has been required.
	 *
	 * @param string $main_file Absolute path to the plugin's main entry file.
	 */
	public static function boot( string $main_file ): void {
		if ( self::$container instanceof Container ) {
			return;
		}

		self::$container = new Container();
		self::$container->set( 'plugin.file', static fn (): string => $main_file );
		self::$container->set( 'plugin.dir', static fn (): string => trailingslashit( dirname( $main_file ) ) );

		// Service registrations are added by later rebuild phases.
	}

	/**
	 * Access the live container.
	 *
	 * @throws \LogicException When called before {@see Plugin::boot()}.
	 */
	public static function container(): Container {
		if ( ! self::$container instanceof Container ) {
			throw new \LogicException( 'Plugin::container() called before Plugin::boot().' );
		}
		return self::$container;
	}

	/**
	 * Reset the container. Tests use this between cases; production code never calls it.
	 *
	 * @internal
	 */
	public static function reset(): void {
		self::$container = null;
	}
}
