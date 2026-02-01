=== Total Mail Queue ===
Tags: email, mail, queue, email log, wp_mail
Requires at least: 5.9
Tested up to: 6.9
Stable tag: 2.2.1
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

= 2.2.1 =
* WordPress Plugin Check compliance: output escaping, ABSPATH guards, safe redirects
* Cross-process cron lock using MySQL GET_LOCK to prevent overlapping batch sends
* Configurable SMTP Timeout and Cron Lock Timeout settings in admin panel
* Memory optimization: lazy-load email bodies per iteration instead of loading all at once
* Fix: SMTP accounts no longer blocked mid-cycle by send_interval check
* Fix: removed %i placeholder for compatibility with WordPress < 6.2
* Fix: replaced file_put_contents with WP_Filesystem API for attachment directory protection
* Removed error_log/print_r development calls from production code

= 2.2.0 =
* SMTP test connection button to verify credentials before sending
* Export/import plugin settings and SMTP accounts via XML
* Status filter on the log table (sent / error / alert)
* Auto-retry failed emails with configurable retry limit
* Resend and force-resend actions that remove the original log entry
* Cron diagnostics panel on the Retention tab
* Detection of conflicting pre_wp_mail filters from other plugins
* Info column in the log with retry count and error details
* Success message after resend redirects to queue
* Bulk delete confirmation dialog
* Security: mandatory nonce verification on all bulk actions
* Security: nonce protection on test email insertion
* Security: XXE prevention on XML import (LIBXML_NONET)
* Security: settings whitelist on import and register_setting sanitize_callback
* Security: encrypted captured SMTP passwords in queue headers
* Performance: SQL LIMIT/OFFSET pagination instead of loading all rows
* Performance: database indexes on status column (idx_status_retry, idx_status_timestamp)
* Performance: SMTP counter reset runs once per cron instead of per email
* Fix: set envelope sender ($phpmailer->Sender) alongside From header
* Fix: instant email tracking now correctly updates status on success/failure
* Fix: NOW() replaced with WordPress local time in SQL queries
* Fix: attachments stored in wp-content/uploads/tmq-attachments/ with .htaccess protection
* Fix: use wp_generate_password() for attachment subfolder names
* Code quality: JSON serialization instead of PHP serialize (with backwards compatibility)
* Code quality: strict comparisons throughout, wp_kses_post on notices, consistent wpdb formats
* Code quality: inline CSS moved to admin.css
* Browser autofill prevention on SMTP username field
* Asset cache-busting via version parameter

= 2.1.0 =
* SMTP accounts management with priority, daily and monthly sending limits
* Multiple send methods: auto, SMTP only, PHP default
* Capture and replay phpmailer_init configurations from other plugins
* Block mode: retain all emails without sending
* Log max records limit setting
* Database table for SMTP accounts
* Encrypted SMTP password storage (AES-256-CBC)

= 1.5.0 =
* Fork of Mail Queue by WDM
* Renamed all prefixes and identifiers
* New branding: Total Mail Queue

= 1.4.6 =
* Added support for the `pre_wp_mail` hook

= 1.4.5 =
* Check for incompatible plugins
* Minor bug fixes

= 1.4.4 =
* Performance improvements for large emails

= 1.4.3 =
* Updated bulk actions for log and queue lists

= 1.4.2 =
* Database improvements

= 1.4.1 =
* Refine detection for html when previewing emails
* Catch html parse errors when previewing emails

= 1.4 =
* Added support for previewing HTML emails as plain text
* Improved preview for HTML emails
* Minor bug fixes

= 1.3.1 =
* Added support for the following `wp_mail` hooks: `wp_mail_content_type`, `wp_mail_charset`, `wp_mail_from`, `wp_mail_from_name`
* Minor bug fixes

= 1.3 =
* Refactor to use WordPress Core functionality
* Added option to set the interval for sending emails in minutes or seconds
* Added feature to send emails with high priority on top of the queue
* Added feature to send emails instantly without delay bypassing the queue

= 1.2 =
* Performance and security improvements

= 1.1 =
* Resend emails
* Notification if WordPress can't send emails

= 1.0 =
* Initial release.
