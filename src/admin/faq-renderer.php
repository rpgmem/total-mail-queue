<?php
/**
 * Static markup for the FAQ tab.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Admin;

/**
 * Long-form help shown under the "FAQ" tab.
 *
 * Split into its own class so {@see PluginPage} doesn't grow beyond what's
 * comfortable to skim. The content matches the legacy procedural FAQ verbatim
 * (verified during the N7b port).
 */
final class FaqRenderer {

	/**
	 * Render the FAQ markup. Live settings drive the "Current state" lines.
	 *
	 * @param array<string,mixed> $options Live settings (Options::get()).
	 */
	public static function render( array $options ): void {
		self::sectionWhatItDoes( $options );
		self::sectionDoesntChangeHow();
		self::sectionCachingPlugins();
		self::sectionProxyCaching();
		self::sectionAttachments();
		self::sectionAlerts( $options );
		self::sectionPriorityHeader();
		self::sectionInstantHeader();
		self::sectionSendMethod();
		self::sectionTestMail( $options );
	}

	/**
	 * "How does this Plugin work?" — overview + live mode indicator.
	 *
	 * @param array<string,mixed> $options Live settings.
	 */
	private static function sectionWhatItDoes( array $options ): void {
		echo '<div class="tmq-box">';
		echo '<h3>' . esc_html__( 'How does this Plugin work?', 'total-mail-queue' ) . '</h3>';
		echo '<p>' . wp_kses_post(
			sprintf(
				/* translators: %1$s: wp_mail() link, %2$s: WP Cron label */
				__( 'If enabled this plugin intercepts the %1$s function. Instead of sending the mails directly, it stores them in the database and sends them step by step with a delay during the %2$s.', 'total-mail-queue' ),
				'<a target="_blank" href="https://developer.wordpress.org/reference/functions/wp_mail/"><i>wp_mail()</i></a>',
				'<i>WP Cron</i>'
			)
		) . '</p>';

		echo '<p>' . esc_html__( 'Current state:', 'total-mail-queue' ) . ' ';
		$mode = (string) $options['enabled'];
		if ( '1' === $mode ) {
			echo '<b class="tmq-ok">' . esc_html__( 'The plugin is enabled', 'total-mail-queue' ) . '</b> ' . wp_kses_post(
				sprintf(
					/* translators: %1$s: wp_mail() link, %2$s: opening Queue link tag, %3$s: closing link tag */
					__( 'All Mails sent through %1$s are delayed by the %2$sQueue%3$s.', 'total-mail-queue' ),
					'<a target="_blank" href="https://developer.wordpress.org/reference/functions/wp_mail/"><i>wp_mail()</i></a>',
					'<a href="admin.php?page=' . PluginPage::TAB_QUEUE . '">',
					'</a>'
				)
			);
		} elseif ( '2' === $mode ) {
			echo '<b class="tmq-warning">' . esc_html__( 'Block mode is active', 'total-mail-queue' ) . '</b>. ' . esc_html__( 'All outgoing emails are being retained and will NOT be sent.', 'total-mail-queue' );
		} else {
			echo '<b>' . esc_html__( 'The plugin is disabled', 'total-mail-queue' ) . '</b>. ' . esc_html__( 'The plugin has no impact at the moment, no Mails inside the Queue are going to be sent.', 'total-mail-queue' );
		}
		echo '</p>';
		echo '</div>';
	}

