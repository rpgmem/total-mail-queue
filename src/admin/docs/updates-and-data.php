<?php
/**
 * Documentation topic: Updates, stored data and uninstall.
 *
 * Included by {@see \TotalMailQueue\Admin\Documentation::render()}.
 *
 * @package TotalMailQueue
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<h3><?php esc_html_e( 'Updates', 'total-mail-queue' ); ?></h3>
<p><?php esc_html_e( 'New versions are published as GitHub releases and appear on WordPress\'s usual update screen, with their notes under "View details". Every package is checked against its published SHA-256 before it is installed, and an update that does not match is refused. To have updates installed automatically, use WordPress\'s own "Enable auto-updates" link on the Plugins screen.', 'total-mail-queue' ); ?></p>

<h3><?php esc_html_e( 'What the plugin stores', 'total-mail-queue' ); ?></h3>
<ul class="tmq-docs-list">
	<li><?php esc_html_e( 'Three database tables: the queue and log, the SMTP accounts, and the sources.', 'total-mail-queue' ); ?></li>
	<li><?php esc_html_e( 'Its settings, its HTML template and a few working records (the last run, the last send, the lock that keeps two runs apart) as WordPress options.', 'total-mail-queue' ); ?></li>
	<li><?php esc_html_e( 'Attachments of waiting emails, in a folder under wp-content/uploads, removed once each email is sent.', 'total-mail-queue' ); ?></li>
</ul>
<p><?php esc_html_e( 'The log holds full emails: recipients, subjects and bodies. Keep its clean-up settings in line with your privacy policy.', 'total-mail-queue' ); ?></p>

<h3><?php esc_html_e( 'Deactivating and deleting', 'total-mail-queue' ); ?></h3>
<p><?php esc_html_e( 'Deactivating stops the queue and keeps everything, so nothing is lost if you turn the plugin back on. Deleting it from the Plugins screen removes all of the above: the tables, the options and the attachments folder.', 'total-mail-queue' ); ?></p>
