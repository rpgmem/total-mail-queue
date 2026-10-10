<?php
/**
 * Documentation topic: Troubleshooting.
 *
 * Included by {@see \TotalMailQueue\Admin\Documentation::render()}. Replaces
 * the old FAQ tab, whose slug now opens this documentation.
 *
 * @package TotalMailQueue
 */

use TotalMailQueue\Admin\PluginPage;
use TotalMailQueue\Settings\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wp_tmq_options  = Options::get();
$wp_tmq_test_url = wp_nonce_url( admin_url( 'admin.php?page=' . PluginPage::TAB_QUEUE . '&addtestmail' ), 'wp_tmq_addtestmail' );
?>
<h3><?php esc_html_e( 'Emails wait in the queue and are never sent', 'total-mail-queue' ); ?></h3>
<p><?php esc_html_e( 'The queue is emptied by WP-Cron, and WP-Cron only runs when someone loads a page of the site. On a site with little traffic, or one whose pages are served from a cache, it may run rarely or only while you are logged in. When emails have been waiting for more than two hours with nothing sent and nothing scheduled, the plugin shows a warning at the top of the admin.', 'total-mail-queue' ); ?></p>
<p><?php esc_html_e( 'The fix is a real server cron that calls wp-cron.php every few minutes. Caching plugins and proxy caches such as NGINX cause the same symptom, and some caching plugins have a WP-Cron option of their own; look for it before adding the server cron.', 'total-mail-queue' ); ?></p>
<p><?php esc_html_e( 'Check the Operation Mode too: in Block mode nothing is ever sent, by design, and a red notice on the Retention tab says so.', 'total-mail-queue' ); ?></p>

<h3><?php esc_html_e( 'The admin says the site has problems sending emails', 'total-mail-queue' ); ?></h3>
<p><?php esc_html_e( 'That notice appears when the last attempt failed, and it quotes the error. Open the Log, find the failed emails and read the Info column. Errors from an SMTP account usually mean a wrong password, port or encryption setting: use Test Connection on the account. Once the cause is fixed, select the failed emails and use Force Resend.', 'total-mail-queue' ); ?></p>

<h3><?php esc_html_e( 'A warning about a conflicting email plugin', 'total-mail-queue' ); ?></h3>
<p><?php esc_html_e( 'Another plugin is also hooked into how WordPress sends mail, usually an SMTP plugin. When the emails go out through this plugin\'s SMTP accounts, the two can fight over the same message. Deactivate the other plugin, or leave the sending to it by choosing WordPress default as the Send Method.', 'total-mail-queue' ); ?></p>

<h3><?php esc_html_e( 'An email never shows up in the queue or the log', 'total-mail-queue' ); ?></h3>
<p><?php esc_html_e( 'The plugin sees only email sent through wp_mail(), the WordPress function nearly every plugin uses. A plugin that talks to a mail server directly bypasses it, and the plugin warns you when it recognises one. Emails sent with the Instant header skip the queue but still appear in the log.', 'total-mail-queue' ); ?></p>

<h3><?php esc_html_e( 'Another plugin\'s scheduled emails appear in the queue', 'total-mail-queue' ); ?></h3>
<p><?php esc_html_e( 'That is expected. Emails another plugin sends from its own scheduled tasks, such as digests or abandoned-cart reminders, pass through wp_mail() like any other, so they are queued, and in Block mode they are held back as well.', 'total-mail-queue' ); ?></p>

<h3><?php esc_html_e( 'Attachments', 'total-mail-queue' ); ?></h3>
<p><?php esc_html_e( 'Attachments are copied when the email is queued and kept until it is sent or fails for good, so a retry still carries them and a form that deletes its upload right away still delivers it. If they cannot be copied, the email is queued without them and its Info column says so.', 'total-mail-queue' ); ?></p>

<h3><?php esc_html_e( 'Testing the whole path', 'total-mail-queue' ); ?></h3>
<p><?php esc_html_e( 'This button puts one test email into the queue, addressed to the alert address in Settings. Watch it on the Retention tab until the next run sends it, then find it in the Log.', 'total-mail-queue' ); ?></p>
<p><a class="button" href="<?php echo esc_url( $wp_tmq_test_url ); ?>">
<?php
echo esc_html(
	sprintf(
		/* translators: %s: email address */
		__( 'Queue a test email to %s', 'total-mail-queue' ),
		(string) $wp_tmq_options['email']
	)
);
?>
</a></p>
