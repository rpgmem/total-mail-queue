<?php
/**
 * Documentation topic: Sources.
 *
 * Included by {@see \TotalMailQueue\Admin\Documentation::render()}.
 *
 * @package TotalMailQueue
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<p><?php esc_html_e( 'A source is who sent an email. Every email is tagged with one: a WordPress core email such as wp_core:password_reset, a plugin such as plugin:woocommerce, or a theme. The Sources tab lists every source seen so far, plus common ones known in advance, so you can act on a source before it sends anything.', 'total-mail-queue' ); ?></p>

<h3><?php esc_html_e( 'How the source is found', 'total-mail-queue' ); ?></h3>
<p><?php esc_html_e( 'For WordPress\'s own emails, the plugin listens to the filters core runs just before sending. For everything else it reads which plugin or theme made the call. A plugin can also name the source itself with a header, which lets it split its emails into several sources; see the developer topic.', 'total-mail-queue' ); ?></p>

<h3><?php esc_html_e( 'Turning a source off', 'total-mail-queue' ); ?></h3>
<p><?php esc_html_e( 'Disable a source and its emails are kept in the log as "Blocked by source" and never sent, even with the Instant header. "Toggle every source in group" does the same for a whole group at once. The plugin\'s own alert email is a system source and stays on.', 'total-mail-queue' ); ?></p>

<h3><?php esc_html_e( 'Editing a source', 'total-mail-queue' ); ?></h3>
<ul class="tmq-docs-list">
	<li><?php esc_html_e( 'Label and Group change how the source is shown. They do not change the source key.', 'total-mail-queue' ); ?></li>
	<li><?php esc_html_e( 'Priority places its emails in the queue order, from 1 (first) to 100. Give password resets a low number so they never wait behind a newsletter.', 'total-mail-queue' ); ?></li>
	<li><?php esc_html_e( 'Preferred SMTP account sends its emails through that account whenever it has room.', 'total-mail-queue' ); ?></li>
	<li><?php esc_html_e( 'For WordPress core emails, Subject and Body replace the text WordPress would send.', 'total-mail-queue' ); ?></li>
</ul>
