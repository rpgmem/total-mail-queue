<?php
/**
 * Top-level admin page renderer (Settings / Log / Retention / SMTP / FAQ / Cron Info).
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Admin;

use TotalMailQueue\Admin\Pages\CronInfoPage;
use TotalMailQueue\Admin\Pages\SmtpPage;
use TotalMailQueue\Admin\Pages\SourcesPage;
use TotalMailQueue\Admin\Pages\TemplatesPage;
use TotalMailQueue\Admin\Tables\LogTable;
use TotalMailQueue\Database\Schema;
use TotalMailQueue\Plugin;
use TotalMailQueue\Settings\Options;

/**
 * The single page callback registered by every submenu in {@see Menu}.
 *
 * Routes by `page` slug to the matching tab renderer:
 * - Settings  → inline Settings API form + Export/Import section.
 * - Log       → {@see LogTable} subclass in `MODE_LOG`.
 * - Retention → test-mail action handler + {@see QueueDiagnostics} notices
 *               + {@see LogTable} subclass in `MODE_QUEUE`.
 * - SMTP      → delegated to {@see SmtpPage}.
 * - Sources   → delegated to {@see SourcesPage}.
 * - Templates → delegated to {@see TemplatesPage}.
 * - FAQ       → {@see FaqRenderer} static markup.
 * - Cron Info → delegated to {@see CronInfoPage} (only registered when
 *               `DISABLE_WP_CRON` is truthy).
 */
final class PluginPage {

	/**
	 * Submenu slug constants, kept here so {@see Menu} and this class agree.
	 */
	public const TAB_SETTINGS  = 'wp_tmq_mail_queue';
	public const TAB_LOG       = 'wp_tmq_mail_queue-tab-log';
	public const TAB_QUEUE     = 'wp_tmq_mail_queue-tab-queue';
	public const TAB_SMTP      = 'wp_tmq_mail_queue-tab-smtp';
	public const TAB_SOURCES   = 'wp_tmq_mail_queue-tab-sources';
	public const TAB_TEMPLATES = 'wp_tmq_mail_queue-tab-templates';
	public const TAB_CRON_INFO = 'wp_tmq_mail_queue-tab-croninfo';
	public const TAB_FAQ       = 'wp_tmq_mail_queue-tab-faq';

