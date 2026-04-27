<?php
/**
 * CSS/JS for the plugin's admin pages.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Admin;

use TotalMailQueue\Plugin;
use TotalMailQueue\Smtp\ConnectionTester;
use TotalMailQueue\Templates\TestEmailSender;

/**
 * Enqueues the admin stylesheet and the inline JS bridge that wires the
 * admin React-y bits (message preview lazy-load, "Test connection" button,
 * bulk-action confirms) to the AJAX/REST endpoints.
 */
final class Assets {

	/**
	 * Hook the enqueue callback.
	 */
	public static function register(): void {
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	/**
	 * `admin_enqueue_scripts` callback. Loads CSS+JS only on the plugin's pages.
	 */
	public static function enqueue(): void {
		$screen = get_current_screen();
		if ( ! $screen || ! preg_match( '#wp_tmq_mail_queue#', $screen->base ) ) {
			return;
		}
		$base = plugin_dir_url( Plugin::container()->get( 'plugin.file' ) );
		wp_enqueue_style( 'wp_tmq_style', $base . 'assets/css/admin.css', array(), Plugin::VERSION );
		wp_enqueue_script( 'wp_tmq_script', $base . 'assets/js/tmq-admin.js', array( 'jquery' ), Plugin::VERSION, true );
		wp_add_inline_script( 'wp_tmq_script', self::inlineConfig(), 'before' );

		// Templates tab carries its own asset bundle (color picker + media
		// uploader + the test-email handler). Loaded only on that tab so the
		// other plugin pages stay slim.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing only, not a sensitive action.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'wp_tmq_mail_queue-tab-templates' === $page ) {
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_media();
			wp_enqueue_script(
				'wp_tmq_templates',
				$base . 'assets/js/tmq-templates.js',
				array( 'jquery', 'wp-color-picker' ),
				Plugin::VERSION,
				true
			);
		}
	}

	/**
	 * Inline config blob exposed to the admin JS as `window.tmq`.
	 */
	private static function inlineConfig(): string {
		$d  = '';
		$d .= '( function ( global ) {';
		$d .= '"use strict";';
		$d .= 'const tmq = global.tmq = global.tmq || {};';
		$d .= 'tmq.restUrl = "' . esc_url( wp_make_link_relative( rest_url() ) ) . '";';
		$d .= 'tmq.restNonce = "' . esc_html( wp_create_nonce( 'wp_rest' ) ) . '";';
		$d .= 'tmq.i18n = tmq.i18n || {};';
		$d .= 'tmq.i18n.errorLoadingMessage = "' . esc_js( __( 'There was an error loading the message.', 'total-mail-queue' ) ) . '";';
		$d .= 'tmq.i18n.confirmDelete = "' . esc_js( __( 'Are you sure you want to delete the selected items? This action cannot be undone.', 'total-mail-queue' ) ) . '";';
		$d .= 'tmq.i18n.testing = "' . esc_js( __( 'Testing...', 'total-mail-queue' ) ) . '";';
		$d .= 'tmq.i18n.testConnection = "' . esc_js( __( 'Test Connection', 'total-mail-queue' ) ) . '";';
		$d .= 'tmq.ajaxUrl = "' . esc_url( admin_url( 'admin-ajax.php' ) ) . '";';
		$d .= 'tmq.testSmtpNonce = "' . esc_js( wp_create_nonce( ConnectionTester::NONCE ) ) . '";';
		$d .= 'tmq.templateTest = { action: "' . esc_js( TestEmailSender::ACTION ) . '" };';
		$d .= 'tmq.i18n.sending = "' . esc_js( __( 'Sending…', 'total-mail-queue' ) ) . '";';
		$d .= 'tmq.i18n.testFailed = "' . esc_js( __( 'Test email failed: ', 'total-mail-queue' ) ) . '";';
		$d .= 'tmq.i18n.mediaTitle = "' . esc_js( __( 'Choose a logo', 'total-mail-queue' ) ) . '";';
		$d .= 'tmq.i18n.mediaButton = "' . esc_js( __( 'Use this image', 'total-mail-queue' ) ) . '";';
		$d .= '}) ( this );';
		return $d;
	}
}
