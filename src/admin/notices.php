<?php
/**
 * Admin notices for last-error visibility and incompatible-plugin warnings.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Admin;

use TotalMailQueue\Cron\Diagnostics;
use TotalMailQueue\Cron\Scheduler;
use TotalMailQueue\Queue\QueueRepository;
use TotalMailQueue\Settings\Options;

/**
 * Renders the global admin notices the plugin emits.
 *
 * Two notices are produced:
 *
 * - When the most recent log row has `status = error`, all manage_options users
 *   see a high-visibility notice with the failure reason; non-admins capable of
 *   editing posts see a softer "contact your administrator" variant.
 * - On the plugin's own settings screen, warn when known wp_mail-bypassing
 *   plugins (currently MailPoet) are active.
 */
final class Notices {

	/**
	 * Hook the renderer onto admin_notices.
	 */
	public static function register(): void {
		add_action( 'admin_notices', array( self::class, 'render' ) );
	}

	/**
	 * `admin_notices` callback.
	 */
	public static function render(): void {
		$options = Options::get();
		if ( ! in_array( (string) $options['enabled'], array( '1', '2' ), true ) ) {
			return;
		}

		$last_mail = QueueRepository::lastLogRow();

		if ( $last_mail && 'error' === $last_mail['status'] ) {
			self::renderLastErrorNotice( $last_mail );
		}

		self::maybeRenderCronStalledNotice( $options );
		self::maybeRenderConflictNotice();
	}

	/**
	 * How long the queue may sit with mail waiting and nothing actually sent
	 * before the "may not be processing" notice appears.
	 */
	private const STALE_AFTER = 2 * HOUR_IN_SECONDS;

	/**
	 * Decide whether the queue is genuinely stalled — mail is waiting, nothing
	 * has been sent for a long time, and the worker isn't scheduled to act.
	 * Pure so the policy is unit-testable; {@see Notices::maybeRenderCronStalledNotice()}
	 * feeds it the live values.
	 *
	 * The staleness clock is the **last individual send**, not the last worker
	 * run: the worker keeps running (and saving diagnostics) even while every
	 * account is capped or the queue idles, so keying off runs produced false
	 * alarms while delivery was actually fine. A send within the window, a
	 * future-scheduled run (normal cadence or an intentional quota deferral),
	 * or a site that has never sent are all treated as "not stalled".
	 *
	 * @param int      $now            Current UTC unix timestamp.
	 * @param int      $pending        Rows waiting in the queue.
	 * @param int|null $next_scheduled Timestamp of the next queue event, or null.
	 * @param int      $interval       Queue interval in seconds.
	 * @param int      $last_send      UTC unix timestamp of the last individual send (0 = never sent).
	 */
	public static function isWorkerStalled( int $now, int $pending, ?int $next_scheduled, int $interval, int $last_send ): bool {
		if ( $pending <= 0 || $last_send <= 0 ) {
			return false;
		}
		// Only suspicious once delivery has actually gone quiet with mail waiting.
		if ( ( $now - $last_send ) <= self::STALE_AFTER ) {
			return false;
		}
		// It's been quiet and mail is waiting. A worker event scheduled for the
		// future (normal cadence or a deliberate quota deferral) means it's
		// still set to run, so the quiet is expected — not a stall. Only warn
		// when no event is armed at all, or the armed event is overdue, which is
		// the signature of WP-Cron not firing on a cached / low-traffic site.
		if ( null === $next_scheduled ) {
			return true;
		}
		$grace = max( 2 * max( 60, $interval ), 10 * MINUTE_IN_SECONDS );
		return $next_scheduled < ( $now - $grace );
	}

