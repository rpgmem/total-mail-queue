<?php
/**
 * Screenshot viewer in the "View details" modal.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Update;

use TotalMailQueue\Plugin;

/**
 * Loads the viewer that opens the modal's screenshots in an overlay instead
 * of a new tab (`assets/js/tmq-screenshots.js`).
 *
 * The modal is core's `plugin-install.php?tab=plugin-information` page, shown
 * in an iframe, and it fires `admin_enqueue_scripts` like any admin screen.
 * Its sections pass through kses, which strips scripts, so the viewer has to
 * arrive as an enqueued file. It loads only on that page and only for this
 * plugin's slug, so no other plugin's modal and no other screen carries it.
 */
final class ScreenshotViewer {

	/**
	 * Script and style handle.
	 */
	public const HANDLE = 'wp_tmq_screenshots';

	/**
	 * Register the enqueue hook.
	 *
	 * Default priority: nothing else on that page depends on the order.
	 */
	public static function register(): void {
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	/**
	 * Enqueue the viewer on this plugin's "View details" modal.
	 *
	 * @param string $hook_suffix The admin page being loaded.
	 */
	public static function enqueue( string $hook_suffix ): void {
		if ( ! self::isOurModal( $hook_suffix ) ) {
			return;
		}

		$base = plugin_dir_url( Plugin::container()->get( 'plugin.file' ) );
		wp_enqueue_style( self::HANDLE, $base . 'assets/css/screenshots.css', array(), Plugin::VERSION );
		wp_enqueue_script( self::HANDLE, $base . 'assets/js/tmq-screenshots.js', array(), Plugin::VERSION, true );
		wp_localize_script(
			self::HANDLE,
			'tmqScreenshots',
			array(
				'dialog'   => __( 'Screenshots', 'total-mail-queue' ),
				'close'    => __( 'Close', 'total-mail-queue' ),
				'previous' => __( 'Previous screenshot', 'total-mail-queue' ),
				'next'     => __( 'Next screenshot', 'total-mail-queue' ),
				'zoomIn'   => __( 'Zoom in', 'total-mail-queue' ),
				'zoomOut'  => __( 'Zoom out', 'total-mail-queue' ),
				/* translators: %1$s: number of the screenshot shown, %2$s: number of screenshots */
				'counter'  => __( '%1$s of %2$s', 'total-mail-queue' ),
			)
		);
	}

	/**
	 * Is this the "View details" modal for this plugin?
	 *
	 * A read-only check of the page's own query arguments: core's modal link
	 * carries no nonce, and nothing is changed here beyond loading two files.
	 *
	 * @param string $hook_suffix The admin page being loaded.
	 */
	private static function isOurModal( string $hook_suffix ): bool {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only routing on core's own modal URL.
		$tab    = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		$plugin = isset( $_GET['plugin'] ) ? sanitize_key( wp_unslash( $_GET['plugin'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return 'plugin-install.php' === $hook_suffix && 'plugin-information' === $tab && GithubUpdater::SLUG === $plugin;
	}
}
