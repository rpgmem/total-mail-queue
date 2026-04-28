<?php
/**
 * Cron Information admin tab — surfaces WP-Cron status when DISABLE_WP_CRON
 * is set.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Admin\Pages;

use TotalMailQueue\Settings\Options;

/**
 * Renders the "Cron Information" tab. Only registered in the menu when
 * `DISABLE_WP_CRON` is defined truthy — at that point the admin needs a
 * place to confirm that wp-cron.php is being hit by an external scheduler
 * and to see whether any queued tasks fell behind.
 *
 * Pulled out of {@see \TotalMailQueue\Admin\PluginPage} so the diagnostic
 * logic for the past-due cron table lives next to the static help text.
 */
final class CronInfoPage {

	/**
	 * Render the tab.
	 */
	public static function render(): void {
		$options = Options::get();
		echo '<div class="tmq-box">';
		echo '<h3>' . esc_html__( 'Information: Your common WP Cron is disabled', 'total-mail-queue' ) . '</h3>';
		echo '<p>' . wp_kses_post( __( 'It looks like you deactivated the WP Cron by <i>define( \'DISABLE_WP_CRON\', true )</i>.', 'total-mail-queue' ) ) . '</p>';
		$url = esc_url( get_option( 'siteurl' ) . '/wp-cron.php' );
		echo '<p>' . wp_kses_post(
			sprintf(
				/* translators: %s: wp-cron.php URL */
				__( 'In general, this is no problem at all. We just want to remind you to make sure you\'re running the Cron manually by calling %s every couple of minutes.', 'total-mail-queue' ),
				'<a href="' . $url . '" target="_blank">' . $url . '</a>'
			)
		) . '</p>';
		echo '</div>';

		if ( ! function_exists( '_get_cron_array' ) ) {
			return;
		}
		$next_tasks = _get_cron_array();
		if ( ! $next_tasks ) {
			return;
		}

		$tasks_in_past         = false;
		$queue_task_in_past_at = 0;
		foreach ( $next_tasks as $key => $val ) {
			if ( time() <= intval( $key ) + intval( $options['queue_interval'] ) ) {
				continue;
			}
			$task_keys = array_keys( $val );
			if ( ! empty( $task_keys ) && 'wp_tmq_mail_queue_hook' === $task_keys[0] ) {
				$queue_task_in_past_at = intval( $key );
			}
			$tasks_in_past = true;
		}
		if ( ! $tasks_in_past ) {
			return;
		}

		echo '<div class="tmq-box">';
		echo '<h3>' . esc_html__( 'Attention: It seems that your WP Cron is not running. There are some jobs waiting to be completed.', 'total-mail-queue' ) . '</h3>';
		if ( $queue_task_in_past_at > 0 ) {
			echo '<p><b>' . esc_html(
				sprintf(
					/* translators: %s: human-readable time diff */
					__( 'The Queue hasn\'t been able to be executed since %s.', 'total-mail-queue' ),
					human_time_diff( $queue_task_in_past_at, time() )
				)
			) . '</b></p>';
		}
		echo '</div>';
	}
}
