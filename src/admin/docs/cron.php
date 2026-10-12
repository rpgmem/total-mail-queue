<?php
/**
 * Documentation topic: Cron.
 *
 * Included by {@see \TotalMailQueue\Admin\Documentation::render()}.
 *
 * @package TotalMailQueue
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<p><?php esc_html_e( 'The queue is sent by a worker that WP-Cron runs at the interval set in Settings. The Cron tab shows whether that worker is running, whether WP-Cron itself is firing, and the line to add to the server so it always does.', 'total-mail-queue' ); ?></p>

<h3><?php esc_html_e( 'Queue worker', 'total-mail-queue' ); ?></h3>
<table class="widefat striped tmq-docs-table">
	<tbody>
		<tr>
			<th scope="row"><?php esc_html_e( 'Running', 'total-mail-queue' ); ?></th>
			<td><?php esc_html_e( 'The next run is due within one interval. Nothing to do.', 'total-mail-queue' ); ?></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Asleep', 'total-mail-queue' ); ?></th>
			<td><?php esc_html_e( 'The queue is empty, so nothing is scheduled. The next email that enters the queue wakes the worker.', 'total-mail-queue' ); ?></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Waiting', 'total-mail-queue' ); ?></th>
			<td><?php esc_html_e( 'With the send method set to SMTP only and every account at a limit, the worker sleeps until the first account frees up instead of waking every interval.', 'total-mail-queue' ); ?></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Overdue', 'total-mail-queue' ); ?></th>
			<td><?php esc_html_e( 'A run is scheduled but well past its time: WP-Cron is not firing. Add a server cron.', 'total-mail-queue' ); ?></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Stalled', 'total-mail-queue' ); ?></th>
			<td><?php esc_html_e( 'Emails are waiting and no run is scheduled. Saving the settings schedules one again; if it keeps happening, check the Troubleshooting topic.', 'total-mail-queue' ); ?></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Not running', 'total-mail-queue' ); ?></th>
			<td><?php esc_html_e( 'The operation mode is Disabled or Block, so the queue is not sent on purpose.', 'total-mail-queue' ); ?></td>
		</tr>
	</tbody>
</table>
<p><?php esc_html_e( 'The card also shows the next and the last run, how many emails are waiting, and when the last email was sent.', 'total-mail-queue' ); ?></p>

<h3><?php esc_html_e( 'WP-Cron', 'total-mail-queue' ); ?></h3>
<p><?php esc_html_e( 'This card says whether WP-Cron\'s visit trigger is on, and lists how many scheduled events, from any plugin, are overdue. Several overdue events mean WP-Cron is not running at all, not that this plugin has a problem.', 'total-mail-queue' ); ?></p>

<h3><?php esc_html_e( 'Server cron', 'total-mail-queue' ); ?></h3>
<p><?php esc_html_e( 'Choose how often the server should wake WP-Cron and how: WP-CLI runs the due events directly, wget and curl call wp-cron.php over HTTP. Copy the line into the crontab of the user that runs the site, or into the cron jobs of your hosting panel, then set DISABLE_WP_CRON to true in wp-config.php so visits no longer trigger it.', 'total-mail-queue' ); ?></p>
<p><?php esc_html_e( 'The line only wakes WP-Cron. How often the queue sends is still the interval in Settings, so a server cron every minute with an interval of five minutes sends every five minutes. This card shows the WordPress path, so only administrators see it.', 'total-mail-queue' ); ?></p>
