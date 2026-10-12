=== Total Mail Queue ===
Tags: email, mail, queue, email log, wp_mail
Requires at least: 6.8
Tested up to: 7.1.3
Stable tag: 2.12.0
Requires PHP: 8.3
License: AGPLv3 or later
License URI: https://www.gnu.org/licenses/agpl-3.0.html

Queue outgoing emails, manage SMTP accounts with daily/monthly limits, and get alerts if your site sends too many emails.

== Description ==

Total Mail Queue holds every email WordPress sends in a queue and lets it out at a pace you choose, through the delivery you already use or through SMTP accounts with daily and monthly limits. A sudden flood of mail, such as a bot abusing a form, piles up in the queue instead of leaving the site, and an alert tells you about it.

* Intercepts wp_mail() and places outgoing messages in a queue
* Configure how many emails are sent and at what interval
* Manage multiple SMTP accounts with priority, daily and monthly sending limits
* Test SMTP connections before sending
* Log all queued email submissions with status filter (sent / error / alert)
* Auto-retry failed emails with configurable retry limit
* Resend or force-resend failed emails from the log
* Receive alerts when the queue grows unexpectedly
* Receive alerts when WordPress is unable to send emails
* See whether WP-Cron is sending the queue, and copy the server cron line that makes it reliable
* Export/import plugin settings and SMTP accounts via XML
* Detects and resolves conflicts with other email plugins

**This plugin is a fork of [Mail Queue](https://wordpress.org/plugins/mail-queue/) by WDM.**

== Frequently Asked Questions ==

= What do I set up after activating it? =

Open the plugin's settings, choose the operation mode and set how many emails leave per run and how often. Alerts are configured on the same screen: the queue length that triggers one, and who receives it.

= How does the queue work? =

The plugin answers wp_mail() itself. Each message is saved to the database and a WP-Cron job sends a batch at every interval you set, so nothing leaves faster than that pace.

= Does it change how my emails are delivered? =

Only if you ask it to. The default `auto` method hands each message to whatever delivers mail on your site today: another SMTP plugin, PHP's mail(), an API service. Add SMTP accounts in the plugin to send through them, with their limits, or choose `php` to always use WordPress's own mailer.

= My site uses a page cache. Will the queue still be sent? =

A page cache (W3 Total Cache, WP Rocket, a proxy such as NGINX in front of the site) serves visitors without running WordPress, so WP-Cron runs rarely and the queue waits. Call wp-cron.php from a server cron job every few minutes instead; the Cron tab shows the exact line for your server.

= What happens to attachments? =

They are copied when the email is queued and kept until that email is sent, then removed.

= What are queue alerts for? =

A queue that grows far beyond normal usually means something is sending mail it should not, such as a form under attack. The queue already slows that mail down; the alert tells you when its length passes the limit you chose, so you can look before more of it goes out.

= Can an email skip ahead of the others? =

Yes. Add the header `X-Mail-Queue-Prio: High` and the email leaves before ordinary mail in the next run. Each source can also have its own priority in the Sources tab.

With wp_mail():

`wp_mail( $to, $subject, $message, array( 'X-Mail-Queue-Prio: High' ) );`

For WooCommerce emails:

`add_filter( 'woocommerce_mail_callback_params', function ( $params ) {
	$header = 'X-Mail-Queue-Prio: High';
	if ( is_array( $params[3] ) ) {
		$params[3][] = $header;
	} else {
		$params[3] = trim( $params[3] . "\r\n" . $header );
	}
	return $params;
} );`

In Contact Form 7, add `X-Mail-Queue-Prio: High` as a line of the form's Additional Headers, on its Mail tab.

= Can an email bypass the queue? =

Yes, with the header `X-Mail-Queue-Prio: Instant`: the email is sent at once and logged as `instant`. It ignores the pace you set, so keep it for the rare email that cannot wait, such as a password reset. A source you disabled still blocks it, and in Block mode it is held like any other email.

For password reset emails:

`add_filter( 'retrieve_password_notification_email', function ( $email ) {
	$header = 'X-Mail-Queue-Prio: Instant';
	if ( is_array( $email['headers'] ) ) {
		$email['headers'][] = $header;
	} else {
		$email['headers'] = trim( $email['headers'] . "\r\n" . $header );
	}
	return $email;
} );`

= What does wp_mail() return while the queue is on? =

`true` once the message is stored in the queue, and `false` if it could not be stored. An `Instant` email is sent even if its log entry fails, because it was marked as one that cannot wait.

= Does it work on a multisite network? =

Activate it on each site rather than network-wide; each site then keeps its own queue and settings.

== Screenshots ==

The images live in the repository's `docs/screenshots/` folder and are shown in its README.

1. Log of sent, failed and blocked emails, with search, status, source and date filters
2. Retention list of queued and held emails
3. SMTP accounts with priority and daily and monthly limits
4. Sources detected on the site, with priority and preferred account
5. HTML template settings with the live preview
6. General settings: operation mode, queue pace and alerts
7. Access: which roles view and which manage the plugin
8. Built-in documentation
9. Dark mode
10. Cron: the queue worker's state, WP-Cron's health and the server cron line

== Installation ==

Install and activate the plugin, then turn the queue on in its settings. The queue is sent by WP-Cron, so make sure it runs reliably; on a cached or quiet site, the Cron tab gives the server line that does.

Updates arrive through the normal WordPress update screen, straight from this plugin's [GitHub Releases](https://github.com/rpgmem/total-mail-queue/releases). Each package is checked against its published SHA-256 before it is installed, and WordPress's own per-plugin toggle turns on automatic updates.


== Changelog ==

The two most recent releases are summarised below. The full history of every release is in [CHANGELOG.md](https://github.com/rpgmem/total-mail-queue/blob/main/CHANGELOG.md).

= 2.12.0 =
* New: a Cron tab, always shown, says whether the queue worker is running, asleep, waiting, overdue or stalled, whether WP-Cron fires, and gives administrators the server cron line to copy.
* Changed: icons, colours and the documentation's Quick Navigation follow Free Form Certificate.
* Fixed: an empty number field keeps its value instead of saving 0, and Import restores every setting the form saves.
* Security: stored SMTP passwords are authenticated as well as encrypted.

= 2.11.0 =
* New: a live preview on the Templates tab shows a sample email, header and footer included, with the settings on screen and updates as you edit, before saving.

== Upgrade Notice ==

= 2.12.0 =
Adds a Cron tab that shows whether the queue is being sent and gives the server cron line to copy; an empty number field no longer saves 0, and SMTP passwords are authenticated. Details and issues in CHANGELOG.md.
