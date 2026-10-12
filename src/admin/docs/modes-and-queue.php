<?php
/**
 * Documentation topic: Modes and the queue.
 *
 * Included by {@see \TotalMailQueue\Admin\Documentation::render()}.
 *
 * @package TotalMailQueue
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<h3><?php esc_html_e( 'Operation modes', 'total-mail-queue' ); ?></h3>
<table class="widefat striped tmq-docs-table">
	<tbody>
		<tr>
			<th scope="row"><?php esc_html_e( 'Disabled', 'total-mail-queue' ); ?></th>
			<td><?php esc_html_e( 'WordPress sends email as if the plugin were not there. Nothing is intercepted and the queue is not sent.', 'total-mail-queue' ); ?></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Queue', 'total-mail-queue' ); ?></th>
			<td><?php esc_html_e( 'Every email is stored first and sent later by the cron worker, at the pace you set.', 'total-mail-queue' ); ?></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Block', 'total-mail-queue' ); ?></th>
			<td><?php esc_html_e( 'Every email is stored and none is sent, including the ones other plugins send from their own scheduled tasks. Use it on a staging copy, during a security incident or while migrating. Switching to Queue sends what was held. Password resets are the exception and go out at once, so nobody is locked out; switch off "Password resets" in Settings to hold them too.', 'total-mail-queue' ); ?></td>
		</tr>
	</tbody>
</table>

<h3><?php esc_html_e( 'Pace', 'total-mail-queue' ); ?></h3>
<p><?php esc_html_e( '"Send max. N email(s) every X" is how many emails one run of the worker sends and how often it runs. The interval cannot be shorter than 10 seconds. It is also a ceiling: an SMTP account with a higher per-cycle limit of its own is held to this number.', 'total-mail-queue' ); ?></p>

<h3><?php esc_html_e( 'WP-Cron', 'total-mail-queue' ); ?></h3>
<p><?php esc_html_e( 'The worker runs on WP-Cron, and WP-Cron only runs when someone visits the site. On a quiet site, or one served from a full-page cache, emails can wait far longer than the interval says.', 'total-mail-queue' ); ?></p>
<p><?php esc_html_e( 'The reliable setup is a real cron job on the server that calls wp-cron.php every minute or two, with WP-Cron\'s visit trigger turned off (DISABLE_WP_CRON in wp-config.php). The Cron tab shows whether the worker is running and gives the exact line to add.', 'total-mail-queue' ); ?></p>

<h3><?php esc_html_e( 'Order and priority', 'total-mail-queue' ); ?></h3>
<p><?php esc_html_e( 'Waiting emails leave in priority order, lower number first, from 1 to 100. Ordinary mail is 50. An email carrying the header X-Mail-Queue-Prio: High gets 10, and each source can be given its own priority in the Sources tab.', 'total-mail-queue' ); ?></p>
<p><?php esc_html_e( 'X-Mail-Queue-Prio: Instant skips the queue altogether: the email is sent at once and logged as "instant". It bypasses the pace you set, so keep it for the rare email that cannot wait. A source you disabled still blocks it.', 'total-mail-queue' ); ?></p>

<h3><?php esc_html_e( 'Send method', 'total-mail-queue' ); ?></h3>
<table class="widefat striped tmq-docs-table">
	<tbody>
		<tr>
			<th scope="row"><?php esc_html_e( 'Automatic', 'total-mail-queue' ); ?></th>
			<td><?php esc_html_e( 'Uses one of your SMTP accounts when one has capacity. Otherwise it reuses the SMTP settings another plugin applied, and failing that, WordPress\'s own mailer.', 'total-mail-queue' ); ?></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Plugin SMTP only', 'total-mail-queue' ); ?></th>
			<td><?php esc_html_e( 'Sends only through your SMTP accounts. When none has capacity, emails wait in the queue until one does.', 'total-mail-queue' ); ?></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'WordPress default', 'total-mail-queue' ); ?></th>
			<td><?php esc_html_e( 'Ignores your SMTP accounts and sends the way wp_mail() would, which may be PHP mail or another SMTP plugin.', 'total-mail-queue' ); ?></td>
		</tr>
	</tbody>
</table>

<h3><?php esc_html_e( 'Failures and retries', 'total-mail-queue' ); ?></h3>
<p><?php esc_html_e( 'A send that fails is tried again on later runs, up to the Auto-Retry count in Settings. After the last attempt the email is marked Error and stays in the log, with the server\'s answer under "Error details".', 'total-mail-queue' ); ?></p>
