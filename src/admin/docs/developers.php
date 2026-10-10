<?php
/**
 * Documentation topic: For developers.
 *
 * Included by {@see \TotalMailQueue\Admin\Documentation::render()}. Code
 * samples are not translated: they are code.
 *
 * @package TotalMailQueue
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<p><?php esc_html_e( 'Everything below works through wp_mail(): add a header to the email, or hook a filter. Neither needs this plugin to be active to be safe; without it, the headers are ordinary headers.', 'total-mail-queue' ); ?></p>

<h3><?php esc_html_e( 'Priority and instant sending', 'total-mail-queue' ); ?></h3>
<p><?php esc_html_e( 'X-Mail-Queue-Prio: High sends an email before ordinary mail. X-Mail-Queue-Prio: Instant skips the queue and sends at once. Both headers are removed before the email leaves.', 'total-mail-queue' ); ?></p>
<pre><code>wp_mail( $to, $subject, $message, array( 'X-Mail-Queue-Prio: High' ) );</code></pre>
<p><?php esc_html_e( 'In a plugin that builds its own headers, add the line to them. For WooCommerce:', 'total-mail-queue' ); ?></p>
<pre><code>add_filter( 'woocommerce_mail_callback_params', function ( $params ) {
	$header = 'X-Mail-Queue-Prio: High';
	if ( is_array( $params[3] ) ) {
		$params[3][] = $header;
	} else {
		$params[3] .= ( $params[3] ? "\r\n" : '' ) . $header;
	}
	return $params;
} );</code></pre>
<p><?php esc_html_e( 'In Contact Form 7, add the header as a line in the form\'s "Additional Headers" field, under the Mail panel.', 'total-mail-queue' ); ?></p>

<h3><?php esc_html_e( 'Naming the source', 'total-mail-queue' ); ?></h3>
<p><?php esc_html_e( 'When all of a plugin\'s emails go through one function, the plugin shows up as a single source. X-TMQ-Source-Key splits it: each kind of email can carry its own key, and X-TMQ-Source-Label gives it a readable name. Keys must start with plugin:, mu_plugin: or theme:, so an email can never pose as a WordPress core one. Both headers are removed before the email is stored or sent.', 'total-mail-queue' ); ?></p>
<pre><code>wp_mail( $to, $subject, $message, array(
	'X-TMQ-Source-Key: plugin:my-shop_receipt',
	'X-TMQ-Source-Label: Order receipt',
) );</code></pre>

<h3><?php esc_html_e( 'Template filters', 'total-mail-queue' ); ?></h3>
<table class="widefat striped tmq-docs-table">
	<tbody>
		<tr>
			<th scope="row"><code>wp_tmq_template_skip</code></th>
			<td><?php esc_html_e( 'Return true to send one email without the HTML layout. Receives the wp_mail() arguments.', 'total-mail-queue' ); ?></td>
		</tr>
		<tr>
			<th scope="row"><code>wp_tmq_template_tokens</code></th>
			<td><?php esc_html_e( 'Add your own {tokens}: return the map of token name to value.', 'total-mail-queue' ); ?></td>
		</tr>
		<tr>
			<th scope="row"><code>wp_tmq_template_content</code></th>
			<td><?php esc_html_e( 'Change the message body before it is wrapped.', 'total-mail-queue' ); ?></td>
		</tr>
		<tr>
			<th scope="row"><code>wp_tmq_template_html</code></th>
			<td><?php esc_html_e( 'Change the finished HTML just before it is sent.', 'total-mail-queue' ); ?></td>
		</tr>
		<tr>
			<th scope="row"><code>wp_tmq_template_options</code></th>
			<td><?php esc_html_e( 'Change the template settings in code, over what the Templates tab saved.', 'total-mail-queue' ); ?></td>
		</tr>
		<tr>
			<th scope="row"><code>wp_tmq_template_before_apply</code>, <code>wp_tmq_template_after_apply</code></th>
			<td><?php esc_html_e( 'Actions that run around the wrapping of each email.', 'total-mail-queue' ); ?></td>
		</tr>
	</tbody>
</table>

<h3><?php esc_html_e( 'Updates', 'total-mail-queue' ); ?></h3>
<p><?php esc_html_e( 'wp_tmq_github_updater_enabled returns false to stop the plugin from checking GitHub for updates; wp_tmq_github_updater_repo points the check at a fork.', 'total-mail-queue' ); ?></p>

<h3><?php esc_html_e( 'REST', 'total-mail-queue' ); ?></h3>
<p><?php esc_html_e( 'GET /wp-json/tmq/v1/message/{id} returns one stored message for display. It is what the log uses to open a message, and it answers only users who can manage options.', 'total-mail-queue' ); ?></p>
