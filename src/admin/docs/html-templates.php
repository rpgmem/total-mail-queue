<?php
/**
 * Documentation topic: HTML templates.
 *
 * Included by {@see \TotalMailQueue\Admin\Documentation::render()}.
 *
 * @package TotalMailQueue
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<p><?php esc_html_e( 'The Templates tab wraps every outgoing email in one styled HTML layout: a header with your logo or site title, the message, and a footer. You set the colours, the alignment, the padding and the footer text once, and every email from every plugin looks like it came from the same site.', 'total-mail-queue' ); ?></p>

<h3><?php esc_html_e( 'What gets wrapped', 'total-mail-queue' ); ?></h3>
<ul class="tmq-docs-list">
	<li><?php esc_html_e( 'Plain-text emails are turned into HTML first, keeping their paragraphs and line breaks.', 'total-mail-queue' ); ?></li>
	<li><?php esc_html_e( 'An email that is already a complete HTML document is sent as it is, so newsletters and page-builder emails keep their own design.', 'total-mail-queue' ); ?></li>
	<li><?php esc_html_e( 'Nothing is wrapped while "Enable HTML wrapping" is off.', 'total-mail-queue' ); ?></li>
</ul>

<h3><?php esc_html_e( 'Tokens', 'total-mail-queue' ); ?></h3>
<p><?php esc_html_e( 'The footer text can use {site_title}, {site_url}, {home_url}, {admin_email}, {recipient}, {date} and {year}. Each is replaced when the email is sent.', 'total-mail-queue' ); ?></p>

<h3><?php esc_html_e( 'Trying it', 'total-mail-queue' ); ?></h3>
<p><?php esc_html_e( 'Save your changes, then use "Send test email" at the bottom of the tab to receive a sample at any address. "Reset to Defaults" puts every template setting back as it shipped.', 'total-mail-queue' ); ?></p>

<h3><?php esc_html_e( 'Rewriting WordPress\'s own emails', 'total-mail-queue' ); ?></h3>
<p><?php esc_html_e( 'The welcome, password-reset, email-change and privacy emails that WordPress itself sends can be rewritten from the Sources tab: open one of the wp_core sources and fill in Subject and Body. Each one lists the tokens it accepts, such as {username} or {reset_url}, and two work in all of them: {subject}, the subject WordPress would have used, and {message_original}, its body. Use the second to add text before or after the original without losing the link it carries.', 'total-mail-queue' ); ?></p>
<p><?php esc_html_e( '"Skip template wrapper" sends that email without the HTML layout, for an override written as plain text. "Send preview test" sends the saved override, filled with sample values, to the address you choose.', 'total-mail-queue' ); ?></p>
<p><?php esc_html_e( 'Emails from other plugins are edited in those plugins; only WordPress core emails are editable here.', 'total-mail-queue' ); ?></p>
