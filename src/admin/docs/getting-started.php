<?php
/**
 * Documentation topic: Getting started.
 *
 * Included by {@see \TotalMailQueue\Admin\Documentation::render()}.
 *
 * @package TotalMailQueue
 */

use TotalMailQueue\Admin\Documentation;
use TotalMailQueue\Admin\PluginPage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<p><?php esc_html_e( 'Total Mail Queue sits between WordPress and your mail server. Every email that WordPress, a plugin or a theme sends through wp_mail() can be held in a queue, sent at a pace you choose, through the SMTP accounts you choose, and recorded in a log you can search.', 'total-mail-queue' ); ?></p>

<h3><?php esc_html_e( 'First setup', 'total-mail-queue' ); ?></h3>
<ol class="tmq-docs-steps">
	<li><?php echo wp_kses_post( sprintf( /* translators: %s: link to the Settings tab */ __( 'In %s, set Operation Mode to Queue. Until then the plugin only watches; nothing is held back.', 'total-mail-queue' ), Documentation::tabLink( PluginPage::TAB_SETTINGS, __( 'Settings', 'total-mail-queue' ) ) ) ); ?></li>
	<li><?php esc_html_e( 'Set the pace in the same card: how many emails go out per interval. Start low, for example 5 every minute, and raise it once you know what your mail server accepts.', 'total-mail-queue' ); ?></li>
	<li><?php echo wp_kses_post( sprintf( /* translators: %s: link to the SMTP Accounts tab */ __( 'Add an account in %s and press Test Connection. Without one, Send Method "Automatic" uses whatever WordPress would have used.', 'total-mail-queue' ), Documentation::tabLink( PluginPage::TAB_SMTP, __( 'SMTP Accounts', 'total-mail-queue' ) ) ) ); ?></li>
	<li><?php echo wp_kses_post( sprintf( /* translators: 1: link to the Retention tab, 2: link to the Log tab */ __( 'Watch it work: waiting emails are listed in %1$s and move to the %2$s once they are sent.', 'total-mail-queue' ), Documentation::tabLink( PluginPage::TAB_QUEUE, __( 'Retention', 'total-mail-queue' ) ), Documentation::tabLink( PluginPage::TAB_LOG, __( 'Log', 'total-mail-queue' ) ) ) ); ?></li>
	<li><?php esc_html_e( 'Make sure WP-Cron runs reliably, because it is what sends the queue. The next topic explains how.', 'total-mail-queue' ); ?></li>
</ol>

<p><?php esc_html_e( 'Switching the plugin to Disabled loses nothing: emails already waiting stay in the queue and go out when you switch back to Queue.', 'total-mail-queue' ); ?></p>
<p><?php esc_html_e( 'On/off switches in Settings and Templates save the moment you flip them, and a badge beside the switch says Saved. The other fields save with the Save button, which keeps the switches as they are.', 'total-mail-queue' ); ?></p>
