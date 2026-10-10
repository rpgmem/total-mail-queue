<?php
/**
 * Documentation topic: SMTP accounts.
 *
 * Included by {@see \TotalMailQueue\Admin\Documentation::render()}.
 *
 * @package TotalMailQueue
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<p><?php esc_html_e( 'An SMTP account is a mail server the plugin sends through: your provider\'s, a transactional service such as Mailgun, or your own. You can add several and let the plugin share the load between them.', 'total-mail-queue' ); ?></p>

<h3><?php esc_html_e( 'Connection', 'total-mail-queue' ); ?></h3>
<p><?php esc_html_e( 'Host, port, encryption (none, TLS or SSL), and the user name and password if the server asks for them. The password is stored encrypted; leave the field blank when editing to keep the one already saved. Test Connection checks the settings without sending anything.', 'total-mail-queue' ); ?></p>
<p><?php esc_html_e( 'When you edit an account, the connection fields start locked, so you can change its limits without touching how it connects. Switch on "Unlock connection fields for editing" to change them.', 'total-mail-queue' ); ?></p>
<p><?php esc_html_e( 'From Email and From Name, when filled, win over the Default Sender in Settings for every email this account sends.', 'total-mail-queue' ); ?></p>

<h3><?php esc_html_e( 'Limits', 'total-mail-queue' ); ?></h3>
<table class="widefat striped tmq-docs-table">
	<tbody>
		<tr>
			<th scope="row"><?php esc_html_e( 'Daily Limit, Monthly Limit', 'total-mail-queue' ); ?></th>
			<td><?php esc_html_e( 'The most this account sends in a calendar day or month. 0 means no limit. The counts start over when the day or the month changes.', 'total-mail-queue' ); ?></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Emails per Cycle', 'total-mail-queue' ); ?></th>
			<td><?php esc_html_e( 'The most it sends in one cycle. 0 uses the global pace from Settings, which is also the ceiling.', 'total-mail-queue' ); ?></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Send Interval', 'total-mail-queue' ); ?></th>
			<td><?php esc_html_e( 'How long, in minutes, this account\'s cycle lasts before its per-cycle count starts over. 0 follows the global interval.', 'total-mail-queue' ); ?></td>
		</tr>
	</tbody>
</table>

<h3><?php esc_html_e( 'Which account sends', 'total-mail-queue' ); ?></h3>
<p><?php esc_html_e( 'Among the enabled accounts that still have room under every limit, the one used least recently goes next, so the load is spread across all of them. Priority (lower first) only breaks a tie. A source can name a preferred account in the Sources tab; that account is used while it is enabled and under its limits, and the normal turn order takes over otherwise.', 'total-mail-queue' ); ?></p>
<p><?php esc_html_e( 'When every account is at its limit, what happens next depends on the Send Method: with "Plugin SMTP only" emails wait in the queue, with "Automatic" they fall back to WordPress\'s mailer.', 'total-mail-queue' ); ?></p>

<h3><?php esc_html_e( 'Moving accounts to another site', 'total-mail-queue' ); ?></h3>
<p><?php esc_html_e( 'Settings → Export / Import carries the settings and the SMTP accounts in one XML file. Passwords travel encrypted and can only be read on a site with the same WordPress salt keys; elsewhere, enter them again after importing.', 'total-mail-queue' ); ?></p>
