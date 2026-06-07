<?php
/**
 * WP-Cron schedule registration for the queue worker.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Cron;

use TotalMailQueue\Queue\QueueRepository;
use TotalMailQueue\Settings\Options;
use TotalMailQueue\Smtp\Repository as SmtpRepository;

/**
 * Registers a custom cron schedule whose interval mirrors the
 * `queue_interval` setting, and (un)schedules the queue-processing event
 * based on whether the plugin is in queue mode.
 *
 * The worker runs on demand rather than on a fixed heartbeat: it stays
 * idle (no event) while the queue is empty, wakes immediately when a mail
 * is enqueued ({@see Scheduler::ensureScheduled()}), drains at the configured
 * interval while there is work an account can take, and — in SMTP-only mode
 * with every account capped — defers to the next cycle/quota window instead
 * of spinning ({@see Scheduler::adjustSchedule()}).
 */
final class Scheduler {

	/**
	 * The action that fires the queue worker — also the cron event name.
	 */
	public const HOOK = 'wp_tmq_mail_queue_hook';

	/**
	 * Custom schedule slug. Registered on `cron_schedules` filter.
	 */
	public const INTERVAL_SLUG = 'wp_tmq_interval';

	/**
	 * Hook everything: the `cron_schedules` filter and the conditional
	 * event (un)scheduling that depends on whether the plugin is enabled.
	 *
	 * Called from {@see \TotalMailQueue\Plugin::boot()}.
	 */
	public static function register(): void {
		add_filter( 'cron_schedules', array( self::class, 'addInterval' ) );
		add_action( self::HOOK, array( BatchProcessor::class, 'run' ) );

		// Re-arm the cycle whenever the plugin settings change. The custom
		// interval is read from the options inside addInterval(), so an edited
		// interval (or a flipped operation mode) only takes effect once the
		// event is rescheduled — without this the old cadence lingers until the
		// next manual mode toggle.
		add_action( 'update_option_' . Options::OPTION_NAME, array( self::class, 'reschedule' ) );
		add_action( 'add_option_' . Options::OPTION_NAME, array( self::class, 'reschedule' ) );

		// Schedule / unschedule based on the current plugin mode.
		// Run on init so options + cron table are loaded.
		self::syncSchedule();
	}

	/**
	 * `cron_schedules` filter — append the plugin's custom interval.
	 *
	 * Note: `cron_schedules` can fire as early as `plugins_loaded` (before
	 * the `init` action), so this callback must NOT call any i18n function.
	 * Doing so would trigger WP 6.7+'s `_load_textdomain_just_in_time`
	 * notice. The display string is the plugin's brand name and stays as
	 * a plain literal.
	 *
	 * @param array<string,array<string,mixed>> $schedules Existing schedules.
	 * @return array<string,array<string,mixed>>
	 */
	public static function addInterval( $schedules ) {
		$options                          = Options::get();
		$schedules[ self::INTERVAL_SLUG ] = array(
			'interval' => (int) $options['queue_interval'],
			'display'  => 'Total Mail Queue',
		);
		return $schedules;
	}

	/**
	 * Reconcile the schedule on boot:
	 * - Not in queue mode: the event must not exist (tear it down).
	 * - Queue mode with an event already armed (active or deferred): leave it.
	 * - Queue mode, no event, but rows are waiting: self-heal by arming an
	 *   immediate run (covers an event lost to a crash or a cleared cron).
	 * - Queue mode, no event, empty queue: stay idle — {@see Scheduler::ensureScheduled()}
	 *   wakes the worker when the next mail is enqueued.
	 */
	public static function syncSchedule(): void {
		$is_enabled = '1' === (string) Options::get()['enabled'];

		if ( ! $is_enabled ) {
			wp_clear_scheduled_hook( self::HOOK );
			return;
		}

		if ( wp_next_scheduled( self::HOOK ) ) {
			return;
		}

		// No event armed: only re-arm when there is actually work to do, so an
		// idle site doesn't fire the worker every interval for an empty queue.
		if ( QueueRepository::pendingCount() > 0 ) {
			wp_schedule_event( time(), self::INTERVAL_SLUG, self::HOOK );
		}
	}

	/**
	 * Wake the worker for a freshly enqueued mail: arm an immediate run when
	 * the plugin is in queue mode and nothing is scheduled yet. A no-op when
	 * an event already exists (active cadence or a pending deferral), so it
	 * never disturbs the running rhythm or pulls a quota-deferred run forward.
	 *
	 * Called by {@see \TotalMailQueue\Queue\MailInterceptor} right after a
	 * sendable row hits the queue.
	 */
	public static function ensureScheduled(): void {
		if ( '1' !== (string) Options::get()['enabled'] ) {
			return;
		}
		if ( wp_next_scheduled( self::HOOK ) ) {
			return;
		}
		wp_schedule_event( time(), self::INTERVAL_SLUG, self::HOOK );
	}

