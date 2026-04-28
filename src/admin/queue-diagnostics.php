<?php
/**
 * Diagnostic notices rendered above the Retention queue listing.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Admin;

use TotalMailQueue\Queue\MailInterceptor;

/**
 * Inline notices shown above the queue table on the Retention tab:
 *
 * - **Mode notice + last-cron diagnostics** — surfaces the active operation
 *   mode (Queue / Block / Disabled) and, on the happy path, the next-cron
 *   countdown plus the last cron run's metrics.
 * - **Conflict notice** — warns when another plugin has subscribed to
 *   `pre_wp_mail`, since those filters can short-circuit our send pipeline.
 *
 * Pulled out of {@see PluginPage} so the diagnostics block has a single
 * home and the page itself stays a router.
 */
final class QueueDiagnostics {

	/**
	 * Block / Queue / Disabled state notice + last-cron diagnostics.
	 *
	 * @param array<string,mixed> $options Live settings.
	 */
	public static function renderModeNotice( array $options ): void {
		$mode = (string) $options['enabled'];

		if ( '2' === $mode ) {
			echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Block Mode Active', 'total-mail-queue' ) . '</strong> — ' . esc_html__( 'All outgoing emails are being retained and will NOT be sent. No emails will leave this server.', 'total-mail-queue' ) . ' ' . wp_kses_post(
				sprintf(
					/* translators: %1$s: opening link tag, %2$s: closing link tag */
					__( 'Change this in %1$sSettings%2$s.', 'total-mail-queue' ),
					'<a href="admin.php?page=' . PluginPage::TAB_SETTINGS . '">',
					'</a>'
				)
			) . '</p></div>';
			return;
		}

		if ( '1' === $mode ) {
			$next = wp_next_scheduled( 'wp_tmq_mail_queue_hook' );
			if ( $next && $next > time() ) {
				echo '<div class="notice notice-success"><p>' . esc_html(
					sprintf(
						/* translators: %1$s: human-readable time diff, %2$s: scheduled time */
						__( 'Next sending will be triggered in %1$s at %2$s.', 'total-mail-queue' ),
						human_time_diff( $next ),
						wp_date( 'H:i', $next )
					)
				) . '</p></div>';
			}

			$last_cron = get_option( 'wp_tmq_last_cron' );
			if ( is_array( $last_cron ) ) {
				$parts   = array();
				$parts[] = '<strong>' . esc_html__( 'Last cron run:', 'total-mail-queue' ) . '</strong> ' . esc_html( (string) ( $last_cron['time'] ?? '—' ) );
				$parts[] = '<strong>' . esc_html__( 'Result:', 'total-mail-queue' ) . '</strong> ' . esc_html( (string) ( $last_cron['result'] ?? '—' ) );
				if ( isset( $last_cron['queue_total'] ) ) {
					$parts[] = '<strong>' . esc_html__( 'Queue:', 'total-mail-queue' ) . '</strong> ' . esc_html( (string) $last_cron['queue_total'] ) . ' total, ' . esc_html( (string) $last_cron['queue_batch'] ) . ' batch';
					$parts[] = '<strong>' . esc_html__( 'SMTP accounts:', 'total-mail-queue' ) . '</strong> ' . esc_html( (string) $last_cron['smtp_accounts'] );
					$parts[] = '<strong>' . esc_html__( 'Send method:', 'total-mail-queue' ) . '</strong> ' . esc_html( (string) $last_cron['send_method'] );
					$parts[] = '<strong>' . esc_html__( 'Sent:', 'total-mail-queue' ) . '</strong> ' . esc_html( (string) $last_cron['sent'] ) . ' | <strong>' . esc_html__( 'Errors:', 'total-mail-queue' ) . '</strong> ' . esc_html( (string) $last_cron['errors'] );
				}
				$class = ( ( $last_cron['result'] ?? '' ) === 'ok' ) ? 'notice-info' : 'notice-warning';
				echo '<div class="notice ' . esc_attr( $class ) . '"><p>' . wp_kses_post( implode( ' &nbsp;|&nbsp; ', $parts ) ) . '</p></div>';
			}
			return;
		}

		echo '<div class="notice notice-warning"><p>' . wp_kses_post(
			sprintf(
				/* translators: %1$s: opening link tag, %2$s: closing link tag */
				__( 'The plugin is currently disabled. Enable it in the %1$sSettings%2$s.', 'total-mail-queue' ),
				'<a href="admin.php?page=' . PluginPage::TAB_SETTINGS . '">',
				'</a>'
			)
		) . '</p></div>';
	}

	/**
	 * Warn when other code has subscribed to `pre_wp_mail`. Skip our own
	 * interceptor; everything else is reported.
	 *
	 * @param array<string,mixed> $options Live settings.
	 */
	public static function renderConflictNotice( array $options ): void {
		if ( '1' !== (string) $options['enabled'] ) {
			return;
		}

		global $wp_filter;
		if ( ! isset( $wp_filter['pre_wp_mail'] ) ) {
			return;
		}

		$other_filters = array();
		foreach ( $wp_filter['pre_wp_mail']->callbacks as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$func = $callback['function'];
				if ( is_array( $func ) ) {
					$class_or_obj = is_object( $func[0] ) ? get_class( $func[0] ) : (string) $func[0];
					if ( MailInterceptor::class === $class_or_obj ) {
						continue;
					}
					$other_filters[] = $class_or_obj . '::' . (string) $func[1];
				} elseif ( is_string( $func ) ) {
					$other_filters[] = $func;
				} else {
					$other_filters[] = __( '(closure)', 'total-mail-queue' );
				}
			}
		}
		if ( empty( $other_filters ) ) {
			return;
		}

		echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Warning: conflicting email plugin detected.', 'total-mail-queue' ) . '</strong> ';
		echo wp_kses_post(
			sprintf(
				/* translators: %1$s: filter name, %2$s: list of conflicting callbacks */
				__( 'The following filter(s) on %1$s may interfere with email sending: %2$s. Consider deactivating conflicting email plugins when using SMTP accounts from Total Mail Queue.', 'total-mail-queue' ),
				'<code>pre_wp_mail</code>',
				'<code>' . esc_html( implode( '</code>, <code>', $other_filters ) ) . '</code>'
			)
		);
		echo '</p></div>';
	}
}
