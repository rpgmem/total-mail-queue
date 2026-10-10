<?php
/**
 * Documentation topic: Alerts.
 *
 * Included by {@see \TotalMailQueue\Admin\Documentation::render()}.
 *
 * @package TotalMailQueue
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<p><?php esc_html_e( 'An alert is an email the plugin sends you when more emails are waiting in the queue than you expect. A queue that suddenly fills up is often the first sign that something on the site is sending mail it should not, such as spam from a compromised form, and the queue holding it back gives you time to look.', 'total-mail-queue' ); ?></p>

<h3><?php esc_html_e( 'Setting it up', 'total-mail-queue' ); ?></h3>
<p><?php esc_html_e( 'In Settings → Alerts, switch on "Alert enabled", then fill in "Alert Sensitivity": the address that receives the alert and how many waiting emails should trigger it.', 'total-mail-queue' ); ?></p>

<h3><?php esc_html_e( 'How often', 'total-mail-queue' ); ?></h3>
<p><?php esc_html_e( 'At most one alert every six hours, however long the queue stays above the threshold. Each alert also appears in the log with the Alert status.', 'total-mail-queue' ); ?></p>
<p><?php esc_html_e( 'The alert has a source of its own that cannot be disabled, so turning sources off can never silence it.', 'total-mail-queue' ); ?></p>

<h3><?php esc_html_e( 'Block on alert', 'total-mail-queue' ); ?></h3>
<p><?php esc_html_e( 'With "Block on alert" on, the alert also switches the Operation Mode to Block, so nothing more is sent until you have looked. The alert email says so, and a notice at the top of the admin stays until the mode changes. Block keeps every email: once the queue is clean, set the mode back to Queue and what was held goes out. Password resets still go out, unless you turned that off.', 'total-mail-queue' ); ?></p>
