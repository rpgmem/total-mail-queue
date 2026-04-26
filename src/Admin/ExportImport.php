<?php
/**
 * XML export/import of plugin settings + SMTP accounts.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Admin;

use TotalMailQueue\Database\Schema;
use TotalMailQueue\Settings\Options;
use DOMDocument;
use SimpleXMLElement;

/**
 * Settings + SMTP-account export/import flow.
 *
 * `register()` wires the early `admin_init` hook so the export response can
 * stream raw XML before any output. The import side is invoked from the
 * Settings tab renderer once the request nonce has been verified there.
 */
final class ExportImport {

	/**
	 * Whitelist of setting keys allowed to be restored from an import.
	 *
	 * `tableName` and `smtpTableName` are deliberately excluded — they would
	 * let an attacker redirect SQL queries to arbitrary tables.
	 *
	 * @var array<int,string>
	 */
	private const ALLOWED_SETTING_KEYS = array(
		'enabled',
		'alert_enabled',
		'email',
		'email_amount',
		'queue_amount',
		'queue_interval',
		'queue_interval_unit',
		'clear_queue',
		'log_max_records',
		'send_method',
		'max_retries',
	);

	/**
	 * Hook the export handler onto admin_init.
	 */
	public static function register(): void {
		add_action( 'admin_init', array( self::class, 'maybeHandleExport' ) );
	}

	/**
	 * `admin_init` callback — handles the Export form submission.
	 */
	public static function maybeHandleExport(): void {
		if ( ! isset( $_POST['wp_tmq_export'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! isset( $_POST['wp_tmq_export_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['wp_tmq_export_nonce'] ) ), 'wp_tmq_export' ) ) {
			wp_die( esc_html__( 'Security check failed!', 'total-mail-queue' ) );
		}
		self::handleExport();
	}

	/**
	 * Stream the export file as a download. Always exits.
	 */
	public static function handleExport(): void {
		$xml      = self::buildExportXml();
		$filename = 'total-mail-queue-export-' . wp_date( 'Y-m-d-His' ) . '.xml';

		header( 'Content-Type: application/xml; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- XML download, not HTML context.
		echo $xml;
		exit;
	}

	/**
	 * Build the XML payload as a formatted string.
	 *
	 * Extracted so it can be unit-tested without triggering header()/exit().
	 */
	public static function buildExportXml(): string {
		global $wpdb;

		$smtp_table = Schema::smtpTable();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$smtp_accounts = $wpdb->get_results( "SELECT * FROM `$smtp_table`", ARRAY_A );

		if ( $smtp_accounts ) {
			foreach ( $smtp_accounts as &$account ) {
				unset( $account['id'] );
				$account['daily_sent']   = 0;
				$account['monthly_sent'] = 0;
			}
			unset( $account );
		}

		$settings = get_option( Options::OPTION_NAME, array() );

		$xml = new SimpleXMLElement( '<?xml version="1.0" encoding="UTF-8"?><total-mail-queue/>' );
		$xml->addAttribute( 'version', (string) get_option( 'wp_tmq_version', '' ) );
		$xml->addAttribute( 'exported_at', current_time( 'mysql', false ) );

		$settings_node = $xml->addChild( 'settings' );
		if ( is_array( $settings ) ) {
			foreach ( $settings as $key => $value ) {
				$settings_node->addChild( sanitize_key( (string) $key ), esc_xml( (string) $value ) );
			}
		}

		$smtp_node = $xml->addChild( 'smtp_accounts' );
		if ( $smtp_accounts ) {
			foreach ( $smtp_accounts as $account ) {
				$account_node = $smtp_node->addChild( 'account' );
				foreach ( $account as $key => $value ) {
					$account_node->addChild( sanitize_key( (string) $key ), esc_xml( (string) $value ) );
				}
			}
		}

		$dom                     = new DOMDocument( '1.0', 'UTF-8' );
		$dom->preserveWhiteSpace = false;
		$dom->formatOutput       = true;
		$dom->loadXML( $xml->asXML() );
		return (string) $dom->saveXML();
	}

	/**
	 * Handle a verified Import POST. Returns the notice HTML to render.
	 *
	 * The caller (the Settings page) is responsible for verifying the
	 * `wp_tmq_import` nonce before invoking this method.
	 */
	public static function handleImport(): string {
		global $wpdb;

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- nonce verified by caller.
		if ( ! isset( $_FILES['wp_tmq_import_file'] ) || ! isset( $_FILES['wp_tmq_import_file']['error'] ) || UPLOAD_ERR_OK !== $_FILES['wp_tmq_import_file']['error'] ) {
			return '<div class="notice notice-error"><p>' . esc_html__( 'Error uploading file. Please try again.', 'total-mail-queue' ) . '</p></div>';
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- reading the uploaded tmp file; nonce checked by caller.
		$file_content = file_get_contents( sanitize_text_field( wp_unslash( $_FILES['wp_tmq_import_file']['tmp_name'] ) ) );

		$use_errors = libxml_use_internal_errors( true );
		$xml        = simplexml_load_string( (string) $file_content, 'SimpleXMLElement', LIBXML_NONET );
		libxml_use_internal_errors( $use_errors );

		if ( ! $xml ) {
			return '<div class="notice notice-error"><p>' . esc_html__( 'Invalid XML file.', 'total-mail-queue' ) . '</p></div>';
		}

		if ( 'total-mail-queue' !== $xml->getName() ) {
			return '<div class="notice notice-error"><p>' . esc_html__( 'This file is not a valid Total Mail Queue export.', 'total-mail-queue' ) . '</p></div>';
		}

		if ( isset( $xml->settings ) ) {
			$current = get_option( Options::OPTION_NAME, array() );
			if ( ! is_array( $current ) ) {
				$current = array();
			}
			foreach ( $xml->settings->children() as $node ) {
				$key = $node->getName();
				if ( in_array( $key, self::ALLOWED_SETTING_KEYS, true ) ) {
					$current[ $key ] = (string) $node;
				}
			}
			if ( ! empty( $current ) ) {
				update_option( Options::OPTION_NAME, $current );
			}
		}

		if ( isset( $xml->smtp_accounts ) ) {
			$smtp_table = Schema::smtpTable();
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$columns = $wpdb->get_col( "DESCRIBE `$smtp_table`", 0 );

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( "TRUNCATE TABLE `$smtp_table`" );

			foreach ( $xml->smtp_accounts->account as $account_node ) {
				$account = array();
				foreach ( $account_node->children() as $field ) {
					$account[ $field->getName() ] = (string) $field;
				}
				unset( $account['id'] );
				$account = array_intersect_key( $account, array_flip( $columns ) );
				if ( ! empty( $account ) ) {
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->insert( $smtp_table, $account );
				}
			}
		}

		// Refresh the legacy global so the rest of the request sees the new values.
		$GLOBALS['wp_tmq_options'] = Options::get();

		$exported_at = (string) ( $xml['exported_at'] ?? '' );
		/* translators: %s: export date */
		$date_info = $exported_at ? ' ' . sprintf( __( '(exported on %s)', 'total-mail-queue' ), esc_html( $exported_at ) ) : '';
		return '<div class="notice notice-success"><p>' . esc_html__( 'Settings and SMTP accounts imported successfully.', 'total-mail-queue' ) . $date_info . '</p></div>';
	}
}
