<?php
/**
 * Add / Edit form renderer for the SMTP Accounts admin tab.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Admin\Pages;

/**
 * Renders the big SMTP-account form (Add or Edit). Pulled out of {@see SmtpPage}
 * so the page itself stays a thin router; the connection-lock toggle and
 * autofill-decoy plumbing live here alongside the field markup they belong to.
 */
final class SmtpFormRenderer {

	/**
	 * Default account row used as a template for the Add view and as the
	 * fallback when an Edit row can't be found in the table.
	 *
	 * @return array<string,mixed>
	 */
	private static function defaultAccount(): array {
		return array(
			'id'            => 0,
			'name'          => '',
			'host'          => '',
			'port'          => 587,
			'encryption'    => 'tls',
			'auth'          => 1,
			'username'      => '',
			'password'      => '',
			'from_email'    => '',
			'from_name'     => '',
			'priority'      => 0,
			'daily_limit'   => 0,
			'monthly_limit' => 0,
			'send_interval' => 0,
			'send_bulk'     => 0,
			'enabled'       => 1,
		);
	}

	/**
	 * Render the Add or Edit form.
	 *
	 * @param string $smtp_table Fully prefixed SMTP table name.
	 * @param string $action     `add` or `edit`.
	 * @param int    $edit_id    Account being edited (0 for `add`).
	 */
	public static function render( string $smtp_table, string $action, int $edit_id ): void {
		global $wpdb;

		$account = self::defaultAccount();

		if ( 'edit' === $action && 0 < $edit_id ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `$smtp_table` WHERE `id` = %d", $edit_id ), ARRAY_A );
			if ( $row ) {
				$account = $row;
			}
		}

		$is_edit    = ( 'edit' === $action && 0 < $edit_id );
		$form_title = $is_edit ? __( 'Edit SMTP Account', 'total-mail-queue' ) : __( 'Add SMTP Account', 'total-mail-queue' );

		echo '<div class="tmq-box">';
		echo '<h3>' . esc_html( $form_title ) . '</h3>';
		echo '<form method="post" autocomplete="off" action="' . esc_url( admin_url( 'admin.php?page=wp_tmq_mail_queue-tab-smtp' ) ) . '">';
		// Hidden decoy fields to absorb browser autofill (positioned off-screen via .tmq-autofill-decoy in admin.css).
		echo '<div class="tmq-autofill-decoy" aria-hidden="true">';
		echo '<input type="text" name="tmq_decoy_user" tabindex="-1" />';
		echo '<input type="password" name="tmq_decoy_pass" tabindex="-1" />';
		echo '</div>';
		wp_nonce_field( 'wp_tmq_smtp_save', 'wp_tmq_smtp_nonce' );
		if ( $is_edit ) {
			echo '<input type="hidden" name="smtp_id" value="' . esc_attr( (string) $account['id'] ) . '" />';
		}
		echo '<table class="form-table">';

		// Name.
		echo '<tr>';
		echo '<th scope="row"><label for="smtp_name">' . esc_html__( 'Name', 'total-mail-queue' ) . '</label></th>';
		echo '<td><input type="text" id="smtp_name" name="smtp_name" value="' . esc_attr( (string) $account['name'] ) . '" class="regular-text" /></td>';
		echo '</tr>';

		// Connection lock toggle (only on edit).
		if ( $is_edit ) {
			echo '<tr>';
			echo '<th scope="row">' . esc_html__( 'Connection Settings', 'total-mail-queue' ) . '</th>';
			echo '<td><label><input type="checkbox" id="tmq-unlock-conn" /> ' . esc_html__( 'Unlock connection fields for editing', 'total-mail-queue' ) . '</label>';
			echo ' <span class="description">' . esc_html__( 'Keep locked to safely change only limits and other options.', 'total-mail-queue' ) . '</span></td>';
			echo '</tr>';
		}

		// Host.
		if ( $is_edit ) {
			echo '<tr class="tmq-conn-row tmq-conn-locked">';
		} else {
			echo '<tr class="tmq-conn-row">';
		}
		echo '<th scope="row"><label for="smtp_host">' . esc_html__( 'Host', 'total-mail-queue' ) . '</label></th>';
		if ( $is_edit ) {
			echo '<td><input type="text" id="smtp_host" name="smtp_host" value="' . esc_attr( (string) $account['host'] ) . '" class="regular-text tmq-conn-field" disabled="disabled" /></td>';
		} else {
			echo '<td><input type="text" id="smtp_host" name="smtp_host" value="' . esc_attr( (string) $account['host'] ) . '" class="regular-text tmq-conn-field" /></td>';
		}
		echo '</tr>';

		// Port.
		if ( $is_edit ) {
			echo '<tr class="tmq-conn-row tmq-conn-locked">';
			echo '<th scope="row"><label for="smtp_port">' . esc_html__( 'Port', 'total-mail-queue' ) . '</label></th>';
			echo '<td><input type="number" id="smtp_port" name="smtp_port" value="' . esc_attr( (string) $account['port'] ) . '" min="1" max="65535" class="tmq-conn-field" disabled="disabled" /></td>';
		} else {
			echo '<tr class="tmq-conn-row">';
			echo '<th scope="row"><label for="smtp_port">' . esc_html__( 'Port', 'total-mail-queue' ) . '</label></th>';
			echo '<td><input type="number" id="smtp_port" name="smtp_port" value="' . esc_attr( (string) $account['port'] ) . '" min="1" max="65535" class="tmq-conn-field" /></td>';
		}
		echo '</tr>';

		// Encryption.
		if ( $is_edit ) {
			echo '<tr class="tmq-conn-row tmq-conn-locked">';
			echo '<th scope="row"><label for="smtp_encryption">' . esc_html__( 'Encryption', 'total-mail-queue' ) . '</label></th>';
			echo '<td><select id="smtp_encryption" name="smtp_encryption" class="tmq-conn-field" disabled="disabled">';
		} else {
			echo '<tr class="tmq-conn-row">';
			echo '<th scope="row"><label for="smtp_encryption">' . esc_html__( 'Encryption', 'total-mail-queue' ) . '</label></th>';
			echo '<td><select id="smtp_encryption" name="smtp_encryption" class="tmq-conn-field">';
		}
		echo '<option value="none"' . selected( $account['encryption'], 'none', false ) . '>' . esc_html__( 'None', 'total-mail-queue' ) . '</option>';
		echo '<option value="tls"' . selected( $account['encryption'], 'tls', false ) . '>' . esc_html__( 'TLS', 'total-mail-queue' ) . '</option>';
		echo '<option value="ssl"' . selected( $account['encryption'], 'ssl', false ) . '>' . esc_html__( 'SSL', 'total-mail-queue' ) . '</option>';
		echo '</select></td>';
		echo '</tr>';

		// Auth.
		if ( $is_edit ) {
			echo '<tr class="tmq-conn-row tmq-conn-locked">';
			echo '<th scope="row"><label for="smtp_auth">' . esc_html__( 'Authentication', 'total-mail-queue' ) . '</label></th>';
			echo '<td><input type="checkbox" id="smtp_auth" name="smtp_auth" value="1"' . checked( $account['auth'], 1, false ) . ' class="tmq-conn-field" disabled="disabled" /></td>';
		} else {
			echo '<tr class="tmq-conn-row">';
			echo '<th scope="row"><label for="smtp_auth">' . esc_html__( 'Authentication', 'total-mail-queue' ) . '</label></th>';
			echo '<td><input type="checkbox" id="smtp_auth" name="smtp_auth" value="1"' . checked( $account['auth'], 1, false ) . ' class="tmq-conn-field" /></td>';
		}
		echo '</tr>';

		// Username.
		if ( $is_edit ) {
			echo '<tr class="tmq-conn-row tmq-conn-locked">';
			echo '<th scope="row"><label for="smtp_username">' . esc_html__( 'Username', 'total-mail-queue' ) . '</label></th>';
			echo '<td><input type="text" id="smtp_username" name="smtp_username" value="' . esc_attr( (string) $account['username'] ) . '" class="regular-text tmq-conn-field tmq-no-autofill" autocomplete="off" disabled="disabled" /></td>';
		} else {
			echo '<tr class="tmq-conn-row">';
			echo '<th scope="row"><label for="smtp_username">' . esc_html__( 'Username', 'total-mail-queue' ) . '</label></th>';
			echo '<td><input type="text" id="smtp_username" name="smtp_username" value="' . esc_attr( (string) $account['username'] ) . '" class="regular-text tmq-conn-field tmq-no-autofill" autocomplete="off" readonly="readonly" /></td>';
		}
		echo '</tr>';

		// Password.
		if ( $is_edit ) {
			echo '<tr class="tmq-conn-row tmq-conn-locked">';
		} else {
			echo '<tr class="tmq-conn-row">';
		}
		echo '<th scope="row"><label for="smtp_password">' . esc_html__( 'Password', 'total-mail-queue' ) . '</label></th>';
		echo '<td><input type="password" id="smtp_password" name="smtp_password" value="" class="regular-text tmq-conn-field tmq-no-autofill" autocomplete="off"' . ( $is_edit ? ' disabled="disabled"' : ' readonly="readonly"' );
		if ( $is_edit && ! empty( $account['password'] ) ) {
			echo ' placeholder="' . esc_attr( str_repeat( "\xE2\x80\xA2", 8 ) ) . '"';
		}
		echo ' />';
		if ( $is_edit && ! empty( $account['password'] ) ) {
			echo ' <span class="description">' . esc_html__( 'Leave blank to keep the current password.', 'total-mail-queue' ) . '</span>';
		}
		echo '</td>';
		echo '</tr>';

		// From Email.
		echo '<tr>';
		echo '<th scope="row"><label for="smtp_from_email">' . esc_html__( 'From Email', 'total-mail-queue' ) . '</label></th>';
		echo '<td><input type="email" id="smtp_from_email" name="smtp_from_email" value="' . esc_attr( (string) $account['from_email'] ) . '" class="regular-text" />';
		echo '<p class="description">' . wp_kses_post(
			sprintf(
				/* translators: %1$s: opening Settings link tag, %2$s: closing tag */
				__( 'Wins over the global Default Sender configured in %1$sSettings%2$s when this SMTP account is sending the email. Leave blank to fall back to the Default Sender.', 'total-mail-queue' ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=wp_tmq_mail_queue' ) ) . '">',
				'</a>'
			)
		) . '</p>';
		echo '</td>';
		echo '</tr>';

		// From Name.
		echo '<tr>';
		echo '<th scope="row"><label for="smtp_from_name">' . esc_html__( 'From Name', 'total-mail-queue' ) . '</label></th>';
		echo '<td><input type="text" id="smtp_from_name" name="smtp_from_name" value="' . esc_attr( (string) $account['from_name'] ) . '" class="regular-text" />';
		echo '<p class="description">' . esc_html__( 'Same precedence as From Email above — wins over the Default Sender when this account is sending.', 'total-mail-queue' ) . '</p>';
		echo '</td>';
		echo '</tr>';

		// Priority.
		echo '<tr>';
		echo '<th scope="row"><label for="smtp_priority">' . esc_html__( 'Priority', 'total-mail-queue' ) . '</label></th>';
		echo '<td><input type="number" id="smtp_priority" name="smtp_priority" value="' . esc_attr( (string) $account['priority'] ) . '" min="0" /> ';
		echo '<span class="description">' . esc_html__( 'Lower number = higher priority.', 'total-mail-queue' ) . '</span></td>';
		echo '</tr>';

		// Daily Limit.
		echo '<tr>';
		echo '<th scope="row"><label for="smtp_daily_limit">' . esc_html__( 'Daily Limit', 'total-mail-queue' ) . '</label></th>';
		echo '<td><input type="number" id="smtp_daily_limit" name="smtp_daily_limit" value="' . esc_attr( (string) $account['daily_limit'] ) . '" min="0" /> ';
		echo '<span class="description">' . esc_html__( '0 = unlimited', 'total-mail-queue' ) . '</span></td>';
		echo '</tr>';

		// Monthly Limit.
		echo '<tr>';
		echo '<th scope="row"><label for="smtp_monthly_limit">' . esc_html__( 'Monthly Limit', 'total-mail-queue' ) . '</label></th>';
		echo '<td><input type="number" id="smtp_monthly_limit" name="smtp_monthly_limit" value="' . esc_attr( (string) $account['monthly_limit'] ) . '" min="0" /> ';
		echo '<span class="description">' . esc_html__( '0 = unlimited', 'total-mail-queue' ) . '</span></td>';
		echo '</tr>';

		// Send Interval.
		echo '<tr>';
		echo '<th scope="row"><label for="smtp_send_interval">' . esc_html__( 'Send Interval (minutes)', 'total-mail-queue' ) . '</label></th>';
		echo '<td><input type="number" id="smtp_send_interval" name="smtp_send_interval" value="' . esc_attr( (string) $account['send_interval'] ) . '" min="0" /> ';
		echo '<span class="description">' . esc_html__( 'Minimum minutes between sending cycles for this account. 0 = use global interval.', 'total-mail-queue' ) . '</span></td>';
		echo '</tr>';

		// Send Bulk.
		echo '<tr>';
		echo '<th scope="row"><label for="smtp_send_bulk">' . esc_html__( 'Emails per Cycle', 'total-mail-queue' ) . '</label></th>';
		echo '<td><input type="number" id="smtp_send_bulk" name="smtp_send_bulk" value="' . esc_attr( (string) $account['send_bulk'] ) . '" min="0" /> ';
		echo '<span class="description">' . esc_html__( 'Maximum emails to send per cycle for this account. 0 = use global limit.', 'total-mail-queue' ) . '</span></td>';
		echo '</tr>';

		// Enabled.
		echo '<tr>';
		echo '<th scope="row"><label for="smtp_enabled">' . esc_html__( 'Enabled', 'total-mail-queue' ) . '</label></th>';
		echo '<td><input type="checkbox" id="smtp_enabled" name="smtp_enabled" value="1"' . checked( $account['enabled'], 1, false ) . ' /></td>';
		echo '</tr>';

		echo '</table>';
		echo '<p class="submit">';
		echo '<input type="submit" name="wp_tmq_smtp_save" class="button button-primary" value="' . esc_attr__( 'Save SMTP Account', 'total-mail-queue' ) . '" /> ';
		echo '<button type="button" id="tmq-test-smtp" class="button" data-smtp-id="' . esc_attr( (string) $account['id'] ) . '">' . esc_html__( 'Test Connection', 'total-mail-queue' ) . '</button> ';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=wp_tmq_mail_queue-tab-smtp' ) ) . '" class="button">' . esc_html__( 'Cancel', 'total-mail-queue' ) . '</a>';
		echo '</p>';
		echo '<div id="tmq-test-smtp-result"></div>';
		echo '</form>';
		echo '</div>';
	}
}
