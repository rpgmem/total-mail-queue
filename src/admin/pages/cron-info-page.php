<?php
/**
 * Cron admin tab — the worker's schedule, WP-Cron's health and the line a
 * server cron needs.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Admin\Pages;

use TotalMailQueue\Admin\Access;
use TotalMailQueue\Admin\Button;
use TotalMailQueue\Admin\Icons;
use TotalMailQueue\Cron\Diagnostics;
use TotalMailQueue\Cron\Scheduler;
use TotalMailQueue\Queue\QueueRepository;
use TotalMailQueue\Settings\Options;

/**
 * Renders the "Cron" tab, shown on every site: the queue is only as reliable
 * as the cron that drains it, and the question "is it running?" comes up
 * whether or not `DISABLE_WP_CRON` is set.
 *
 * Three cards: the worker (mode, state, next and last run), WP-Cron (the visit
 * trigger and any event left overdue), and the server cron line. The last one
 * prints the WordPress path, so it is shown to administrators only.
 */
final class CronInfoPage {

	/**
	 * Server-cron frequencies offered, in minutes.
	 */
	public const FREQUENCIES = array( 1, 2, 5, 10, 15 );

	/**
	 * How a server cron reaches WordPress: method => label.
	 *
	 * @return array<string,string>
	 */
	public static function methods(): array {
		return array(
			'wpcli' => __( 'WP-CLI (recommended)', 'total-mail-queue' ),
			'wget'  => __( 'wget', 'total-mail-queue' ),
			'curl'  => __( 'curl', 'total-mail-queue' ),
		);
	}

	/**
	 * Render the tab.
	 */
	public static function render(): void {
		$options = Options::get();
		self::renderWorker( $options );
		self::renderWpCron( $options, defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON );
		if ( Access::isAdmin() ) {
			self::renderServerCron( (int) $options['queue_interval'] );
		}
	}

	/**
	 * What the worker is doing, from the live schedule. Pure so the policy is
	 * unit-testable; {@see CronInfoPage::renderWorker()} feeds it.
	 *
	 * - `off`: the plugin is not in Queue mode, so nothing is scheduled on purpose.
	 * - `idle`: nothing scheduled and nothing waiting; the next email wakes it.
	 * - `stalled`: mail is waiting and nothing is scheduled to send it.
	 * - `overdue`: a run is scheduled but well past due, the sign of a WP-Cron
	 *   that is not firing.
	 * - `deferred`: the next run is later than one interval away, which only a
	 *   quota deferral does.
	 * - `active`: running at its normal cadence.
	 *
	 * @param string   $mode     The `enabled` setting.
	 * @param int      $pending  Rows waiting in the queue.
	 * @param int|null $next     Next scheduled run, or null.
	 * @param int      $now      Current UTC unix timestamp.
	 * @param int      $interval Queue interval in seconds.
	 */
	public static function workerState( string $mode, int $pending, ?int $next, int $now, int $interval ): string {
		if ( '1' !== $mode ) {
			return 'off';
		}
		if ( null === $next ) {
			return $pending > 0 ? 'stalled' : 'idle';
		}
		$grace = max( 2 * max( 60, $interval ), 10 * MINUTE_IN_SECONDS );
		if ( $next < $now - $grace ) {
			return 'overdue';
		}
		if ( $next > $now + $interval + MINUTE_IN_SECONDS ) {
			return 'deferred';
		}
		return 'active';
	}

