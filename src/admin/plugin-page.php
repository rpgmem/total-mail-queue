<?php
/**
 * Top-level admin page renderer (Settings / Log / Retention / SMTP / FAQ / Cron Info).
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Admin;

use TotalMailQueue\Admin\Pages\SmtpPage;
use TotalMailQueue\Admin\Pages\SourcesPage;
use TotalMailQueue\Admin\Pages\TemplatesPage;
use TotalMailQueue\Admin\Tables\LogTable;
use TotalMailQueue\Database\Schema;
use TotalMailQueue\Plugin;
use TotalMailQueue\Queue\MailInterceptor;
use TotalMailQueue\Settings\Options;

/**
 * The single page callback registered by every submenu in {@see Menu}.
 *
 * Routes by `page` slug to the matching tab renderer. Settings tab uses the
 * Settings API plus the Export/Import section, Log+Retention tabs use the
 * {@see LogTable} subclass, the SMTP Accounts tab delegates to
 * {@see SmtpPage}, and the FAQ + Cron Information tabs are static markup.
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
				self::renderCronInfoTab();
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
		$logo = plugins_url( 'assets/img/total-mail-queue-logo-wordmark.svg', Plugin::container()->get( 'plugin.file' ) );
		echo '<h1 class="tmq-title"><img class="tmq-logo" src="' . esc_url( $logo ) . '" alt="' . esc_attr__( 'Total Mail Queue', 'total-mail-queue' ) . '" width="308" height="56" /></h1>';

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
		$logtable = new LogTable();
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

		self::renderQueueModeNotice( $options );
		self::renderConflictNotice( $options );

		echo '<form method="post" action="admin.php?page=' . esc_attr( self::TAB_QUEUE ) . '">';
		$queuetable = new LogTable();
		$queuetable->prepare_items();
		$queuetable->display();
		echo '</form>';
	}

	/**
	 * Block/Queue/Disabled state notice + last-cron diagnostics.
	 *
	 * @param array<string,mixed> $options Live settings.
	 */
	private static function renderQueueModeNotice( array $options ): void {
		$mode = (string) $options['enabled'];

		if ( '2' === $mode ) {
			echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Block Mode Active', 'total-mail-queue' ) . '</strong> — ' . esc_html__( 'All outgoing emails are being retained and will NOT be sent. No emails will leave this server.', 'total-mail-queue' ) . ' ' . wp_kses_post(
				sprintf(
					/* translators: %1$s: opening link tag, %2$s: closing link tag */
					__( 'Change this in %1$sSettings%2$s.', 'total-mail-queue' ),
					'<a href="admin.php?page=' . self::TAB_SETTINGS . '">',
					'</a>'
				)
			) . '</p></div>';
			return;
		}

		if ( '1' === $mode ) {
			$next = wp_next_scheduled( 'wp_tmq_mail_queue_hook' );
			if ( $next && $next > time() ) {
				echo '<div class="notice notice-success"><p>' . esc_html(
					sprintf(
						/* translators: %1$s: human-readable time diff, %2$s: scheduled time */
						__( 'Next sending will be triggered in %1$s at %2$s.', 'total-mail-queue' ),
						human_time_diff( $next ),
						wp_date( 'H:i', $next )
					)
				) . '</p></div>';
			}

			$last_cron = get_option( 'wp_tmq_last_cron' );
			if ( is_array( $last_cron ) ) {
				$parts   = array();
				$parts[] = '<strong>' . esc_html__( 'Last cron run:', 'total-mail-queue' ) . '</strong> ' . esc_html( (string) ( $last_cron['time'] ?? '—' ) );
				$parts[] = '<strong>' . esc_html__( 'Result:', 'total-mail-queue' ) . '</strong> ' . esc_html( (string) ( $last_cron['result'] ?? '—' ) );
				if ( isset( $last_cron['queue_total'] ) ) {
					$parts[] = '<strong>' . esc_html__( 'Queue:', 'total-mail-queue' ) . '</strong> ' . esc_html( (string) $last_cron['queue_total'] ) . ' total, ' . esc_html( (string) $last_cron['queue_batch'] ) . ' batch';
					$parts[] = '<strong>' . esc_html__( 'SMTP accounts:', 'total-mail-queue' ) . '</strong> ' . esc_html( (string) $last_cron['smtp_accounts'] );
					$parts[] = '<strong>' . esc_html__( 'Send method:', 'total-mail-queue' ) . '</strong> ' . esc_html( (string) $last_cron['send_method'] );
					$parts[] = '<strong>' . esc_html__( 'Sent:', 'total-mail-queue' ) . '</strong> ' . esc_html( (string) $last_cron['sent'] ) . ' | <strong>' . esc_html__( 'Errors:', 'total-mail-queue' ) . '</strong> ' . esc_html( (string) $last_cron['errors'] );
				}
				$class = ( ( $last_cron['result'] ?? '' ) === 'ok' ) ? 'notice-info' : 'notice-warning';
				echo '<div class="notice ' . esc_attr( $class ) . '"><p>' . wp_kses_post( implode( ' &nbsp;|&nbsp; ', $parts ) ) . '</p></div>';
			}
			return;
		}

		echo '<div class="notice notice-warning"><p>' . wp_kses_post(
			sprintf(
				/* translators: %1$s: opening link tag, %2$s: closing link tag */
				__( 'The plugin is currently disabled. Enable it in the %1$sSettings%2$s.', 'total-mail-queue' ),
				'<a href="admin.php?page=' . self::TAB_SETTINGS . '">',
				'</a>'
			)
		) . '</p></div>';
	}

	/**
	 * Warn when other code has subscribed to `pre_wp_mail`. Skip our own
	 * interceptor; everything else is reported.
	 *
	 * @param array<string,mixed> $options Live settings.
	 */
	private static function renderConflictNotice( array $options ): void {
		if ( '1' !== (string) $options['enabled'] ) {
			return;
		}

		global $wp_filter;
		if ( ! isset( $wp_filter['pre_wp_mail'] ) ) {
			return;
		}

		$other_filters = array();
		foreach ( $wp_filter['pre_wp_mail']->callbacks as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$func = $callback['function'];
				if ( is_array( $func ) ) {
					$class_or_obj = is_object( $func[0] ) ? get_class( $func[0] ) : (string) $func[0];
					if ( MailInterceptor::class === $class_or_obj ) {
						continue;
					}
					$other_filters[] = $class_or_obj . '::' . (string) $func[1];
				} elseif ( is_string( $func ) ) {
					$other_filters[] = $func;
				} else {
					$other_filters[] = __( '(closure)', 'total-mail-queue' );
				}
			}
		}
		if ( empty( $other_filters ) ) {
			return;
		}

		echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Warning: conflicting email plugin detected.', 'total-mail-queue' ) . '</strong> ';
		echo wp_kses_post(
			sprintf(
				/* translators: %1$s: filter name, %2$s: list of conflicting callbacks */
				__( 'The following filter(s) on %1$s may interfere with email sending: %2$s. Consider deactivating conflicting email plugins when using SMTP accounts from Total Mail Queue.', 'total-mail-queue' ),
				'<code>pre_wp_mail</code>',
				'<code>' . esc_html( implode( '</code>, <code>', $other_filters ) ) . '</code>'
			)
		);
		echo '</p></div>';
	}

	/**
	 * Cron Information tab — only registered when DISABLE_WP_CRON is set.
	 */
	private static function renderCronInfoTab(): void {
		$options = Options::get();
		echo '<div class="tmq-box">';
		echo '<h3>' . esc_html__( 'Information: Your common WP Cron is disabled', 'total-mail-queue' ) . '</h3>';
		echo '<p>' . wp_kses_post( __( 'It looks like you deactivated the WP Cron by <i>define( \'DISABLE_WP_CRON\', true )</i>.', 'total-mail-queue' ) ) . '</p>';
		$url = esc_url( get_option( 'siteurl' ) . '/wp-cron.php' );
		echo '<p>' . wp_kses_post(
			sprintf(
				/* translators: %s: wp-cron.php URL */
				__( 'In general, this is no problem at all. We just want to remind you to make sure you\'re running the Cron manually by calling %s every couple of minutes.', 'total-mail-queue' ),
				'<a href="' . $url . '" target="_blank">' . $url . '</a>'
			)
		) . '</p>';
		echo '</div>';

		if ( ! function_exists( '_get_cron_array' ) ) {
			return;
		}
		$next_tasks = _get_cron_array();
		if ( ! $next_tasks ) {
			return;
		}

		$tasks_in_past         = false;
		$queue_task_in_past_at = 0;
		foreach ( $next_tasks as $key => $val ) {
			if ( time() <= intval( $key ) + intval( $options['queue_interval'] ) ) {
				continue;
			}
			$task_keys = array_keys( $val );
			if ( ! empty( $task_keys ) && 'wp_tmq_mail_queue_hook' === $task_keys[0] ) {
				$queue_task_in_past_at = intval( $key );
			}
			$tasks_in_past = true;
		}
		if ( ! $tasks_in_past ) {
			return;
		}

		echo '<div class="tmq-box">';
		echo '<h3>' . esc_html__( 'Attention: It seems that your WP Cron is not running. There are some jobs waiting to be completed.', 'total-mail-queue' ) . '</h3>';
		if ( $queue_task_in_past_at > 0 ) {
			echo '<p><b>' . esc_html(
				sprintf(
					/* translators: %s: human-readable time diff */
					__( 'The Queue hasn\'t been able to be executed since %s.', 'total-mail-queue' ),
					human_time_diff( $queue_task_in_past_at, time() )
				)
			) . '</b></p>';
		}
		echo '</div>';
	}

	/**
	 * FAQ tab — long-form static help with code samples.
	 */
	private static function renderFaqTab(): void {
		$options = Options::get();
		FaqRenderer::render( $options );
	}
}
