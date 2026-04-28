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
	public const VERSION = '2.5.2';

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
	 * Bootstrap the plugin: build the container, register lifecycle hooks,
	 * and wire upcoming services.
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

		// Lifecycle hooks. Uninstall is wired via the dedicated uninstall.php
		// file at the plugin root (WP convention) since register_uninstall_hook
		// cannot serialize closures, and our handler needs the main file path.
		register_activation_hook( $main_file, array( Lifecycle\Activator::class, 'activate' ) );
		register_deactivation_hook( $main_file, array( Lifecycle\Deactivator::class, 'deactivate' ) );

		// Run pending schema migrations on every load (cheap when up-to-date).
		add_action( 'plugins_loaded', array( Database\Migrator::class, 'maybeMigrate' ), 10, 0 );

		// Source-tracking listeners on WP-core filters that fire just before
		// wp_mail(). Wired regardless of mode/cron so the catalog stays in
		// sync; only the interceptor below cares about consuming the marker.
		Sources\Detector::register();
		Sources\WpVersionNotice::register();

		// Mail interception (queue/block modes only, never during WP cron).
		Queue\MailInterceptor::register();
		Queue\MailFailedHandler::register();
		Queue\MailSucceededHandler::register();

		// HTML template engine. Hooks `wp_mail` (priority 100) so mail that
		// is about to leave the server gets wrapped — both direct sends in
		// disabled mode and cron drains in queue mode. Skipped automatically
		// in block mode (no mail leaves anyway).
		Templates\Engine::register();
		Templates\WooCommerceTokens::register();

		// Cron worker — schedule + the actual queue-draining action.
		Cron\Scheduler::register();

		// Admin AJAX endpoints.
		Smtp\ConnectionTester::register();
		Templates\TestEmailSender::register();

		// Admin UI scaffolding — text domain, action links, asset pipeline,
		// menu + page renderer, Settings API, admin notices, export/import.
		Admin\TextDomain::register();
		Admin\PluginRowLinks::register();
		Admin\Menu::register();
		Admin\Assets::register();
		Admin\SettingsApi::register();
		Admin\Notices::register();
		Admin\ExportImport::register();

		// REST API.
		Rest\MessageController::register();
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