	/**
	 * The worker card.
	 *
	 * @param array<string,mixed> $options Live settings.
	 */
	public static function renderWorker( array $options ): void {
		$now      = time();
		$interval = (int) $options['queue_interval'];
		$pending  = QueueRepository::pendingCount();
		$next_raw = wp_next_scheduled( Scheduler::HOOK );
		$next     = false === $next_raw ? null : (int) $next_raw;
		$state    = self::workerState( (string) $options['enabled'], $pending, $next, $now, $interval );

		$states = array(
			'off'      => array( '', __( 'Not running: the plugin is not in Queue mode, so nothing is sent from the queue.', 'total-mail-queue' ) ),
			'idle'     => array( 'tmq-ok', __( 'Asleep: the queue is empty. The next email wakes it.', 'total-mail-queue' ) ),
			'active'   => array( 'tmq-ok', __( 'Running at the configured interval.', 'total-mail-queue' ) ),
			'deferred' => array( '', __( 'Waiting: every SMTP account is at a limit, so the next run is set for when one frees up.', 'total-mail-queue' ) ),
			'overdue'  => array( 'tmq-warning', __( 'Overdue: the next run is past due. WP-Cron is not firing on this site.', 'total-mail-queue' ) ),
			'stalled'  => array( 'tmq-warning', __( 'Stalled: emails are waiting and no run is scheduled.', 'total-mail-queue' ) ),
		);

		echo '<div class="tmq-box tmq-settings-card">';
		echo Icons::heading( 'cron', __( 'Queue worker', 'total-mail-queue' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons::heading() escapes the title and emits fixed markup.
		echo '<table class="form-table" role="presentation"><tbody>';

		$class = $states[ $state ][0];
		$label = $states[ $state ][1];
		self::row( __( 'State', 'total-mail-queue' ), '' === $class ? esc_html( $label ) : '<span class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</span>' );

		$next_text = null === $next ? esc_html__( 'Not scheduled', 'total-mail-queue' ) : esc_html( self::moment( $next, $now ) );
		self::row( __( 'Next run', 'total-mail-queue' ), $next_text );

		self::row(
			__( 'Interval', 'total-mail-queue' ),
			esc_html(
				sprintf(
					/* translators: %s: human-readable duration, e.g. "5 mins" */
					__( 'Every %s', 'total-mail-queue' ),
					human_time_diff( 0, max( 1, $interval ) )
				)
			)
		);

		self::row( __( 'Waiting in the queue', 'total-mail-queue' ), esc_html( number_format_i18n( $pending ) ) );

		$last = get_option( Diagnostics::OPTION_NAME );
		if ( is_array( $last ) && isset( $last['time'] ) ) {
			$stamp     = (int) get_gmt_from_date( (string) $last['time'], 'U' );
			$last_text = esc_html( self::moment( $stamp, $now ) ) . ' — ' . esc_html( (string) ( $last['result'] ?? '—' ) );
			if ( isset( $last['sent'] ) ) {
				$last_text .= ' ' . esc_html(
					sprintf(
						/* translators: 1: emails sent in that run, 2: errors in that run */
						__( '(%1$d sent, %2$d errors)', 'total-mail-queue' ),
						(int) $last['sent'],
						(int) ( $last['errors'] ?? 0 )
					)
				);
			}
		} else {
			$last_text = esc_html__( 'Never', 'total-mail-queue' );
		}
		self::row( __( 'Last run', 'total-mail-queue' ), $last_text );

		$last_send = Diagnostics::lastSendTimestamp();
		self::row(
			__( 'Last email sent', 'total-mail-queue' ),
			0 === $last_send ? esc_html__( 'Never', 'total-mail-queue' ) : esc_html( self::moment( $last_send, $now ) )
		);

		echo '</tbody></table>';
		echo '</div>';
	}

	/**
	 * The WP-Cron card: whether the visit trigger is on, and any event left
	 * overdue.
	 *
	 * @param array<string,mixed> $options  Live settings.
	 * @param bool                $disabled Whether `DISABLE_WP_CRON` is set.
	 */
	public static function renderWpCron( array $options, bool $disabled ): void {
		echo '<div class="tmq-box tmq-settings-card">';
		echo Icons::heading( 'advanced', __( 'WP-Cron', 'total-mail-queue' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons::heading() escapes the title and emits fixed markup.

		if ( $disabled ) {
			echo '<p>' . wp_kses_post( __( 'The visit trigger is off (<code>DISABLE_WP_CRON</code> is set in wp-config.php), so WP-Cron runs only when something calls it. Make sure a server cron does, every minute or two.', 'total-mail-queue' ) ) . '</p>';
		} else {
			echo '<p>' . esc_html__( 'WP-Cron runs when someone visits the site. On a quiet site, or one served from a full-page cache, the queue can wait much longer than its interval.', 'total-mail-queue' ) . '</p>';
			echo '<p>' . wp_kses_post( __( 'For sending you can rely on, add a server cron that calls WP-Cron every minute or two, then turn the visit trigger off with <code>define( \'DISABLE_WP_CRON\', true );</code> in wp-config.php.', 'total-mail-queue' ) ) . '</p>';
		}

		$now     = time();
		$overdue = self::overdueEvents( (int) $options['queue_interval'], $now );
		if ( array() === $overdue ) {
			echo '<p><span class="tmq-ok">' . esc_html__( 'No scheduled event is overdue.', 'total-mail-queue' ) . '</span></p>';
			echo '</div>';
			return;
		}

		echo '<p><span class="tmq-warning">' . esc_html(
			sprintf(
				/* translators: %d: number of overdue WP-Cron events */
				_n( '%d scheduled event is overdue: WP-Cron does not seem to be running.', '%d scheduled events are overdue: WP-Cron does not seem to be running.', count( $overdue ), 'total-mail-queue' ),
				count( $overdue )
			)
		) . '</span></p>';
		if ( isset( $overdue[ Scheduler::HOOK ] ) ) {
			echo '<p><b>' . esc_html(
				sprintf(
					/* translators: %s: human-readable time difference */
					__( 'The queue worker has been waiting to run for %s.', 'total-mail-queue' ),
					human_time_diff( $overdue[ Scheduler::HOOK ], $now )
				)
			) . '</b></p>';
		}
		echo '</div>';
	}

	/**
	 * Hooks whose next run is more than one queue interval past due, with the
	 * earliest such run for each.
	 *
	 * @param int $interval Queue interval in seconds.
	 * @param int $now      Current UTC unix timestamp.
	 * @return array<string,int> Hook => timestamp it was due.
	 */
	public static function overdueEvents( int $interval, int $now ): array {
		$cron = _get_cron_array();
		$late = array();
		foreach ( (array) $cron as $timestamp => $hooks ) {
			if ( $now <= (int) $timestamp + $interval ) {
				continue;
			}
			foreach ( array_keys( (array) $hooks ) as $hook ) {
				$hook = (string) $hook;
				if ( ! isset( $late[ $hook ] ) || (int) $timestamp < $late[ $hook ] ) {
					$late[ $hook ] = (int) $timestamp;
				}
			}
		}
		return $late;
	}

	/**
	 * The server cron card: frequency and method selects that switch the line
	 * shown, and a copy button. Every combination is printed up front, so the
	 * script only picks one.
	 *
	 * @param int $interval Queue interval in seconds, which picks the default frequency.
	 */
	public static function renderServerCron( int $interval ): void {
		$default_frequency = self::FREQUENCIES[0];
		foreach ( self::FREQUENCIES as $minutes ) {
			if ( $minutes * MINUTE_IN_SECONDS <= $interval ) {
				$default_frequency = $minutes;
			}
		}

		$lines = array();
		foreach ( array_keys( self::methods() ) as $method ) {
			foreach ( self::FREQUENCIES as $minutes ) {
				$lines[ $method . ':' . $minutes ] = self::serverLine( $method, $minutes, ABSPATH, site_url( 'wp-cron.php?doing_wp_cron' ) );
			}
		}

		echo '<div class="tmq-box tmq-settings-card tmq-server-cron">';
		echo Icons::heading( 'send', __( 'Server cron', 'total-mail-queue' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons::heading() escapes the title and emits fixed markup.
		echo '<p>' . esc_html__( 'Add this line to the crontab of the user that runs the site (crontab -e), or to your hosting panel\'s cron jobs. It only wakes WP-Cron: how often the queue sends is still set by the interval in Settings.', 'total-mail-queue' ) . '</p>';
		echo '<table class="form-table" role="presentation"><tbody>';

		echo '<tr><th scope="row"><label for="tmq-cron-frequency">' . esc_html__( 'Frequency', 'total-mail-queue' ) . '</label></th><td>';
		echo '<select id="tmq-cron-frequency" data-tmq-cron-part="frequency">';
		foreach ( self::FREQUENCIES as $minutes ) {
			echo '<option value="' . esc_attr( (string) $minutes ) . '"' . selected( $minutes, $default_frequency, false ) . '>' . esc_html(
				sprintf(
					/* translators: %d: number of minutes */
					_n( 'Every %d minute', 'Every %d minutes', $minutes, 'total-mail-queue' ),
					$minutes
				)
			) . '</option>';
		}
		echo '</select></td></tr>';

		echo '<tr><th scope="row"><label for="tmq-cron-method">' . esc_html__( 'Method', 'total-mail-queue' ) . '</label></th><td>';
		echo '<select id="tmq-cron-method" data-tmq-cron-part="method">';
		foreach ( self::methods() as $method => $label ) {
			echo '<option value="' . esc_attr( $method ) . '">' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'WP-CLI runs the due events directly, with no web request. Use wget or curl when WP-CLI is not installed on the server.', 'total-mail-queue' ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row"><label for="tmq-cron-line">' . esc_html__( 'Crontab line', 'total-mail-queue' ) . '</label></th><td>';
		echo '<input type="text" id="tmq-cron-line" class="large-text code" readonly value="' . esc_attr( $lines[ 'wpcli:' . $default_frequency ] ) . '" data-tmq-cron-lines="' . esc_attr( (string) wp_json_encode( $lines ) ) . '" /> ';
		Button::render(
			array(
				'label' => __( 'Copy', 'total-mail-queue' ),
				'icon'  => 'copy',
				'attrs' => array(
					'data-tmq-copy'   => '#tmq-cron-line',
					'data-tmq-copied' => __( 'Copied', 'total-mail-queue' ),
				),
			)
		);
		echo '</td></tr>';

		echo '</tbody></table>';
		echo '</div>';
	}

	/**
	 * One crontab line.
	 *
	 * @param string $method  `wpcli`, `wget` or `curl`.
	 * @param int    $minutes Frequency in minutes.
	 * @param string $path    WordPress root, for WP-CLI.
	 * @param string $url     wp-cron.php URL, for wget and curl.
	 */
	public static function serverLine( string $method, int $minutes, string $path, string $url ): string {
		$schedule = 1 === $minutes ? '* * * * *' : '*/' . $minutes . ' * * * *';
		switch ( $method ) {
			case 'wget':
				return $schedule . ' wget -q -O - ' . self::shellQuote( $url ) . ' >/dev/null 2>&1';
			case 'curl':
				return $schedule . ' curl -s ' . self::shellQuote( $url ) . ' >/dev/null 2>&1';
			case 'wpcli':
			default:
				return $schedule . ' cd ' . self::shellQuote( untrailingslashit( $path ) ) . ' && wp cron event run --due-now --quiet';
		}
	}

	/**
	 * Single-quote a value for a POSIX shell. `escapeshellarg()` does the same
	 * but is disabled on some shared hosts, and the line runs on the server's
	 * shell, not this PHP's.
	 *
	 * @param string $value Raw value.
	 */
	public static function shellQuote( string $value ): string {
		return "'" . str_replace( "'", "'\\''", $value ) . "'";
	}

	/**
	 * A timestamp as "in 5 mins (date time)" or "5 mins ago (date time)".
	 *
	 * @param int $timestamp UTC unix timestamp.
	 * @param int $now       Current UTC unix timestamp.
	 */
	private static function moment( int $timestamp, int $now ): string {
		$absolute = (string) wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
		$relative = human_time_diff( $timestamp, $now );
		if ( $timestamp > $now ) {
			/* translators: 1: human-readable time difference, 2: date and time */
			return sprintf( __( 'in %1$s (%2$s)', 'total-mail-queue' ), $relative, $absolute );
		}
		/* translators: 1: human-readable time difference, 2: date and time */
		return sprintf( __( '%1$s ago (%2$s)', 'total-mail-queue' ), $relative, $absolute );
	}

	/**
	 * One label/value row of a card table.
	 *
	 * @param string $label Row label, unescaped.
	 * @param string $html  Value, already escaped.
	 */
	private static function row( string $label, string $html ): void {
		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . $html . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every caller passes escaped markup.
	}
}
