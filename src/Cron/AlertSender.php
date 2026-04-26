<?php
/**
 * Queue-overflow alert email.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Cron;

use TotalMailQueue\Queue\QueueRepository;
use TotalMailQueue\Settings\Options;

/**
 * If the queue has more pending rows than the configured threshold AND
 * no alert has been fired in the last 6 hours, drop a row of status
 * `alert` into the queue (so it shows up in the log) and send an email
 * to the admin via `wp_mail()`.
 */
final class AlertSender {

	/**
	 * Send the alert when the configured conditions are met.
	 *
	 * @param int $pending_total Result of {@see QueueRepository::pendingCount()}.
	 * @param int $batch_size    Number of rows the cron is about to send this run.
	 */
	public static function maybeAlert( int $pending_total, int $batch_size ): void {
		$options = Options::get();

		if ( '1' !== $options['alert_enabled'] ) {
			return;
		}
		if ( $pending_total <= (int) $options['email_amount'] ) {
			return;
		}
		if ( QueueRepository::recentAlertExists() ) {
			return;
		}

		$alert_subject = self::buildSubject();
		$alert_message = self::buildMessage( $pending_total );
		$info_payload  = wp_json_encode(
			array(
				'in_queue'       => (string) $batch_size,
				'email_amount'   => (int) $options['email_amount'],
				'queue_amount'   => (int) $options['queue_amount'],
				'queue_interval' => (int) $options['queue_interval'],
			)
		);

		QueueRepository::insert(
			array(
				'timestamp' => current_time( 'mysql', false ),
				'recipient' => sanitize_email( $options['email'] ),
				'subject'   => $alert_subject,
				'message'   => $alert_message,
				'status'    => 'alert',
				'info'      => $info_payload,
			)
		);
		wp_mail( $options['email'], $alert_subject, $alert_message );
	}

	/**
	 * Compose the alert subject.
	 */
	private static function buildSubject(): string {
		/* translators: %s: blog name */
		return sprintf( __( '🔴 WordPress Total Mail Queue Alert - %s', 'total-mail-queue' ), esc_html( get_option( 'blogname' ) ) );
	}

	/**
	 * Compose the alert body (plain text).
	 *
	 * @param int $pending_total Number of pending rows in the queue.
	 */
	private static function buildMessage( int $pending_total ): string {
		$lines  = __( 'Hi,', 'total-mail-queue' );
		$lines .= "\n\n";
		/* translators: %s: site URL */
		$lines .= sprintf( __( 'this is an important message from your WordPress website %s.', 'total-mail-queue' ), esc_url( get_option( 'siteurl' ) ) );
		$lines .= "\n";
		/* translators: %s: number of emails in queue */
		$lines .= "\n" . sprintf( __( 'The Total Mail Queue Plugin has detected that your website tries to send more emails than expected (currently %s).', 'total-mail-queue' ), $pending_total );
		$lines .= "\n" . __( 'Please take a close look at the email queue, because it contains more messages than the specified limit.', 'total-mail-queue' );
		$lines .= "\n";
		$lines .= "\n" . __( 'In case this is the usual amount of emails, you can adjust the threshold for alerts in the settings of your Total Mail Queue Plugin.', 'total-mail-queue' );
		$lines .= "\n\n-- \n";
		$lines .= admin_url();
		return $lines;
	}
}
