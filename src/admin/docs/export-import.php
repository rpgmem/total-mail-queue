<?php
/**
 * Documentation topic: Export and import.
 *
 * Included by {@see \TotalMailQueue\Admin\Documentation::render()}.
 *
 * @package TotalMailQueue
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<p><?php esc_html_e( 'Settings → Export / Import moves the plugin\'s configuration between sites, or keeps a copy before a big change.', 'total-mail-queue' ); ?></p>

<h3><?php esc_html_e( 'What the file carries', 'total-mail-queue' ); ?></h3>
<ul class="tmq-docs-list">
	<li><?php esc_html_e( 'Every setting from the Settings tab.', 'total-mail-queue' ); ?></li>
	<li><?php esc_html_e( 'Every SMTP account, with its password still encrypted.', 'total-mail-queue' ); ?></li>
</ul>
<p><?php esc_html_e( 'It does not carry the queue, the log, the sources or the HTML template.', 'total-mail-queue' ); ?></p>

<h3><?php esc_html_e( 'Importing', 'total-mail-queue' ); ?></h3>
<p><?php esc_html_e( 'Importing replaces all current settings and all SMTP accounts with the ones in the file; nothing is merged. Passwords can only be decrypted on a site with the same WordPress salt keys. On any other site the accounts arrive without a usable password: open each one and enter it again.', 'total-mail-queue' ); ?></p>
