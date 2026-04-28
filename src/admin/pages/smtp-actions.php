<?php
/**
 * POST/GET action handlers for the SMTP Accounts admin tab.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Admin\Pages;

use TotalMailQueue\Support\Encryption;

/**
 * Handles the side-effecting half of the SMTP Accounts tab: Save (Add / Edit),
 * Delete, and Reset Counters. Pulled out of {@see SmtpPage} so the page itself
 * is just a router and the form / list views stay focused on rendering.
 *
 * Each handler verifies its own nonce, mutates the row directly, and emits
 * an admin notice. The router consumes the returned action/edit_id pair to
 * decide whether to fall through to the list view or stay on the form.
 */
final class SmtpActions {

	/**
	 * Run the right handler for the current request and return the action/id
	 * the caller should keep using on this render pass.
	 *
	 * Save resets the action so the caller falls through to the list view;
	 * Delete does the same; the Reset Counters handler is a side effect
	 * that doesn't change the routing.
	 *
	 * @param string $smtp_table Fully prefixed SMTP table name.
	 * @param string $action     Current `smtp-action` value.
	 * @param int    $edit_id    Current `smtp-id` value.
	 * @return array{action:string,edit_id:int}
	 */
	public static function handle( string $smtp_table, string $action, int $edit_id ): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- handleSave() verifies the nonce.
		if ( isset( $_POST['wp_tmq_smtp_save'] ) ) {
			self::handleSave( $smtp_table );
			return array(
				'action'  => '',
				'edit_id' => 0,
			);
		}

		if ( 'delete' === $action && 0 < $edit_id ) {
			self::handleDelete( $smtp_table, $edit_id );
			return array(
				'action'  => '',
				'edit_id' => 0,
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- handleResetCounters() verifies the nonce.
		if ( isset( $_POST['wp_tmq_smtp_reset_counters'] ) ) {
			self::handleResetCounters( $smtp_table );
		}

		return array(
			'action'  => $action,
			'edit_id' => $edit_id,
		);
	}

	/**
	 * Save handler — Add or Edit depending on whether `smtp_id` is set.
	 *
	 * @param string $smtp_table Fully prefixed SMTP table name.
	 */
	private static function handleSave( string $smtp_table ): void {
		global $wpdb;

		if ( ! isset( $_POST['wp_tmq_smtp_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['wp_tmq_smtp_nonce'] ) ), 'wp_tmq_smtp_save' ) ) {
			wp_die( esc_html__( 'Security check failed!', 'total-mail-queue' ) );
		}

		$save_id = isset( $_POST['smtp_id'] ) ? intval( $_POST['smtp_id'] ) : 0;

		$data = array(
			'name'          => sanitize_text_field( wp_unslash( $_POST['smtp_name'] ?? '' ) ),
			'from_email'    => sanitize_email( wp_unslash( $_POST['smtp_from_email'] ?? '' ) ),
			'from_name'     => sanitize_text_field( wp_unslash( $_POST['smtp_from_name'] ?? '' ) ),
			'priority'      => intval( $_POST['smtp_priority'] ?? 0 ),
			'daily_limit'   => intval( $_POST['smtp_daily_limit'] ?? 0 ),
			'monthly_limit' => intval( $_POST['smtp_monthly_limit'] ?? 0 ),
			'send_interval' => intval( $_POST['smtp_send_interval'] ?? 0 ),
			'send_bulk'     => intval( $_POST['smtp_send_bulk'] ?? 0 ),
			'enabled'       => isset( $_POST['smtp_enabled'] ) ? 1 : 0,
		);

		// Connection fields are submitted only when the lock is opened
		// (or on Add). When absent, existing DB values are preserved.
		if ( isset( $_POST['smtp_host'] ) ) {
			$data['host']       = sanitize_text_field( wp_unslash( $_POST['smtp_host'] ) );
			$data['port']       = intval( $_POST['smtp_port'] ?? 587 );
			$data['encryption'] = sanitize_key( wp_unslash( $_POST['smtp_encryption'] ?? 'tls' ) );
			$data['auth']       = isset( $_POST['smtp_auth'] ) ? 1 : 0;
			$data['username']   = sanitize_text_field( wp_unslash( $_POST['smtp_username'] ?? '' ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- password must preserve special characters.
		$raw_password = isset( $_POST['smtp_password'] ) ? wp_unslash( $_POST['smtp_password'] ) : '';
		if ( '' !== $raw_password ) {
			$data['password'] = Encryption::encrypt( (string) $raw_password );
		}

		if ( $save_id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update( $smtp_table, $data, array( 'id' => $save_id ), null, '%d' );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'SMTP account updated.', 'total-mail-queue' ) . '</p></div>';
			return;
		}

		if ( ! isset( $data['password'] ) ) {
			$data['password'] = '';
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->insert( $smtp_table, $data );
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'SMTP account added.', 'total-mail-queue' ) . '</p></div>';
	}

	/**
	 * Delete handler.
	 *
	 * @param string $smtp_table Fully prefixed SMTP table name.
	 * @param int    $edit_id    Account being deleted.
	 */
	private static function handleDelete( string $smtp_table, int $edit_id ): void {
		global $wpdb;

		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'wp_tmq_smtp_delete_' . $edit_id ) ) {
			wp_die( esc_html__( 'Security check failed!', 'total-mail-queue' ) );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $smtp_table, array( 'id' => $edit_id ), '%d' );
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'SMTP account deleted.', 'total-mail-queue' ) . '</p></div>';
	}

	/**
	 * Reset Counters handler — zeros `daily_sent` and `monthly_sent` on every row.
	 *
	 * @param string $smtp_table Fully prefixed SMTP table name.
	 */
	private static function handleResetCounters( string $smtp_table ): void {
		global $wpdb;

		if ( ! isset( $_POST['wp_tmq_smtp_reset_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['wp_tmq_smtp_reset_nonce'] ) ), 'wp_tmq_smtp_reset_counters' ) ) {
			wp_die( esc_html__( 'Security check failed!', 'total-mail-queue' ) );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "UPDATE `$smtp_table` SET `daily_sent` = 0, `monthly_sent` = 0" );
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'All sending counters have been reset.', 'total-mail-queue' ) . '</p></div>';
	}
}