	/**
	 * Warn manage_options users when mail is piling up but the worker hasn't
	 * run in a while — the classic symptom of WP-Cron only firing while an
	 * admin is logged in (cached front-end / low traffic). Works whether or
	 * not `DISABLE_WP_CRON` is set, unlike the Cron Information tab.
	 *
	 * @param array<string,mixed> $options Plugin options snapshot.
	 */
	private static function maybeRenderCronStalledNotice( array $options ): void {
		if ( '1' !== (string) $options['enabled'] || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$next    = wp_next_scheduled( Scheduler::HOOK );
		$stalled = self::isWorkerStalled(
			time(),
			QueueRepository::pendingCount(),
			false === $next ? null : (int) $next,
			(int) $options['queue_interval'],
			Diagnostics::lastSendTimestamp()
		);
		if ( ! $stalled ) {
			return;
		}

		$cron_doc = 'https://developer.wordpress.org/plugins/cron/hooking-wp-cron-into-the-system-task-scheduler/';

		$notice  = '<div class="notice notice-warning is-dismissible">';
		$notice .= '<p><strong>' . esc_html__( 'Total Mail Queue: the queue may not be processing.', 'total-mail-queue' ) . '</strong></p>';
		$notice .= '<p>' . esc_html__( 'Emails are waiting in the queue, but none have been sent in over two hours and the worker is not scheduled to run. This usually means WP-Cron is only triggered while you are logged in — for example because the front-end is cached or gets little traffic — so the queue stalls once you log out.', 'total-mail-queue' ) . '</p>';
		$notice .= '<p>' . wp_kses_post(
			sprintf(
				/* translators: 1: opening link tag, 2: closing link tag */
				__( 'To process the queue around the clock, set up a real server cron that calls %1$swp-cron.php%2$s on a schedule.', 'total-mail-queue' ),
				'<a href="' . esc_url( $cron_doc ) . '" target="_blank" rel="noopener noreferrer">',
				'</a>'
			)
		) . '</p>';
		$notice .= '</div>';
		echo wp_kses_post( $notice );
	}

	/**
	 * Render the appropriate "last email failed" notice for the current user.
	 *
	 * @param array<string,mixed> $last_mail The most recent non-queued log row.
	 */
	private static function renderLastErrorNotice( array $last_mail ): void {
		if ( current_user_can( 'manage_options' ) ) {
			$notice  = '<div class="notice notice-error is-dismissible">';
			$notice .= '<h1>' . esc_html__( 'Attention: Your website has problems sending e-mails', 'total-mail-queue' ) . '</h1>';
			/* translators: %1$s: opening italic tag, %2$s: closing italic tag, %3$s: opening Mail Log link tag, %4$s: closing link tag */
			$notice .= '<p>' . sprintf( __( 'This is an important message from your %1$sTotal Mail Queue%2$s plugin. Please take a look at your %3$sMail Log%4$s. The last email(s) couldn\'t be sent properly.', 'total-mail-queue' ), '<i>', '</i>', '<a href="admin.php?page=wp_tmq_mail_queue-tab-log">', '</a>' ) . '</p>';
			/* translators: %s: error message */
			$notice .= '<p>' . sprintf( __( 'Last error message was: %s', 'total-mail-queue' ), '<b>' . esc_html( (string) $last_mail['info'] ) . '</b>' ) . '</p>';
			$notice .= '</div>';
			echo wp_kses_post( $notice );
			return;
		}

		if ( current_user_can( 'edit_posts' ) ) {
			$notice  = '<div class="notice notice-error is-dismissible">';
			$notice .= '<h1>' . esc_html__( 'Attention: Your website has problems sending e-mails', 'total-mail-queue' ) . '</h1>';
			$notice .= '<p>' . esc_html__( 'Please contact your Administrator. It seems that WordPress is not able to send emails.', 'total-mail-queue' ) . '</p>';
			/* translators: %s: error message */
			$notice .= '<p>' . sprintf( __( 'Last error message: %s', 'total-mail-queue' ), '<b>' . esc_html( (string) $last_mail['info'] ) . '</b>' ) . '</p>';
			$notice .= '</div>';
			echo wp_kses_post( $notice );
		}
	}

	/**
	 * On the plugin's main settings page, warn when known wp_mail-bypassing
	 * plugins (currently MailPoet) are active.
	 */
	private static function maybeRenderConflictNotice(): void {
		$current_screen = get_current_screen();
		if ( ! $current_screen || 'toplevel_page_wp_tmq_mail_queue' !== $current_screen->base ) {
			return;
		}

		$bypassing = array(
			'mailpoet/mailpoet.php' => 'MailPoet',
		);
		$active    = array();
		foreach ( array_keys( $bypassing ) as $plugin ) {
			if ( is_plugin_active( $plugin ) ) {
				$active[] = $plugin;
			}
		}
		if ( ! $active ) {
			return;
		}

		$notice  = '<div class="notice notice-warning is-dismissible">';
		$notice .= '<p>';
		$notice .= '<strong>' . esc_html__( 'Please note:', 'total-mail-queue' ) . '</strong>';
		$notice .= '<br />' . wp_kses_post( __( 'This plugin is not supported when using in combination with plugins that do not use the standard <i>wp_mail()</i> function.', 'total-mail-queue' ) );
		$notice .= '</p>';
		$notice .= '<p>';
		$notice .= wp_kses_post( __( 'It seems you are using the following plugin(s) that do not use <i>wp_mail()</i>:', 'total-mail-queue' ) );
		$notice .= '<br />' . implode(
			', ',
			array_map(
				static function ( $plugin ) use ( $bypassing ) {
					return esc_html( $bypassing[ $plugin ] );
				},
				$active
			)
		);
		$notice .= '</p>';
		$notice .= '<p><a href="' . esc_url( get_admin_url( null, 'admin.php?page=wp_tmq_mail_queue-tab-faq' ) ) . '">' . esc_html__( 'More information', 'total-mail-queue' ) . '</a></p>';
		$notice .= '</div>';
		echo wp_kses_post( $notice );
	}
}
