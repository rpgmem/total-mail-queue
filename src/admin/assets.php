<?php
/**
 * CSS/JS for the plugin's admin pages.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Admin;

use TotalMailQueue\Plugin;
use TotalMailQueue\Settings\Options;
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
		wp_enqueue_style( 'wp_tmq_style', $base . 'assets/css/admin' . self::suffix() . '.css', array(), Plugin::VERSION );

		// Printed in the footer, at <body> level, so `position: fixed` is
		// relative to the viewport and no ancestor can contain it.
		add_action( 'admin_footer', array( self::class, 'printBackToTop' ) );

		// The theme class must be on <html> before the page paints, or a dark
		// screen flashes light first: an inline script in the head, with no
		// file behind it.
		wp_register_script( 'wp_tmq_theme', false, array(), Plugin::VERSION, false );
		wp_enqueue_script( 'wp_tmq_theme' );
		wp_add_inline_script( 'wp_tmq_theme', self::darkModeScript( (string) Options::get()['dark_mode'] ) );
		wp_enqueue_script( 'wp_tmq_script', $base . 'assets/js/tmq-admin' . self::suffix() . '.js', array( 'jquery' ), Plugin::VERSION, true );
		wp_add_inline_script( 'wp_tmq_script', self::inlineConfig(), 'before' );
		wp_enqueue_script( 'wp_tmq_autosave', $base . 'assets/js/tmq-autosave' . self::suffix() . '.js', array( 'wp_tmq_script' ), Plugin::VERSION, true );

		// Templates tab carries its own asset bundle (color picker + media
		// uploader + the test-email handler). Loaded only on that tab so the
		// other plugin pages stay slim.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing only, not a sensitive action.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( PluginPage::TAB_DOCS === $page || PluginPage::TAB_FAQ === $page ) {
			wp_enqueue_script( 'wp_tmq_docs', $base . 'assets/js/tmq-docs' . self::suffix() . '.js', array(), Plugin::VERSION, true );
		}
		if ( 'wp_tmq_mail_queue-tab-templates' === $page ) {
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_media();
			wp_enqueue_script(
				'wp_tmq_templates',
				$base . 'assets/js/tmq-templates' . self::suffix() . '.js',
				array( 'jquery', 'wp-color-picker' ),
				Plugin::VERSION,
				true
			);
		}
	}

	/**
	 * The file suffix for the plugin's own CSS and JS: `.min` in production,
	 * the readable source when `SCRIPT_DEBUG` is on. `npm run build` writes
	 * the `.min` files, and CI fails when one is older than its source.
	 */
	public static function suffix(): string {
		return defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
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
		$d .= 'tmq.i18n.previewUpdating = "' . esc_js( __( 'Updating preview…', 'total-mail-queue' ) ) . '";';
		$d .= 'tmq.i18n.previewFailed = "' . esc_js( __( 'The preview could not be updated.', 'total-mail-queue' ) ) . '";';
		$d .= 'tmq.autosave = ' . wp_json_encode(
			array(
				'action'  => SettingsAutosave::ACTION,
				'nonce'   => wp_create_nonce( SettingsAutosave::NONCE ),
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'i18n'    => array(
					'saving' => __( 'Saving…', 'total-mail-queue' ),
					'saved'  => __( 'Saved', 'total-mail-queue' ),
					'error'  => __( 'Not saved', 'total-mail-queue' ),
				),
			)
		) . ';';
		$d .= '}) ( this );';
		return $d;
	}

	/**
	 * The script that puts `tmq-dark-mode` on <html>.
	 *
	 * "on" and "off" are decided here; only "auto" asks the operating system,
	 * and it keeps listening, so switching the system theme repaints the open
	 * page. An unknown value is treated as "off".
	 *
	 * @param string $mode The stored `dark_mode` setting.
	 */
	public static function darkModeScript( string $mode ): string {
		if ( ! in_array( $mode, Options::DARK_MODES, true ) ) {
			$mode = 'off';
		}
		return '(function(mode){'
			. 'var root=document.documentElement;'
			. 'var query=window.matchMedia?window.matchMedia("(prefers-color-scheme: dark)"):null;'
			. 'function apply(){root.classList.toggle("tmq-dark-mode",mode==="on"||(mode==="auto"&&!!query&&query.matches));}'
			. 'apply();'
			. 'if(mode==="auto"&&query&&query.addEventListener){query.addEventListener("change",apply);}'
			. '})(' . wp_json_encode( $mode ) . ');';
	}

	/**
	 * The floating "Back to top" link on the plugin's screens. A plain
	 * anchor to the page wrapper: it works without JavaScript.
	 */
	public static function printBackToTop(): void {
		echo '<a href="#tmq-top" class="tmq-back-to-top" title="' . esc_attr__( 'Back to top', 'total-mail-queue' ) . '">'
			. Icons::tag( 'top' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons::tag() is fixed markup.
			. '<span class="screen-reader-text">' . esc_html__( 'Back to top', 'total-mail-queue' ) . '</span></a>';
	}
}
