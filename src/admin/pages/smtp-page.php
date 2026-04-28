<?php
/**
 * SMTP Accounts admin tab — list / add / edit / delete / reset counters.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Admin\Pages;

use TotalMailQueue\Database\Schema;

/**
 * Router for the "SMTP Accounts" tab.
 *
 * Invoked by {@see \TotalMailQueue\Admin\PluginPage::render()} once the page
 * slug matches `wp_tmq_mail_queue-tab-smtp`. Side effects (Save / Delete /
 * Reset Counters) are owned by {@see SmtpActions}; the form view by
 * {@see SmtpFormRenderer}; the list view by {@see SmtpListRenderer}. This
 * class only does the action-state routing between them.
 */
final class SmtpPage {

	/**
	 * Entry point — invoked by the parent renderer with `manage_options`
	 * already verified.
	 */
	public static function render(): void {
		$smtp_table = Schema::smtpTable();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing parameters, action handlers verify their own nonces.
		$action = isset( $_GET['smtp-action'] ) ? sanitize_key( wp_unslash( $_GET['smtp-action'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- same as above.
		$edit_id = isset( $_GET['smtp-id'] ) ? intval( $_GET['smtp-id'] ) : 0;

		$action_state = SmtpActions::handle( $smtp_table, $action, $edit_id );
		$action       = $action_state['action'];
		$edit_id      = $action_state['edit_id'];

		if ( 'add' === $action || 'edit' === $action ) {
			SmtpFormRenderer::render( $smtp_table, $action, $edit_id );
			return;
		}

		SmtpListRenderer::render( $smtp_table );
	}
}
