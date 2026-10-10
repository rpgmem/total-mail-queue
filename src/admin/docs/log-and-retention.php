<?php
/**
 * Documentation topic: Log and retention.
 *
 * Included by {@see \TotalMailQueue\Admin\Documentation::render()}.
 *
 * @package TotalMailQueue
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<p><?php esc_html_e( 'Retention lists the emails still waiting to be sent. Log lists everything the plugin has handled: sent, failed, blocked and its own alerts.', 'total-mail-queue' ); ?></p>

<h3><?php esc_html_e( 'Statuses', 'total-mail-queue' ); ?></h3>
<table class="widefat striped tmq-docs-table">
	<tbody>
		<tr>
			<th scope="row"><span class="tmq-status tmq-status-queue"><?php esc_html_e( 'Queue', 'total-mail-queue' ); ?></span></th>
			<td><?php esc_html_e( 'Waiting for its turn. A "High" badge beside it means it will go before ordinary mail.', 'total-mail-queue' ); ?></td>
		</tr>
		<tr>
			<th scope="row"><span class="tmq-status tmq-status-sent"><?php esc_html_e( 'Sent', 'total-mail-queue' ); ?></span></th>
			<td><?php esc_html_e( 'Handed to the mail server, which accepted it. Delivery to the inbox is up to that server from here.', 'total-mail-queue' ); ?></td>
		</tr>
		<tr>
			<th scope="row"><span class="tmq-status tmq-status-error"><?php esc_html_e( 'Error', 'total-mail-queue' ); ?></span></th>
			<td><?php esc_html_e( 'Every attempt failed. "Error details" in the Info column shows why; turn on SMTP Debug Log in Settings to record the whole conversation with the server.', 'total-mail-queue' ); ?></td>
		</tr>
		<tr>
			<th scope="row"><span class="tmq-status tmq-status-blocked-by-source"><?php esc_html_e( 'Blocked by source', 'total-mail-queue' ); ?></span></th>
			<td><?php esc_html_e( 'Its source is disabled in the Sources tab, so it was kept and never sent.', 'total-mail-queue' ); ?></td>
		</tr>
		<tr>
			<th scope="row"><span class="tmq-status tmq-status-alert"><?php esc_html_e( 'Alert', 'total-mail-queue' ); ?></span></th>
			<td><?php esc_html_e( 'An alert the plugin sent you because the queue grew past your threshold.', 'total-mail-queue' ); ?></td>
		</tr>
	</tbody>
</table>

<h3><?php esc_html_e( 'Actions', 'total-mail-queue' ); ?></h3>
<ul class="tmq-docs-list">
	<li><?php esc_html_e( 'Resend puts a failed, blocked or alert email back in the queue as a new entry. Emails already sent, and emails still waiting, are skipped.', 'total-mail-queue' ); ?></li>
	<li><?php esc_html_e( 'Force Resend is for emails marked Error: it queues them again with a fresh retry count and keeps the original error in the Info column.', 'total-mail-queue' ); ?></li>
	<li><?php esc_html_e( 'An email that had attachments cannot be resent: attachments are kept only until the email is sent.', 'total-mail-queue' ); ?></li>
	<li><?php esc_html_e( 'Delete removes the selected entries for good.', 'total-mail-queue' ); ?></li>
</ul>

<h3><?php esc_html_e( 'Finding an email', 'total-mail-queue' ); ?></h3>
<p><?php esc_html_e( 'Filter the log by status, or by source from the Sources tab ("Filter log"). Each row opens the message, its headers and its attachments in place.', 'total-mail-queue' ); ?></p>
<p><?php esc_html_e( 'Screen Options, at the top right of the page, chooses which columns you see. Headers and Attachments start hidden to leave room for the rest; your choice is saved for your user only.', 'total-mail-queue' ); ?></p>

<h3><?php esc_html_e( 'Clean-up', 'total-mail-queue' ); ?></h3>
<p><?php esc_html_e( 'Settings → Log removes entries older than a number of days and can also cap the log at a number of entries (0 means no cap). Clean-up never touches emails that are still waiting.', 'total-mail-queue' ); ?></p>