	/**
	 * Put the worker to sleep: drop every scheduled occurrence. Used when a
	 * drain leaves the queue empty — the worker is woken again by the next
	 * enqueue.
	 */
	public static function idle(): void {
		wp_clear_scheduled_hook( self::HOOK );
	}

	/**
	 * Defer the worker to a specific moment instead of the normal interval:
	 * clear the recurring cadence and arm a single run at $timestamp. Used in
	 * SMTP-only mode when every account is capped, so the worker sleeps until
	 * the next cycle/quota window rather than waking every interval.
	 *
	 * Guards against scheduling in the past (runs as soon as possible) and is
	 * a no-op outside queue mode.
	 *
	 * @param int $timestamp UTC unix timestamp to wake at.
	 */
	public static function deferUntil( int $timestamp ): void {
		if ( '1' !== (string) Options::get()['enabled'] ) {
			return;
		}
		wp_clear_scheduled_hook( self::HOOK );
		$timestamp = max( $timestamp, time() + MINUTE_IN_SECONDS );
		wp_schedule_single_event( $timestamp, self::HOOK );
	}

	/**
	 * Decide what the worker should do with its own schedule once a drain
	 * finishes. Pure so the policy is unit-testable; {@see Scheduler::adjustSchedule()}
	 * supplies the live inputs and applies the result.
	 *
	 * @param int      $pending             Rows still waiting in the queue.
	 * @param string   $send_method         `auto` / `smtp` / `php`.
	 * @param bool     $account_available   Whether an SMTP account can send right now.
	 * @param int|null $next_available_at   When an account next frees up, or null if none are enabled.
	 * @return string One of `idle`, `defer`, `keep`.
	 */
	public static function nextState( int $pending, string $send_method, bool $account_available, ?int $next_available_at ): string {
		if ( $pending <= 0 ) {
			return 'idle';
		}
		// auto/php can always fall back to the default transport, so account
		// availability never blocks the drain — keep the normal cadence.
		if ( 'smtp' !== $send_method ) {
			return 'keep';
		}
		if ( $account_available ) {
			return 'keep';
		}
		if ( null === $next_available_at ) {
			// SMTP-only with no enabled account: nothing to wait for. Sleep
			// until the admin enables one (which re-arms via reschedule()).
			return 'idle';
		}
		return 'defer';
	}

	/**
	 * Apply {@see Scheduler::nextState()} to the live queue / account state at
	 * the end of a drain. Called by {@see BatchProcessor::run()} while still in
	 * queue mode (the worker early-outs otherwise), so it only ever tightens
	 * or relaxes an already-running schedule.
	 *
	 * @param array<string,mixed> $options Plugin options snapshot.
	 */
	public static function adjustSchedule( array $options ): void {
		$pending     = QueueRepository::pendingCount();
		$send_method = (string) $options['send_method'];

		$account_available = false;
		$next_available_at = null;
		if ( $pending > 0 && 'smtp' === $send_method ) {
			$account_available = ! empty( SmtpRepository::available() );
			if ( ! $account_available ) {
				$next_available_at = SmtpRepository::nextAvailableAt( (int) $options['queue_interval'] );
			}
		}

		switch ( self::nextState( $pending, $send_method, $account_available, $next_available_at ) ) {
			case 'idle':
				self::idle();
				break;
			case 'defer':
				self::deferUntil( (int) $next_available_at );
				break;
			case 'keep':
			default:
				// Keep draining at the normal cadence. A recurring tick has
				// already re-armed its next occurrence (WP does this before
				// firing the hook), so this is a no-op there; but when the run
				// we're finishing was a one-off deferral wake-up, nothing is
				// armed yet — restore the recurring cadence so the remaining
				// rows keep flowing.
				if ( ! wp_next_scheduled( self::HOOK ) ) {
					wp_schedule_event( time() + (int) $options['queue_interval'], self::INTERVAL_SLUG, self::HOOK );
				}
				break;
		}
	}

	/**
	 * Drop any existing queue event and re-arm a fresh one due immediately
	 * (when the plugin is in queue mode). This is what manually flipping the
	 * operation mode to "block" and back used to do by side effect — callers
	 * invoke it directly after mutating SMTP accounts or settings so the very
	 * next request picks up the new account set / interval instead of waiting
	 * out the previously scheduled tick.
	 *
	 * Safe to call when not in queue mode: the stale event is cleared and no
	 * new one is armed, mirroring {@see Scheduler::syncSchedule()}.
	 */
	public static function reschedule(): void {
		$next_run = wp_next_scheduled( self::HOOK );
		if ( $next_run ) {
			wp_unschedule_event( $next_run, self::HOOK );
		}
		if ( '1' === (string) Options::get()['enabled'] ) {
			wp_schedule_event( time(), self::INTERVAL_SLUG, self::HOOK );
		}
	}
}
