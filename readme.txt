=== Total Mail Queue ===
Tags: email, mail, queue, email log, wp_mail
Requires at least: 5.9
Tested up to: 6.9
Stable tag: 2.6.1
Requires PHP: 7.4
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Queue outgoing emails, manage SMTP accounts with daily/monthly limits, and get alerts if your site sends too many emails.

== Description ==

This plugin enhances the security and stability of your WordPress installation by delaying and controlling wp_mail() email submissions through a managed queue.

If your site exhibits unusual behavior — such as a spam bot repeatedly submitting forms — you will be alerted immediately.

* Intercepts wp_mail() and places outgoing messages in a queue
* Configure how many emails are sent and at what interval
* Manage multiple SMTP accounts with priority, daily and monthly sending limits
* Test SMTP connections before sending
* Log all queued email submissions with status filter (sent / error / alert)
* Auto-retry failed emails with configurable retry limit
* Resend or force-resend failed emails from the log
* Receive alerts when the queue grows unexpectedly
* Receive alerts when WordPress is unable to send emails
* Export/import plugin settings and SMTP accounts via XML
* Detects and resolves conflicts with other email plugins

**This plugin is a fork of [Mail Queue](https://wordpress.org/plugins/mail-queue/) by WDM.**

== Frequently Asked Questions ==

= Do I need to configure anything? =

Yes. Once activated please go into the Settings of the Plugin to do some configurations.

You can enable the Queue, control how many emails and how often they should be sent.

You can enable the Alerting feature and control at which point exactly you want to be alerted.

= How does this plugin work? =

When enabled, the plugin intercepts wp_mail(). Instead of sending emails immediately, they are stored in the database and released gradually via WP-Cron according to your configured interval.

= Does this plugin change the way HOW the emails are sent? =

It depends on your configuration. In the default `auto` send method the plugin preserves whatever email delivery mechanism you already have (SMTP from another plugin, PHP mail, Mailgun, etc.).

If you configure SMTP accounts within the plugin, it will use those to send emails with full control over daily/monthly limits. You can also set the send method to `php` to always use WordPress's default mail function.

= Does this plugin work, if I have a caching Plugin installed? =

If you're using a caching plugin like W3 Total Cache, WP Rocket or any other caching solution which generates static HTML files and serves them to visitors, you'll have to make sure you're calling the wp-cron file manually every couple of minutes.

Otherwise your normal WP Cron wouldn't be called as often as it should be and scheduled messages would be sent with big delays.

= What about Proxy-Caching, e.g. NGINX? =

Same situation here. Please make sure you're calling the WordPress Cron by an external service or your webhoster every couple of minutes.

= My form builder supports attachments. What about them? =

You are covered. All attachments are stored temporarily in the queue until they are sent along with their corresponding emails.

= What are Queue alerts? =

This is a simple and effective way to improve the security of your WordPress installation.

Imagine: In case your website is sending spam through wp_mail(), the email Queue would fill up very quickly preventing your website from sending so many spam emails at once. This gives you time and avoids a lot of trouble.

Queue Alerts warn you, if the Queue is longer than usal. You decide at which point you want to be alerted. So you get the chance to have a look if there might be something wrong on the website.

= Can I add emails with a high priority to the queue? =

Yes, you can add the custom `X-Mail-Queue-Prio` header set to `High` to your email. High priority emails will be sent through the standard Total Mail Queue sending cycle but before all normal emails lacking a priority header in the queue.

*Example 1 (add priority to Woocommerce emails):*

`add_filter('woocommerce_mail_callback_params',function ( $array ) {
	$prio_header = 'X-Mail-Queue-Prio: High';
	if (is_array($array[3])) {
		$array[3][] = $prio_header;
	} else {
		$array[3] .= $array[3] ? "\r\n" : '';
		$array[3] .= $prio_header;
	}
	return $array;
},10,1);`

*Example 2 (add priority to Contact Form 7 form emails):*

When editing a form in Contact Form 7 just add an additional line to the
`Additional Headers` field under the `Mail` tab panel.

`X-Mail-Queue-Prio: High`

*Example 3 (add priority to WordPress reset password emails):*

`add_filter('retrieve_password_notification_email', function ($defaults, $key, $user_login, $user_data) {
	$prio_header = 'X-Mail-Queue-Prio: High';
	if (is_array($defaults['headers'])) {
		$defaults['headers'][] = $prio_header;
	} else {
		$defaults['headers'] .= $defaults['headers'] ? "\r\n" : '';
		$defaults['headers'] .= $prio_header;
	}
	return $defaults;
}, 10, 4);`


= Can I send emails instantly without going through the queue? =

Yes, this is possible (if you absolutely need to do this).

For this you can add the custom `X-Mail-Queue-Prio` header set to `Instant` to your email. These emails are sent instantly circumventing the mail queue. They still appear in the Total Mail Queue log flagged as `instant`.

Mind that this is a potential security risk and should be considered carefully. Please use only as an exception.

*Example 1 (instantly send Woocommerce emails):*

`add_filter('woocommerce_mail_callback_params',function ( $array ) {
	$prio_header = 'X-Mail-Queue-Prio: Instant';
	if (is_array($array[3])) {
		$array[3][] = $prio_header;
	} else {
		$array[3] .= $array[3] ? "\r\n" : '';
		$array[3] .= $prio_header;
	}
	return $array;
},10,1);`

*Example 2 (instantly send Contact Form 7 form emails):*

When editing a form in Contact Form 7 just add an additional line to the
`Additional Headers` field under the `Mail` tab panel.

`X-Mail-Queue-Prio: Instant`

*Example 3 (instantly send WordPress reset password emails):*

`add_filter('retrieve_password_notification_email', function ($defaults, $key, $user_login, $user_data) {
	$prio_header = 'X-Mail-Queue-Prio: Instant';
	if (is_array($defaults['headers'])) {
		$defaults['headers'][] = $prio_header;
	} else {
		$defaults['headers'] .= $defaults['headers'] ? "\r\n" : '';
		$defaults['headers'] .= $prio_header;
	}
	return $defaults;
}, 10, 4);`

= Can I still use the wp_mail() function as ususal? =

Yes, the wp_mail() function works as expected.

When calling wp_mail() the function returns `true` as expected. This means the email has been entered into the queue.

*Exceptions:*

If for some reason the email cannot be entered into the database, wp_mail() will return `false`.

However if you send an email using the instant header option the email will be considered important.
In this case the email will be sent right away, even if there is an error creating a log for it in the queue.

= I have a MultiSite. Can I use Total Mail Queue? =

Yes, but with limitations.

Do not activate the Total Mail Queue for the whole network. Instead, please activate it for each site separately. Then it will work smoothly. In a future release we'll add full MultiSite support.

== Installation ==

Upload the the plugin, activate it, and go to the Settings to enable the Queue. 

Please make sure that your WP Cron is running reliably.


== Changelog ==

The two most recent releases are summarised below. The full history of every release is in [CHANGELOG.md](https://github.com/rpgmem/total-mail-queue/blob/main/CHANGELOG.md).

= 2.6.1 =
* Hotfix: per-source `Skip template wrapper` checkbox introduced in 2.6.0 was honored at intercept time but the Engine on `wp_mail` filter @100 silently re-wrapped queued rows at cron drain time. Now skipped end-to-end.

= 2.6.0 =
* Per-source body & subject overrides for 11 WordPress core emails (password reset, new user welcome, password change, email change, admin email change confirm, privacy data confirm/export/erasure, recovery mode). Edit in Sources tab → row → Edit, with token substitution, "Reset to WP default", and a per-template "Send preview" button.
* `Skip template wrapper` per template (raw delivery, bypasses the global HTML envelope).
* Token registry extended: `{subject}` + `{message_original}` for prefix/suffix overrides; `{username}`, `{reset_url}`, etc. captured automatically per-call from WP-core filters.
* Sender override moved from Templates tab → Settings tab as `Default Sender`. One-shot migration on upgrade copies legacy `from_email` / `from_name` and documents the precedence: SMTP account → Default Sender → WordPress core.
* Admin notice when WordPress is upgraded between requests, prompting a re-check of the wp_core baseline.
* Schema: `subject_override` + `body_override` + `skip_template_wrap` columns on `{$prefix}total_mail_queue_sources` (added via dbDelta on upgrade).

