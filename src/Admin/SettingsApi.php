<?php
/**
 * Settings API registration + render callbacks for the Settings tab.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Admin;

use TotalMailQueue\Database\Schema;
use TotalMailQueue\Settings\Options;
use TotalMailQueue\Settings\Sanitizer;

/**
 * Registers the `wp_tmq_settings` setting plus every field rendered on the
 * Settings tab. The Sanitizer enforces the canonical format on save; each
 * render method below renders one row using the live (already-normalised)
 * options array.
 */
final class SettingsApi {

	/**
	 * Settings group + page slug used by the Settings API.
	 */
	public const GROUP = 'wp_tmq_settings';
	public const PAGE  = 'wp_tmq_settings_page';

	/**
	 * Hook the registration onto admin_init.
	 */
	public static function register(): void {
		add_action( 'admin_init', array( self::class, 'init' ) );
	}

	/**
	 * `admin_init` callback. Registers the option + every field.
	 */
	public static function init(): void {
		register_setting(
			self::GROUP,
			Options::OPTION_NAME,
			array(
				'sanitize_callback' => array( Sanitizer::class, 'sanitize' ),
			)
		);

		add_settings_section( 'wp_tmq_settings_section', '', null, self::PAGE );

		$fields = array(
			'wp_tmq_status'        => array( __( 'Operation Mode', 'total-mail-queue' ), 'renderStatus' ),
			'wp_tmq_queue'         => array( __( 'Queue', 'total-mail-queue' ), 'renderQueue' ),
			'wp_tmq_log'           => array( __( 'Log', 'total-mail-queue' ), 'renderLog' ),
			'wp_tmq_send_method'   => array( __( 'Send Method', 'total-mail-queue' ), 'renderSendMethod' ),
			'wp_tmq_retry'         => array( __( 'Auto-Retry', 'total-mail-queue' ), 'renderRetry' ),
			'wp_tmq_smtp_timeout'  => array( __( 'SMTP Timeout', 'total-mail-queue' ), 'renderSmtpTimeout' ),
			'wp_tmq_cron_lock_ttl' => array( __( 'Cron Lock Timeout', 'total-mail-queue' ), 'renderCronLockTtl' ),
			'wp_tmq_alert_status'  => array( __( 'Alert enabled', 'total-mail-queue' ), 'renderAlertStatus' ),
			'wp_tmq_sensitivity'   => array( __( 'Alert Sensitivity', 'total-mail-queue' ), 'renderSensitivity' ),
		);

		foreach ( $fields as $id => $spec ) {
			add_settings_field( $id, $spec[0], array( self::class, $spec[1] ), self::PAGE, 'wp_tmq_settings_section' );
		}
	}

	/**
	 * Operation mode select (disabled / queue / block).
	 */
	public static function renderStatus(): void {
		$options = Options::get();
		$mode    = (string) $options['enabled'];
		echo '<select name="wp_tmq_settings[enabled]">';
		echo '<option value="0"' . selected( $mode, '0', false ) . '>' . esc_html__( 'Disabled — No interception, emails sent normally', 'total-mail-queue' ) . '</option>';
		echo '<option value="1"' . selected( $mode, '1', false ) . '>' . esc_html__( 'Queue — Retain emails and send via queue', 'total-mail-queue' ) . '</option>';
		echo '<option value="2"' . selected( $mode, '2', false ) . '>' . esc_html__( 'Block — Retain all emails, never send (block all outgoing)', 'total-mail-queue' ) . '</option>';
		echo '</select>';
		if ( '0' === $mode ) {
			echo ' <span class="tmq-warning">' . esc_html__( 'The plugin is currently disabled and has no effect.', 'total-mail-queue' ) . '</span>';
		} elseif ( '2' === $mode ) {
			echo ' <span class="tmq-warning tmq-warning-block">' . esc_html__( 'All outgoing emails are being blocked!', 'total-mail-queue' ) . '</span>';
		}
	}

	/**
	 * Alert-enabled checkbox.
	 */
	public static function renderAlertStatus(): void {
		$options = Options::get();
		$checked = '1' === (string) $options['alert_enabled'] ? ' checked' : '';
		echo '<input type="checkbox" name="wp_tmq_settings[alert_enabled]" value="1"' . esc_attr( $checked ) . ' />';
	}

