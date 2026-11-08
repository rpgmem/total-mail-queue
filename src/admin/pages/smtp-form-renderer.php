<?php
/**
 * Add / Edit form renderer for the SMTP Accounts admin tab.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Admin\Pages;

use TotalMailQueue\Admin\Button;
use TotalMailQueue\Admin\Icons;
use TotalMailQueue\Admin\Toggle;
use TotalMailQueue\Smtp\M365SecretExpiry;
use TotalMailQueue\Smtp\M365Token;
use TotalMailQueue\Smtp\Repository as SmtpRepository;

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
			'id'              => 0,
			'name'            => '',
			'host'            => '',
			'port'            => 587,
			'encryption'      => 'tls',
			'auth'            => 1,
			'auth_type'       => 'password',
			'username'        => '',
			'password'        => '',
			'oauth_tenant'    => '',
			'oauth_client_id' => '',
			'from_email'      => '',
			'from_name'       => '',
			'priority'        => 0,
			'daily_limit'     => 0,
			'monthly_limit'   => 0,
			'send_interval'   => 0,
			'send_bulk'       => 0,
			'enabled'         => 1,
		);
	}

	/**
	 * Render the Add or Edit form.
	 *
	 * @param string $action  `add` or `edit`.
	 * @param int    $edit_id Account being edited (0 for `add`).
	 */
	public static function render( string $action, int $edit_id ): void {
		$account = self::defaultAccount();

		if ( 'edit' === $action && 0 < $edit_id ) {
			$row = SmtpRepository::findById( $edit_id );
			if ( null !== $row ) {
				$account = $row;
			}
		}

		$is_edit    = ( 'edit' === $action && 0 < $edit_id );
		$form_title = $is_edit ? __( 'Edit SMTP Account', 'total-mail-queue' ) : __( 'Add SMTP Account', 'total-mail-queue' );

		echo '<div class="tmq-box tmq-settings-card">';
		echo Icons::heading( 'smtp', $form_title ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons::heading() escapes the title and emits fixed markup.
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
		$m365 = M365Token::AUTH_TYPE === (string) ( $account['auth_type'] ?? '' );
		// The class picks which sign-in rows show; tmq-admin.js flips it with the method.
		echo '<table class="form-table tmq-smtp-form' . ( $m365 ? ' tmq-auth-m365' : '' ) . '">';

		// Name.
		echo '<tr>';
		echo '<th scope="row"><label for="smtp_name">' . esc_html__( 'Name', 'total-mail-queue' ) . '</label></th>';
		echo '<td><input type="text" id="smtp_name" name="smtp_name" value="' . esc_attr( (string) $account['name'] ) . '" class="regular-text" /></td>';
		echo '</tr>';

		// Connection lock toggle (only on edit).
		if ( $is_edit ) {
			echo '<tr>';
			echo '<th scope="row">' . esc_html__( 'Connection Settings', 'total-mail-queue' ) . '</th>';
			echo '<td>';
			// A screen control, not a field: it has no name and is never saved.
			Toggle::render(
				array(
					'name'  => 'tmq_unlock_conn',
					'id'    => 'tmq-unlock-conn',
					'label' => __( 'Unlock connection fields for editing', 'total-mail-queue' ),
				)
			);
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
			echo '<td>' . self::authToggle( $account, true ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Toggle::html().
		} else {
			echo '<tr class="tmq-conn-row">';
			echo '<th scope="row"><label for="smtp_auth">' . esc_html__( 'Authentication', 'total-mail-queue' ) . '</label></th>';
			echo '<td>' . self::authToggle( $account, false ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Toggle::html().
		}
		echo '</tr>';

		self::renderSignInMethod( $m365, $is_edit );

		// Username.
		if ( $is_edit ) {
			echo '<tr class="tmq-conn-row tmq-conn-locked">';
			echo '<th scope="row"><label for="smtp_username">' . self::usernameLabel() . '</label></th>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- usernameLabel() escapes both labels.
			echo '<td><input type="text" id="smtp_username" name="smtp_username" value="' . esc_attr( (string) $account['username'] ) . '" class="regular-text tmq-conn-field tmq-no-autofill" autocomplete="off" disabled="disabled" />' . self::mailboxHint() . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- mailboxHint() escapes its text.
		} else {
			echo '<tr class="tmq-conn-row">';
			echo '<th scope="row"><label for="smtp_username">' . self::usernameLabel() . '</label></th>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- usernameLabel() escapes both labels.
			echo '<td><input type="text" id="smtp_username" name="smtp_username" value="' . esc_attr( (string) $account['username'] ) . '" class="regular-text tmq-conn-field tmq-no-autofill" autocomplete="off" readonly="readonly" />' . self::mailboxHint() . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- mailboxHint() escapes its text.
		}
		echo '</tr>';

		// Password.
		if ( $is_edit ) {
			echo '<tr class="tmq-conn-row tmq-conn-locked tmq-password-only">';
		} else {
			echo '<tr class="tmq-conn-row tmq-password-only">';
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

		self::renderM365Rows( $account, $is_edit );

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
		echo '<td>';
		Toggle::render(
			array(
				'name'    => 'smtp_enabled',
				'id'      => 'smtp_enabled',
				'checked' => 1 === (int) $account['enabled'],
			)
		);
		echo '</td>';
		echo '</tr>';

		echo '</table>';
		echo '<p class="submit">';
		Button::render(
			array(
				'label'    => __( 'Save SMTP Account', 'total-mail-queue' ),
				'category' => 'primary',
				'icon'     => 'save',
				'type'     => 'submit',
				'name'     => 'wp_tmq_smtp_save',
			)
		);
		echo ' ';
		Button::render(
			array(
				'label' => __( 'Test Connection', 'total-mail-queue' ),
				'icon'  => 'network',
				'id'    => 'tmq-test-smtp',
				'attrs' => array( 'data-smtp-id' => (string) $account['id'] ),
			)
		);
		echo ' ';
		Button::render(
			array(
				'label' => __( 'Cancel', 'total-mail-queue' ),
				'icon'  => 'cross',
				'href'  => admin_url( 'admin.php?page=wp_tmq_mail_queue-tab-smtp' ),
			)
		);
		echo '</p>';
		echo '<div id="tmq-test-smtp-result"></div>';
		echo '</form>';
		echo '</div>';
	}

	/**
	 * The sign-in method: a password, or the Microsoft 365 app (#186).
	 *
	 * @param bool $m365    Whether the account uses the Microsoft 365 app.
	 * @param bool $is_edit Whether the connection fields start locked.
	 */
	private static function renderSignInMethod( bool $m365, bool $is_edit ): void {
		$disabled = $is_edit ? ' disabled="disabled"' : '';
		echo '<tr class="tmq-conn-row' . ( $is_edit ? ' tmq-conn-locked' : '' ) . '">';
		echo '<th scope="row">' . esc_html__( 'Sign-in method', 'total-mail-queue' ) . '</th>';
		echo '<td><fieldset class="tmq-sign-in-method"><legend class="screen-reader-text">' . esc_html__( 'Sign-in method', 'total-mail-queue' ) . '</legend>';
		foreach ( array(
			'password'           => __( 'Password', 'total-mail-queue' ),
			M365Token::AUTH_TYPE => __( 'Microsoft 365 app', 'total-mail-queue' ),
		) as $value => $label ) {
			$checked = ( M365Token::AUTH_TYPE === $value ) === $m365;
			echo '<label><input type="radio" name="smtp_auth_type" value="' . esc_attr( $value ) . '" class="tmq-conn-field"' . checked( $checked, true, false ) . $disabled . ' /> ' . esc_html( $label ) . '</label> '; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $disabled is a fixed attribute string.
		}
		echo '</fieldset>';
		echo '<p class="description">' . esc_html__( 'The Microsoft 365 app signs in with a token from Microsoft; no mailbox password is stored. Choosing it fills in smtp.office365.com, 587 and TLS when the host is empty.', 'total-mail-queue' ) . '</p>';
		echo '</td></tr>';
	}

	/**
	 * The Microsoft 365 app's fields and its set-up steps (#186).
	 *
	 * @param array<string,mixed> $account Account row.
	 * @param bool                $is_edit Whether the connection fields start locked.
	 */
	private static function renderM365Rows( array $account, bool $is_edit ): void {
		$row      = '<tr class="tmq-conn-row tmq-m365-only' . ( $is_edit ? ' tmq-conn-locked' : '' ) . '">';
		$disabled = $is_edit ? ' disabled="disabled"' : '';
		foreach ( array(
			'oauth_tenant'    => array( __( 'Tenant ID', 'total-mail-queue' ), __( 'Directory (tenant) ID, from the app\'s overview in Microsoft Entra, or the organisation\'s domain.', 'total-mail-queue' ) ),
			'oauth_client_id' => array( __( 'Client ID', 'total-mail-queue' ), __( 'Application (client) ID.', 'total-mail-queue' ) ),
		) as $key => $spec ) {
			echo $row; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the row opening tag is a fixed string.
			echo '<th scope="row"><label for="smtp_' . esc_attr( $key ) . '">' . esc_html( $spec[0] ) . '</label></th>';
			echo '<td><input type="text" id="smtp_' . esc_attr( $key ) . '" name="smtp_' . esc_attr( $key ) . '" value="' . esc_attr( (string) ( $account[ $key ] ?? '' ) ) . '" class="regular-text tmq-conn-field" autocomplete="off"' . $disabled . ' />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $disabled is a fixed attribute string.
			echo '<p class="description">' . esc_html( $spec[1] ) . '</p></td></tr>';
		}

		$has_secret = '' !== (string) ( $account['oauth_client_secret'] ?? '' );
		echo $row; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the row opening tag is a fixed string.
		echo '<th scope="row"><label for="smtp_oauth_client_secret">' . esc_html__( 'Client secret', 'total-mail-queue' ) . '</label></th>';
		echo '<td><input type="password" id="smtp_oauth_client_secret" name="smtp_oauth_client_secret" value="" class="regular-text tmq-conn-field tmq-no-autofill" autocomplete="off"' . ( $is_edit ? ' disabled="disabled"' : ' readonly="readonly"' ) . ( $has_secret ? ' placeholder="' . esc_attr( str_repeat( "\xE2\x80\xA2", 8 ) ) . '"' : '' ) . ' />';
		echo '<p class="description">' . esc_html__( 'Stored encrypted. Leave blank to keep the current one.', 'total-mail-queue' ) . '</p></td></tr>';

		// When the secret runs out (#188): optional, and locked with it.
		$expires = M365SecretExpiry::sanitizeDate( $account['oauth_secret_expires'] ?? '' );
		$status  = M365SecretExpiry::status(
			array_merge(
				$account,
				array(
					'enabled'   => 1,
					'auth'      => 1,
					'auth_type' => M365Token::AUTH_TYPE,
				)
			),
			time()
		);
		echo $row; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the row opening tag is a fixed string.
		echo '<th scope="row"><label for="smtp_oauth_secret_expires">' . esc_html__( 'Secret expires on', 'total-mail-queue' ) . '</label></th>';
		echo '<td><input type="date" id="smtp_oauth_secret_expires" name="smtp_oauth_secret_expires" value="' . esc_attr( $expires ) . '" class="tmq-conn-field"' . $disabled . ' />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $disabled is a fixed attribute string.
		if ( null !== $status ) {
			echo ' <span class="tmq-status tmq-status-secret-' . esc_attr( $status['state'] ) . '">' . esc_html( M365SecretExpiry::note( $status ) ) . '</span>';
		}
		/* translators: %d: days of warning */
		echo '<p class="description">' . esc_html( sprintf( __( 'Optional. The date under "Expires" in Microsoft Entra → Certificates & secrets. The plugin warns %d days before. When you save a new secret, update the date.', 'total-mail-queue' ), M365SecretExpiry::WARN_DAYS ) ) . '</p></td></tr>';

		echo '<tr class="tmq-m365-only"><td colspan="2"><details class="tmq-m365-setup"><summary>' . esc_html__( 'How to set this up in Microsoft 365', 'total-mail-queue' ) . '</summary><ol>';
		foreach ( array(
			__( 'In Microsoft Entra, register an app and create a client secret.', 'total-mail-queue' ),
			__( 'Under API permissions, add Office 365 Exchange Online → Application permissions → SMTP.SendAsApp, and grant admin consent.', 'total-mail-queue' ),
			__( 'In Exchange Online PowerShell, register the app\'s service principal (New-ServicePrincipal) and give it the mailbox (Add-MailboxPermission -AccessRights FullAccess).', 'total-mail-queue' ),
			__( 'Make sure SMTP AUTH is allowed for the mailbox.', 'total-mail-queue' ),
			__( 'Fill in the fields above, with the mailbox as Mailbox, and use Test Connection.', 'total-mail-queue' ),
		) as $step ) {
			echo '<li>' . esc_html( $step ) . '</li>';
		}
		echo '</ol></details></td></tr>';
	}

	/**
	 * The Username label, which reads "Mailbox" for the Microsoft 365 app.
	 */
	private static function usernameLabel(): string {
		return '<span class="tmq-password-only">' . esc_html__( 'Username', 'total-mail-queue' ) . '</span><span class="tmq-m365-only">' . esc_html__( 'Mailbox', 'total-mail-queue' ) . '</span>';
	}

	/**
	 * What the Mailbox field means for the Microsoft 365 app.
	 */
	private static function mailboxHint(): string {
		return '<p class="description tmq-m365-only">' . esc_html__( 'The address the emails are sent as.', 'total-mail-queue' ) . '</p>';
	}

	/**
	 * The Authentication switch. Part of the connection fields, which the
	 * edit screen keeps locked until "Unlock connection fields" is on.
	 *
	 * @param array<string,mixed> $account The account being edited.
	 * @param bool                $locked  Whether it starts disabled.
	 */
	private static function authToggle( array $account, bool $locked ): string {
		return Toggle::html(
			array(
				'name'        => 'smtp_auth',
				'id'          => 'smtp_auth',
				'checked'     => 1 === (int) $account['auth'],
				'disabled'    => $locked,
				'input_class' => 'tmq-conn-field',
			)
		);
	}
}