	/**
	 * "Does this plugin change how emails are sent?" — clarifies wp_mail() usage.
	 */
	private static function sectionDoesntChangeHow(): void {
		echo '<div class="tmq-box">';
		echo '<h3>' . wp_kses_post(
			sprintf(
				/* translators: %1$s: opening bold tag, %2$s: closing bold tag */
				__( 'Does this plugin change the way %1$sHOW%2$s emails are sent?', 'total-mail-queue' ),
				'<b>',
				'</b>'
			)
		) . '</h3>';
		echo '<p>' . wp_kses_post(
			sprintf(
				/* translators: %1$s: opening bold tag, %2$s: closing bold tag, %3$s: wp_mail() link */
				__( 'No, don\'t worry. This plugin only affects %1$sWHEN%2$s emails are sent, not how. It delays the sending (by the Queue), nonetheless all emails are sent through the standard %3$s function.', 'total-mail-queue' ),
				'<b>',
				'</b>',
				'<a target="_blank" href="https://developer.wordpress.org/reference/functions/wp_mail/"><i>wp_mail()</i></a>'
			)
		) . '</p>';
		echo '<p>' . esc_html__( 'If you use SMTP for sending, or an external service like Mailgun, everything will still work as expected.', 'total-mail-queue' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Caching-plugin caveat (need to call wp-cron.php manually).
	 */
	private static function sectionCachingPlugins(): void {
		echo '<div class="tmq-box">';
		echo '<h3>' . wp_kses_post(
			sprintf(
				/* translators: %1$s: example caching plugin name */
				__( 'Does this plugin work, if I have a Caching Plugin installed? E.g. %1$s or similar?', 'total-mail-queue' ),
				'<i>W3 Total Cache</i>'
			)
		) . '</h3>';
		echo '<p>' . wp_kses_post(
			sprintf(
				/* translators: %1$s: W3 Total Cache, %2$s: WP Rocket, %3$s: wp-cron.php link */
				__( 'If you\'re using a Caching plugin like %1$s, %2$s or any other caching solution which generates static html-files and serves them to visitors, you\'ll have to make sure you\'re calling the %3$s manually every couple of minutes.', 'total-mail-queue' ),
				'<i>W3 Total Cache</i>',
				'<i>WP Rocket</i>',
				'<a href="' . esc_url( get_option( 'siteurl' ) ) . '/wp-cron.php" target="_blank">' . esc_html__( 'wp-cron file', 'total-mail-queue' ) . '</a>'
			)
		) . '</p>';
		echo '<p>' . esc_html__( 'Otherwise your normal WP Cron wouldn\'t be called as often as it should be and scheduled messages would be sent with big delays.', 'total-mail-queue' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Proxy-caching caveat (NGINX et al.).
	 */
	private static function sectionProxyCaching(): void {
		echo '<div class="tmq-box">';
		echo '<h3>' . esc_html__( 'What about Proxy-Caching, e.g. NGINX?', 'total-mail-queue' ) . '</h3>';
		echo '<p>' . wp_kses_post(
			sprintf(
				/* translators: %s: WordPress Cron link */
				__( 'Same situation here. Please make sure you\'re calling the %s by an external service or your webhoster every couple of minutes.', 'total-mail-queue' ),
				'<a href="' . esc_url( get_option( 'siteurl' ) ) . '/wp-cron.php" target="_blank">' . esc_html__( 'WordPress Cron', 'total-mail-queue' ) . '</a>'
			)
		) . '</p>';
		echo '</div>';
	}

	/**
	 * Confirms attachments are queued alongside their email.
	 */
	private static function sectionAttachments(): void {
		echo '<div class="tmq-box">';
		echo '<h3>' . esc_html__( 'My form builder supports attachments. What about them?', 'total-mail-queue' ) . '</h3>';
		echo '<p>' . esc_html__( 'You are covered. All attachments are stored temporarily in the queue until they are sent along with their corresponding emails.', 'total-mail-queue' ) . '</p>';
		echo '</div>';
	}

	/**
	 * "What are Queue alerts?" — explains the email-flood early-warning + live state.
	 *
	 * @param array<string,mixed> $options Live settings.
	 */
	private static function sectionAlerts( array $options ): void {
		echo '<div class="tmq-box">';
		echo '<h3>' . esc_html__( 'What are Queue alerts?', 'total-mail-queue' ) . '</h3>';
		echo '<p>' . esc_html__( 'This is a simple and effective way to improve the security of your WordPress installation.', 'total-mail-queue' ) . '</p>';
		echo '<p>' . wp_kses_post(
			sprintf(
				/* translators: %1$s: wp_mail() link */
				__( 'Imagine: In case your website is sending spam through %1$s, the email Queue would fill up very quickly preventing your website from sending so many spam emails at once. This gives you time and avoids a lot of trouble.', 'total-mail-queue' ),
				'<a target="_blank" href="https://developer.wordpress.org/reference/functions/wp_mail/"><i>wp_mail()</i></a>'
			)
		) . '</p>';
		echo '<p>' . esc_html__( 'Queue Alerts warn you, if the Queue is longer than usual. You decide at which point you want to be alerted. So you get the chance to have a look if there might be something wrong on the website.', 'total-mail-queue' ) . '</p>';
		echo '<p>' . esc_html__( 'Current state:', 'total-mail-queue' ) . ' ';
		if ( '1' === (string) $options['alert_enabled'] ) {
			echo '<b class="tmq-ok">' . esc_html__( 'Alerts are enabled', 'total-mail-queue' ) . '</b> ' . wp_kses_post(
				sprintf(
					/* translators: %1$s: email amount threshold, %2$s: alert email address */
					__( 'If more than %1$s emails are waiting in the Queue, WordPress will send an alert email to %2$s.', 'total-mail-queue' ),
					esc_html( (string) $options['email_amount'] ),
					'<i>' . esc_html( (string) $options['email'] ) . '</i>'
				)
			);
		} else {
			echo '<b>' . esc_html__( 'Alerting is disabled', 'total-mail-queue' ) . '</b>. ' . esc_html__( 'No alerts will be sent.', 'total-mail-queue' );
		}
		echo '</p>';
		echo '<p>' . esc_html__( 'Please note: This plugin will only send one alert every six hours.', 'total-mail-queue' ) . '</p>';
		echo '</div>';
	}

	/**
	 * `X-Mail-Queue-Prio: High` header documentation + 3 sample integrations.
	 */
	private static function sectionPriorityHeader(): void {
		echo '<div class="tmq-box">';
		echo '<h3>' . esc_html__( 'Can I add emails with a high priority to the queue?', 'total-mail-queue' ) . '</h3>';
		echo '<p>' . wp_kses_post( __( 'Yes, you can add the custom <i>`X-Mail-Queue-Prio`</i> header set to <i>`High`</i> to your email. High priority emails will be sent through the standard Total Mail Queue sending cycle but before all normal emails lacking a priority header in the queue.', 'total-mail-queue' ) ) . '</p>';

		echo '<p><b>' . esc_html__( 'Example 1 (add priority to Woocommerce emails):', 'total-mail-queue' ) . '</b></p>';
		echo '<pre><code>add_filter(\'woocommerce_mail_callback_params\',function ( $array ) {
    $prio_header = \'X-Mail-Queue-Prio: High\';
    if (is_array($array[3])) {
        $array[3][] = $prio_header;
    } else {
        $array[3] .= $array[3] ? "\r\n" : \'\';
        $array[3] .= $prio_header;
    }
    return $array;
},10,1);</code></pre>';

		echo '<p><b>' . esc_html__( 'Example 2 (add priority to Contact Form 7 form emails):', 'total-mail-queue' ) . '</b></p>';
		echo '<p>' . wp_kses_post( __( 'When editing a form in Contact Form 7 just add an additional line to the <i>`Additional Headers`</i> field under the <i>`Mail`</i> tab panel.', 'total-mail-queue' ) ) . '</p>';
		echo '<pre><code>X-Mail-Queue-Prio: High</code></pre>';

		echo '<p><b>' . esc_html__( 'Example 3 (add priority to WordPress reset password emails):', 'total-mail-queue' ) . '</b></p>';
		echo '<pre><code>add_filter(\'retrieve_password_notification_email\', function ($defaults, $key, $user_login, $user_data) {
    $prio_header = \'X-Mail-Queue-Prio: High\';
    if (is_array($defaults[\'headers\'])) {
        $defaults[\'headers\'][] = $prio_header;
    } else {
        $defaults[\'headers\'] .= $defaults[\'headers\'] ? "\r\n" : \'\';
        $defaults[\'headers\'] .= $prio_header;
    }
    return $defaults;
}, 10, 4);</code></pre>';
		echo '</div>';
	}

	/**
	 * `X-Mail-Queue-Prio: Instant` header documentation + 3 sample integrations.
	 */
	private static function sectionInstantHeader(): void {
		echo '<div class="tmq-box">';
		echo '<h3>' . wp_kses_post( __( 'Can I send emails <i>instantly</i> without going through the queue?', 'total-mail-queue' ) ) . '</h3>';
		echo '<p>' . esc_html__( 'Yes, this is possible (if you absolutely need to do this).', 'total-mail-queue' ) . '</p>';
		echo '<p>' . wp_kses_post( __( 'For this you can add the custom <i>`X-Mail-Queue-Prio`</i> header set to <i>`Instant`</i> to your email. These emails are sent instantly circumventing the mail queue. They still appear in the Total Mail Queue log flagged as `instant`.', 'total-mail-queue' ) ) . '</p>';
		echo '<p>' . esc_html__( 'Mind that this is a potential security risk and should be considered carefully. Please use only as an exception.', 'total-mail-queue' ) . '</p>';

		echo '<p><b>' . esc_html__( 'Example 1 (instantly send Woocommerce emails):', 'total-mail-queue' ) . '</b></p>';
		echo '<pre><code>add_filter(\'woocommerce_mail_callback_params\',function ( $array ) {
    $prio_header = \'X-Mail-Queue-Prio: Instant\';
    if (is_array($array[3])) {
        $array[3][] = $prio_header;
    } else {
        $array[3] .= $array[3] ? "\r\n" : \'\';
        $array[3] .= $prio_header;
    }
    return $array;
},10,1);</code></pre>';

		echo '<p><b>' . esc_html__( 'Example 2 (instantly send Contact Form 7 form emails):', 'total-mail-queue' ) . '</b></p>';
		echo '<p>' . wp_kses_post( __( 'When editing a form in Contact Form 7 just add an additional line to the <i>`Additional Headers`</i> field under the <i>`Mail`</i> tab panel.', 'total-mail-queue' ) ) . '</p>';
		echo '<pre><code>X-Mail-Queue-Prio: Instant</code></pre>';

		echo '<p><b>' . esc_html__( 'Example 3 (instantly send WordPress reset password emails):', 'total-mail-queue' ) . '</b></p>';
		echo '<pre><code>add_filter(\'retrieve_password_notification_email\', function ($defaults, $key, $user_login, $user_data) {
    $prio_header = \'X-Mail-Queue-Prio: Instant\';
    if (is_array($defaults[\'headers\'])) {
        $defaults[\'headers\'][] = $prio_header;
    } else {
        $defaults[\'headers\'] .= $defaults[\'headers\'] ? "\r\n" : \'\';
        $defaults[\'headers\'] .= $prio_header;
    }
    return $defaults;
}, 10, 4);</code></pre>';
		echo '</div>';
	}

	/**
	 * Explains the `auto` / `smtp` / `php` Send Method options.
	 */
	private static function sectionSendMethod(): void {
		echo '<div class="tmq-box">';
		echo '<h3>' . esc_html__( 'What is the "Send Method" setting?', 'total-mail-queue' ) . '</h3>';
		echo '<p>' . esc_html__( 'The Send Method setting controls how emails from the retention queue are delivered. There are three options:', 'total-mail-queue' ) . '</p>';
		echo '<ul>';
		echo '<li><b>' . esc_html__( 'Automatic', 'total-mail-queue' ) . '</b> — ' . esc_html__( 'This is the default. The plugin will first try to use an SMTP account configured in the SMTP Accounts tab. If no SMTP account is available (none configured or all have reached their limits), it will try to replay any captured SMTP configuration from other plugins. If neither is available, it falls back to the standard WordPress wp_mail() function.', 'total-mail-queue' ) . '</li>';
		echo '<li><b>' . esc_html__( 'Plugin SMTP only', 'total-mail-queue' ) . '</b> — ' . esc_html__( 'Emails will ONLY be sent via SMTP accounts configured in this plugin. If no account is available (limits reached or none configured), emails will remain in the retention queue waiting until an SMTP account becomes available. This is useful if you want to guarantee all emails go through your own SMTP servers.', 'total-mail-queue' ) . '</li>';
		echo '<li><b>' . esc_html__( 'WordPress default', 'total-mail-queue' ) . '</b> — ' . esc_html__( 'Ignores all SMTP accounts configured in this plugin and any captured configurations. Emails are sent using whatever wp_mail() does by default — which could be the PHP mail() function or another SMTP plugin like WP Mail SMTP.', 'total-mail-queue' ) . '</li>';
		echo '</ul>';
		echo '</div>';
	}

	/**
	 * "Want to put a test email into the Queue?" — nonce'd self-link.
	 *
	 * @param array<string,mixed> $options Live settings.
	 */
	private static function sectionTestMail( array $options ): void {
		echo '<div class="tmq-box">';
		echo '<h3>' . esc_html__( 'Want to put a test email into the Queue?', 'total-mail-queue' ) . '</h3>';
		$url = wp_nonce_url( 'admin.php?page=' . PluginPage::TAB_QUEUE . '&addtestmail', 'wp_tmq_addtestmail' );
		echo '<p><a class="button" href="' . esc_url( $url ) . '">' . esc_html(
			sprintf(
				/* translators: %s: admin email address */
				__( 'Sure! Put a Test Email for %s into the Queue', 'total-mail-queue' ),
				(string) $options['email']
			)
		) . '</a></p>';
		echo '</div>';
	}
}