	/**
	 * Page entry point — invoked by every submenu the {@see Menu} registers.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$import_notice = self::maybeHandleImport();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing only, individual tabs verify their own nonces.
		$tab = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : self::TAB_SETTINGS;

		echo '<div class="wrap">';
		self::renderHeader( $tab );
		self::renderNav( $tab );

		switch ( $tab ) {
			case self::TAB_LOG:
				self::renderLogTab();
				break;
			case self::TAB_QUEUE:
				self::renderQueueTab();
				break;
			case self::TAB_SMTP:
				SmtpPage::render();
				break;
			case self::TAB_SOURCES:
				SourcesPage::render();
				break;
			case self::TAB_TEMPLATES:
				TemplatesPage::render();
				break;
			case self::TAB_FAQ:
				self::renderFaqTab();
				break;
			case self::TAB_CRON_INFO:
				CronInfoPage::render();
				break;
			case self::TAB_SETTINGS:
			default:
				self::renderSettingsTab( $import_notice );
				break;
		}

		echo '</div>';
	}

	/**
	 * Verify the import nonce + run the import, returning the notice HTML.
	 *
	 * Done early (before any output) so the form-handling round-trip stays
	 * before the page chrome.
	 */
	private static function maybeHandleImport(): string {
		if ( ! isset( $_POST['wp_tmq_import'] ) ) {
			return '';
		}
		if ( ! isset( $_POST['wp_tmq_import_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['wp_tmq_import_nonce'] ) ), 'wp_tmq_import' ) ) {
			wp_die( esc_html__( 'Security check failed!', 'total-mail-queue' ) );
		}
		return ExportImport::handleImport();
	}

	/**
	 * Plugin logo + the global "WP Cron disabled" warning shown above the nav.
	 *
	 * @param string $tab Active tab slug.
	 */
	private static function renderHeader( string $tab ): void {
		$logo = plugins_url( 'assets/img/total-mail-queue-logo-wordmark.png', Plugin::container()->get( 'plugin.file' ) );
		echo '<h1 class="tmq-title"><img class="tmq-logo" src="' . esc_url( $logo ) . '" alt="' . esc_attr__( 'Total Mail Queue', 'total-mail-queue' ) . '" width="692" height="342" /></h1>';

		if ( self::TAB_CRON_INFO === $tab ) {
			return;
		}
		if ( ! defined( 'DISABLE_WP_CRON' ) || ! DISABLE_WP_CRON ) {
			return;
		}

		$url = esc_url( get_option( 'siteurl' ) . '/wp-cron.php' );
		echo '<div class="notice notice-warning notice-large">';
		echo '<p><strong>' . esc_html__( 'Please note:', 'total-mail-queue' ) . '</strong><br />' . wp_kses_post(
			sprintf(
				/* translators: %s: wp-cron.php URL */
				__( 'Your normal WP Cron is disabled. Please make sure you\'re running the Cron manually by calling %s every couple of minutes.', 'total-mail-queue' ),
				'<a href="' . $url . '" target="_blank">' . $url . '</a>'
			)
		) . '</p>';
		echo '<p><a href="?page=' . esc_attr( self::TAB_CRON_INFO ) . '">' . esc_html__( 'More information', 'total-mail-queue' ) . '</a></p>';
		echo '</div>';
	}

	/**
	 * Tab navigation strip.
	 *
	 * @param string $tab Active tab slug.
	 */
	private static function renderNav( string $tab ): void {
		$tabs = array(
			self::TAB_SETTINGS  => __( 'Settings', 'total-mail-queue' ),
			self::TAB_LOG       => __( 'Log', 'total-mail-queue' ),
			self::TAB_QUEUE     => __( 'Retention', 'total-mail-queue' ),
			self::TAB_SMTP      => __( 'SMTP Accounts', 'total-mail-queue' ),
			self::TAB_SOURCES   => __( 'Sources', 'total-mail-queue' ),
			self::TAB_TEMPLATES => __( 'Templates', 'total-mail-queue' ),
		);
		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			$tabs[ self::TAB_CRON_INFO ] = __( 'Cron Information', 'total-mail-queue' );
		}
		$tabs[ self::TAB_FAQ ] = __( 'FAQ', 'total-mail-queue' );

		echo '<nav class="nav-tab-wrapper">';
		foreach ( $tabs as $slug => $label ) {
			$class = 'nav-tab' . ( $tab === $slug ? ' nav-tab-active' : '' );
			echo '<a href="?page=' . esc_attr( $slug ) . '" class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';
	}

	/**
	 * Settings tab — Settings API form + Export/Import section.
	 *
	 * @param string $import_notice Already-rendered notice HTML from the import flow.
	 */
	private static function renderSettingsTab( string $import_notice ): void {
		echo '<form action="options.php" method="post">';
		settings_fields( SettingsApi::GROUP );
		do_settings_sections( SettingsApi::PAGE );
		submit_button();
		echo '</form>';

		echo '<hr />';
		echo '<h2>' . esc_html__( 'Export / Import', 'total-mail-queue' ) . '</h2>';

		if ( '' !== $import_notice ) {
			echo wp_kses_post( $import_notice );
		}

		echo '<div class="tmq-export-import">';

		// Export.
		echo '<div class="tmq-box">';
		echo '<h3>' . esc_html__( 'Export', 'total-mail-queue' ) . '</h3>';
		echo '<p>' . esc_html__( 'Download an XML file with all plugin settings and SMTP accounts. Passwords are included in encrypted form and can only be imported on a site with the same WordPress salt keys.', 'total-mail-queue' ) . '</p>';
		echo '<form method="post">';
		wp_nonce_field( 'wp_tmq_export', 'wp_tmq_export_nonce' );
		echo '<button type="submit" name="wp_tmq_export" class="button button-primary">' . esc_html__( 'Export Settings', 'total-mail-queue' ) . '</button>';
		echo '</form>';
		echo '</div>';

		// Import.
		echo '<div class="tmq-box">';
		echo '<h3>' . esc_html__( 'Import', 'total-mail-queue' ) . '</h3>';
		echo '<p>' . esc_html__( 'Upload a previously exported XML file to restore settings and SMTP accounts. This will replace all current settings and SMTP accounts.', 'total-mail-queue' ) . '</p>';
		echo '<form method="post" enctype="multipart/form-data">';
		wp_nonce_field( 'wp_tmq_import', 'wp_tmq_import_nonce' );
		echo '<input type="file" name="wp_tmq_import_file" accept=".xml" required /> ';
		echo '<button type="submit" name="wp_tmq_import" class="button" onclick="return confirm(\'' . esc_js( __( 'This will replace all current settings and SMTP accounts. Continue?', 'total-mail-queue' ) ) . '\');">' . esc_html__( 'Import Settings', 'total-mail-queue' ) . '</button>';
		echo '</form>';
		echo '</div>';

		echo '</div>';
	}

	/**
	 * Log tab — wraps the {@see LogTable} subclass.
	 */
	private static function renderLogTab(): void {
		echo '<form method="post">';
		$logtable = new LogTable( LogTable::MODE_LOG );
		$logtable->prepare_items();
		$logtable->display();
		echo '</form>';
	}

	/**
	 * Retention tab — mode-aware notices, "addtestmail" handler, conflict
	 * detection, and the queue {@see LogTable} listing.
	 */
	private static function renderQueueTab(): void {
		$options = Options::get();

		// "Resent N email(s)" success notice carried by the resend redirect.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only flash flag, not destructive.
		if ( isset( $_GET['resent'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- same.
			$resent_count = intval( $_GET['resent'] );
			if ( $resent_count > 0 ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(
					sprintf(
						/* translators: %d: number of emails resent */
						__( '%d email(s) have been added back to the queue for resending.', 'total-mail-queue' ),
						$resent_count
					)
				) . '</p></div>';
			}
		}

		// "Put a test mail" link target (FAQ tab carries the nonce'd link).
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce checked just below.
		if ( isset( $_GET['addtestmail'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'wp_tmq_addtestmail' ) ) {
			global $wpdb;
			$table = Schema::queueTable();
			$data  = array(
				'timestamp' => current_time( 'mysql', false ),
				'recipient' => (string) $options['email'],
				/* translators: %s: timestamp */
				'subject'   => sprintf( __( 'Testmail #%s', 'total-mail-queue' ), time() ),
				'message'   => __( 'This is just a test email sent by the Total Mail Queue plugin.', 'total-mail-queue' ),
				'status'    => 'queue',
			);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->insert( $table, $data );
		}

		QueueDiagnostics::renderModeNotice( $options );
		QueueDiagnostics::renderConflictNotice( $options );

		echo '<form method="post" action="admin.php?page=' . esc_attr( self::TAB_QUEUE ) . '">';
		$queuetable = new LogTable( LogTable::MODE_QUEUE );
		$queuetable->prepare_items();
		$queuetable->display();
		echo '</form>';
	}

	/**
	 * FAQ tab — long-form static help with code samples, prefaced by the
	 * postman hero banner.
	 */
	private static function renderFaqTab(): void {
		$postman = plugins_url( 'assets/img/total-mail-queue-postman.png', Plugin::container()->get( 'plugin.file' ) );
		echo '<div class="tmq-banner">';
		echo '<img src="' . esc_url( $postman ) . '" alt="" width="1408" height="768" loading="lazy" />';
		echo '</div>';

		$options = Options::get();
		FaqRenderer::render( $options );
	}
}