	/**
	 * Queue throughput row — amount per interval + unit.
	 */
	public static function renderQueue(): void {
		$options = Options::get();
		if ( 'seconds' === $options['queue_interval_unit'] ) {
			$number = intval( $options['queue_interval'] );
		} else {
			$number = intval( $options['queue_interval'] ) / 60;
		}

		echo esc_html__( 'Send max.', 'total-mail-queue' ) .
			' <input name="wp_tmq_settings[queue_amount]" type="number" min="1" value="' . esc_attr( (string) $options['queue_amount'] ) . '" /> ' .
			esc_html__( 'email(s) every', 'total-mail-queue' ) .
			' <input name="wp_tmq_settings[queue_interval]" type="number" min="1" value="' . esc_attr( (string) $number ) . '" />';

		echo '<select name="wp_tmq_settings[queue_interval_unit]">';
		echo '<option value="minutes"' . selected( $options['queue_interval_unit'], 'minutes', false ) . '>' . esc_html__( 'minute(s)', 'total-mail-queue' ) . '</option>';
		echo '<option value="seconds"' . selected( $options['queue_interval_unit'], 'seconds', false ) . '>' . esc_html__( 'second(s)', 'total-mail-queue' ) . '</option>';
		echo '</select>';

		/* translators: %1$s: opening link tag, %2$s: closing link tag */
		echo ' ' . wp_kses_post( sprintf( __( 'by %1$sWP Cron%2$s.', 'total-mail-queue' ), '<i><a href="https://developer.wordpress.org/plugins/cron/" target="_blank">', '</a></i>' ) ) . ' ';

		// Warn when any SMTP account has a per-cycle bulk higher than the global queue amount.
		global $wpdb;
		$smtp_table    = Schema::smtpTable();
		$global_amount = intval( $options['queue_amount'] );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exceeding = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from Schema::smtpTable()
				"SELECT `name`, `send_bulk` FROM `$smtp_table` WHERE `enabled` = 1 AND `send_bulk` > %d AND `send_bulk` > 0",
				$global_amount
			),
			ARRAY_A
		);
		if ( ! empty( $exceeding ) ) {
			$names = array_map(
				static function ( $a ) {
					return '<strong>' . esc_html( $a['name'] ) . '</strong> (' . intval( $a['send_bulk'] ) . ')';
				},
				$exceeding
			);
			echo '<br /><span class="tmq-warning">' . wp_kses_post(
				sprintf(
					/* translators: %1$d: global email limit, %2$s: list of SMTP account names */
					__( 'Warning: The following SMTP account(s) have a per-cycle limit higher than the global limit of %1$d: %2$s. The global limit will be applied as the ceiling.', 'total-mail-queue' ),
					$global_amount,
					implode( ', ', $names )
				)
			) . '</span>';
		}
	}

	/**
	 * Log retention row (clear_queue + log_max_records).
	 */
	public static function renderLog(): void {
		$options = Options::get();
		echo esc_html__( 'Delete Log entries older than', 'total-mail-queue' ) .
			' <input name="wp_tmq_settings[clear_queue]" type="number" min="1" value="' . esc_attr( (string) ( intval( $options['clear_queue'] ) / 24 ) ) . '" /> ' .
			esc_html__( 'days.', 'total-mail-queue' );
		echo '<br /><br />';
		echo esc_html__( 'Keep a maximum of', 'total-mail-queue' ) .
			' <input name="wp_tmq_settings[log_max_records]" type="number" min="0" value="' . esc_attr( (string) intval( $options['log_max_records'] ) ) . '" /> ' .
			esc_html__( 'log records.', 'total-mail-queue' );
		echo ' <span class="description">' . esc_html__( '0 = unlimited', 'total-mail-queue' ) . '</span>';
	}

	/**
	 * Auto-retry count.
	 */
	public static function renderRetry(): void {
		$options = Options::get();
		echo esc_html__( 'If sending fails, retry up to', 'total-mail-queue' ) .
			' <input name="wp_tmq_settings[max_retries]" type="number" min="0" value="' . esc_attr( (string) intval( $options['max_retries'] ) ) . '" /> ' .
			esc_html__( 'time(s) before marking as error.', 'total-mail-queue' );
		echo ' <span class="description">' . esc_html__( '0 = no retries, email is immediately marked as error', 'total-mail-queue' ) . '</span>';
	}

	/**
	 * Per-email SMTP timeout.
	 */
	public static function renderSmtpTimeout(): void {
		$options = Options::get();
		$timeout = intval( $options['smtp_timeout'] );
		echo '<input name="wp_tmq_settings[smtp_timeout]" type="number" min="5" step="1" value="' . esc_attr( (string) $timeout ) . '" /> ' . esc_html__( 'seconds', 'total-mail-queue' );
		echo '<p class="description">';
		/* translators: %s: recommended value */
		echo wp_kses_post( sprintf( __( 'Maximum time to wait for a response from the SMTP server per email. If the server does not respond within this time, the email is marked as failed and the batch continues to the next email. Recommended: %s seconds. Minimum: 5 seconds.', 'total-mail-queue' ), '<strong>30</strong>' ) );
		echo '</p>';
	}

	/**
	 * Cron lock TTL (advisory lock safety net).
	 */
	public static function renderCronLockTtl(): void {
		$options = Options::get();
		$ttl     = intval( $options['cron_lock_ttl'] );
		echo '<input name="wp_tmq_settings[cron_lock_ttl]" type="number" min="30" step="1" value="' . esc_attr( (string) $ttl ) . '" /> ' . esc_html__( 'seconds', 'total-mail-queue' );
		echo '<p class="description">';
		/* translators: %s: recommended value */
		echo wp_kses_post( sprintf( __( 'Maximum time a cron batch can hold the processing lock. If the batch finishes normally, the lock is released immediately. This timeout is a safety net: if PHP crashes mid-batch, the lock expires after this period and the queue resumes automatically. Recommended: %s seconds (5 minutes). Minimum: 30 seconds.', 'total-mail-queue' ), '<strong>300</strong>' ) );
		echo '</p>';
		echo '<p class="description tmq-warning-block">';
		echo esc_html__( 'Too low: the lock may expire while a large batch is still sending, allowing overlapping batches (risk of duplicate emails). Too high: if the process crashes, the queue will be blocked until the timeout expires.', 'total-mail-queue' );
		echo '</p>';
	}

	/**
	 * Send-method selector (auto / smtp-only / php).
	 */
	public static function renderSendMethod(): void {
		$options = Options::get();
		$method  = isset( $options['send_method'] ) ? (string) $options['send_method'] : 'auto';
		echo '<select name="wp_tmq_settings[send_method]">';
		echo '<option value="auto"' . selected( $method, 'auto', false ) . '>' . esc_html__( 'Automatic — Use plugin SMTP if available, then captured config, then WordPress default', 'total-mail-queue' ) . '</option>';
		echo '<option value="smtp"' . selected( $method, 'smtp', false ) . '>' . esc_html__( 'Plugin SMTP only — Only send via SMTP accounts configured in this plugin (hold emails if none available)', 'total-mail-queue' ) . '</option>';
		echo '<option value="php"' . selected( $method, 'php', false ) . '>' . esc_html__( 'WordPress default — Ignore plugin SMTP accounts, send via standard wp_mail()', 'total-mail-queue' ) . '</option>';
		echo '</select>';
		if ( 'smtp' === $method ) {
			echo ' <span class="description">' . esc_html__( 'Emails will wait in the retention queue until an SMTP account with available limits is found.', 'total-mail-queue' ) . '</span>';
		}
	}

	/**
	 * Alert recipient + threshold.
	 */
	public static function renderSensitivity(): void {
		$options = Options::get();
		echo esc_html__( 'Send alert to', 'total-mail-queue' ) .
			' <input type="text" name="wp_tmq_settings[email]" value="' . esc_attr( sanitize_email( (string) $options['email'] ) ) . '" /> ' .
			esc_html__( 'if more than', 'total-mail-queue' ) .
			' <input name="wp_tmq_settings[email_amount]" type="number" min="1" value="' . esc_attr( (string) intval( $options['email_amount'] ) ) . '" /> ' .
			wp_kses_post(
				sprintf(
					/* translators: %1$s: opening link tag, %2$s: closing link tag */
					__( 'email(s) in the %1$sQueue%2$s.', 'total-mail-queue' ),
					'<a href="admin.php?page=wp_tmq_mail_queue-tab-queue">',
					'</a>'
				)
			);
	}
}
