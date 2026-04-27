<?php
/**
 * Cron worker that drains the queue.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Cron;

use TotalMailQueue\Queue\AttachmentStore;
use TotalMailQueue\Queue\QueueRepository;
use TotalMailQueue\Queue\Tracker;
use TotalMailQueue\Retention\LogPruner;
use TotalMailQueue\Settings\Options;
use TotalMailQueue\Smtp\Configurator;
use TotalMailQueue\Smtp\Repository as SmtpRepository;
use TotalMailQueue\Support\Encryption;
use TotalMailQueue\Support\Serializer;

/**
 * Drains the queue and sends pending emails.
 *
 * Hooked onto {@see Scheduler::HOOK} by the scheduler. Runs once per cron
 * tick, processes up to `queue_amount` rows, applies retention sweeps at
 * the end, and persists a {@see Diagnostics} snapshot.
 *
 * Most of the orchestration is read top-to-bottom in {@see BatchProcessor::run()};
 * the per-row loop is delegated to {@see BatchProcessor::sendOne()} for
 * readability.
 */
final class BatchProcessor {

	/**
	 * Counts how many times the cron hook fires within a single PHP request.
	 * Multiple add_actions for the same hook would otherwise drain the queue
	 * twice — so we early-out from the second invocation onwards.
	 *
	 * @var int
	 */
	private static int $invocation_count = 0;

	/**
	 * True while {@see BatchProcessor::run()} is iterating the queue.
	 * Read by {@see \TotalMailQueue\Queue\MailInterceptor::handle()} so it
	 * doesn't re-queue messages we are already sending — mirrors the
	 * `pre_wp_mail` filter teardown that {@see dispatchOnce()} performs,
	 * but covers paths that call `wp_mail()` outside dispatchOnce too
	 * (e.g. {@see AlertSender::maybeAlert()}).
	 *
	 * @var bool
	 */
	private static bool $is_draining = false;

	/**
	 * Reset the in-process re-entrancy guard. Tests call this between cases;
	 * production code never does.
	 *
	 * @internal
	 */
	public static function reset(): void {
		self::$invocation_count = 0;
		self::$is_draining      = false;
	}

	/**
	 * Whether the cron drainer is currently sending queued rows.
	 *
	 * Used by {@see \TotalMailQueue\Queue\MailInterceptor} to short-circuit
	 * its own filter so the drainer's `wp_mail()` calls are not re-queued.
	 */
	public static function isDraining(): bool {
		return self::$is_draining;
	}

	/**
	 * Cron entry point. Idempotent re-entrancy guard at the top.
	 */
	public static function run(): void {
		$diag = new Diagnostics( array( 'time' => current_time( 'mysql', false ) ) );

		$options = Options::get();
		if ( '1' !== $options['enabled'] ) {
			$diag->set( 'result', 'skipped: plugin not in queue mode (enabled=' . $options['enabled'] . ')' );
			$diag->save();
			return;
		}

		++self::$invocation_count;
		if ( self::$invocation_count > 1 ) {
			$diag->set( 'result', 'skipped: duplicate trigger' );
			$diag->save();
			return;
		}

		if ( ! CronLock::acquire( (int) $options['cron_lock_ttl'] ) ) {
			$diag->set( 'result', 'skipped: another batch is still running' );
			$diag->save();
			return;
		}

		// Mark the drain in progress so MailInterceptor knows to leave our
		// own wp_mail() calls alone. Wrapped in try / finally so a fatal
		// (or `wp_die()`) inside the loop doesn't strand the flag at true.
		self::$is_draining = true;
		try {
			$pending_total = QueueRepository::pendingCount();
			$pending_ids   = QueueRepository::pendingIds( (int) $options['queue_amount'] );
			$batch_size    = count( $pending_ids );

			AlertSender::maybeAlert( $pending_total, $batch_size );

			// Reset SMTP counters once per cron run, then snapshot the available accounts.
			SmtpRepository::resetCounters();
			$smtp_accounts = SmtpRepository::available();

			$diag->set( 'queue_total', $pending_total );
			$diag->set( 'queue_batch', $batch_size );
			$diag->set( 'smtp_accounts', count( $smtp_accounts ) );
			$diag->set( 'send_method', $options['send_method'] );
			$diag->set( 'sent', 0 );
			$diag->set( 'errors', 0 );

			foreach ( $pending_ids as $mail_id ) {
				$loop_result = self::sendOne( $mail_id, $options, $smtp_accounts, $diag );
				if ( 'smtp_unavailable' === $loop_result ) {
					$diag->set( 'result', 'smtp_unavailable' );
					break;
				}
			}

			LogPruner::pruneByAge();
			LogPruner::pruneByCount();
		} finally {
			self::$is_draining = false;
			CronLock::release();
		}

		if ( ! $diag->has( 'result' ) ) {
			$diag->set( 'result', 'ok' );
		}
		$diag->save();
	}

	/**
	 * Send one queued email. Returns 'smtp_unavailable' to signal the caller
	 * to abort the rest of the batch (smtp-only mode without an account),
	 * 'sent' on success, 'failed' on failure, or 'skipped' when the row
	 * disappeared between the SELECT and now.
	 *
	 * @param int                       $mail_id       Row id.
	 * @param array<string,mixed>       $options       Plugin options snapshot.
	 * @param list<array<string,mixed>> $smtp_accounts Snapshot of available SMTP accounts (modified in place by `bumpMemoryCounter` on success).
	 * @param Diagnostics               $diag          Diagnostics accumulator.
	 * @return string Loop control token.
	 */
	private static function sendOne( int $mail_id, array $options, array &$smtp_accounts, Diagnostics $diag ): string {
		$item = QueueRepository::findById( $mail_id );
		if ( null === $item ) {
			return 'skipped';
		}

		$to      = ! empty( $item['recipient'] ) ? Serializer::decode( $item['recipient'] ) : $options['email'];
		$subject = $item['subject'];
		if ( empty( $item['recipient'] ) ) {
			$subject = __( 'ERROR', 'total-mail-queue' ) . ' // ' . $subject;
		}
		$headers     = ! empty( $item['headers'] ) ? Serializer::decode( $item['headers'] ) : '';
		$attachments = ! empty( $item['attachments'] ) ? Serializer::decode( $item['attachments'] ) : '';

		Tracker::set( $mail_id );

		// Strip any X-TMQ-PHPMailer-Config header and decode it.
		$captured = self::extractCapturedConfig( $headers );

		$send_method = $options['send_method'];
		$smtp_to_use = null;
		if ( 'php' !== $send_method && ! empty( $smtp_accounts ) ) {
			$smtp_to_use = SmtpRepository::pickAvailable( $smtp_accounts );
		}

		if ( 'smtp' === $send_method && ! $smtp_to_use ) {
			QueueRepository::update(
				$mail_id,
				array( 'info' => __( 'Waiting: no SMTP account available (check if accounts are enabled and limits are not exceeded).', 'total-mail-queue' ) ),
				array( '%s' )
			);
			return 'smtp_unavailable';
		}

		$phpmailer_hook = self::installPhpMailerHook( $smtp_to_use, $captured, $send_method );

		$send_status = self::dispatchOnce( $to, $subject, $item['message'], $headers, $attachments );

		if ( null !== $phpmailer_hook ) {
			remove_action( 'phpmailer_init', $phpmailer_hook, 999999 );
		}

		if ( $send_status ) {
			$sent_smtp_id = $smtp_to_use ? (int) $smtp_to_use['id'] : 0;
			QueueRepository::update(
				$mail_id,
				array(
					'timestamp'       => current_time( 'mysql', false ),
					'status'          => 'sent',
					'info'            => '',
					'smtp_account_id' => $sent_smtp_id,
				),
				array( '%s', '%s', '%s', '%d' )
			);
			if ( $smtp_to_use ) {
				SmtpRepository::incrementCounter( $smtp_to_use['id'] );
				SmtpRepository::bumpMemoryCounter( $smtp_accounts, $smtp_to_use['id'] );
			}
			$diag->increment( 'sent' );
		} else {
			$diag->increment( 'errors' );
			// If wp_mail_failed didn't already write an info, leave a fallback.
			if ( '' === QueueRepository::infoFor( $mail_id ) ) {
				QueueRepository::update(
					$mail_id,
					array( 'info' => __( 'wp_mail() returned false. Check for conflicting email plugins or server mail configuration.', 'total-mail-queue' ) ),
					array( '%s' )
				);
			}
		}

		AttachmentStore::releaseFor( $attachments );
		return $send_status ? 'sent' : 'failed';
	}

	/**
	 * Find and remove the X-TMQ-PHPMailer-Config header, returning its
	 * decoded payload (or null when absent).
	 *
	 * @param mixed $headers Reference; modified in place when a match is found.
	 * @return array<string,mixed>|null
	 */
	private static function extractCapturedConfig( &$headers ): ?array {
		if ( ! is_array( $headers ) ) {
			return null;
		}
		foreach ( $headers as $idx => $hval ) {
			if ( preg_match( '/^X-TMQ-PHPMailer-Config: (.+)$/i', $hval, $matches ) ) {
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- reading the encoded payload our own MailInterceptor wrote
				$decoded = Serializer::decode( base64_decode( trim( $matches[1] ) ) );
				array_splice( $headers, $idx, 1 );
				return is_array( $decoded ) ? $decoded : null;
			}
		}
		return null;
	}

	/**
	 * Build the temporary `phpmailer_init` callback that configures the
	 * outgoing PHPMailer for this row. Returns the callable so the caller
	 * can remove it after wp_mail() returns.
	 *
	 * @param array<string,mixed>|null $smtp_to_use Account chosen by pickAvailable, or null.
	 * @param array<string,mixed>|null $captured    Replayed third-party config, or null.
	 * @param string                   $send_method `auto` / `smtp` / `php`.
	 * @return callable|null
	 */
	private static function installPhpMailerHook( ?array $smtp_to_use, ?array $captured, string $send_method ) {
		if ( $smtp_to_use ) {
			$cb = static function ( $phpmailer ) use ( $smtp_to_use ): void {
				Configurator::apply( $phpmailer, $smtp_to_use );
			};
			add_action( 'phpmailer_init', $cb, 999999 );
			return $cb;
		}
		if ( 'auto' === $send_method && $captured ) {
			$cb = static function ( $phpmailer ) use ( $captured ): void {
				if ( method_exists( $phpmailer, 'smtpClose' ) ) {
					$phpmailer->smtpClose();
				}
				foreach ( $captured as $prop => $val ) {
					if ( ! property_exists( $phpmailer, $prop ) ) {
						continue;
					}
					if ( 'Password' === $prop ) {
						$val = Encryption::decrypt( $val );
					}
					$phpmailer->$prop = $val;
				}
				$timeout = (int) Options::get()['smtp_timeout'];
				if ( $timeout > 0 ) {
					$phpmailer->Timeout = $timeout;
				}
			};
			add_action( 'phpmailer_init', $cb, 999999 );
			return $cb;
		}
		return null;
	}

	/**
	 * Send via wp_mail() once, with all pre_wp_mail filters temporarily
	 * removed so neither the plugin's own interceptor nor any third-party
	 * blocker re-enters. Belt-and-suspenders: {@see $is_draining} also
	 * makes {@see \TotalMailQueue\Queue\MailInterceptor::handle()} bail
	 * immediately, but stripping the filter chain protects against other
	 * mail plugins that filter `pre_wp_mail` for routing/short-circuit.
	 *
	 * @param mixed               $to          Recipient(s).
	 * @param string              $subject     Subject.
	 * @param string              $message     Body.
	 * @param mixed               $headers     Headers as decoded from the queue row.
	 * @param string|list<string> $attachments Staged attachment paths.
	 */
	private static function dispatchOnce( $to, string $subject, string $message, $headers, $attachments ): bool {
		global $wp_filter;
		$saved = isset( $wp_filter['pre_wp_mail'] ) ? clone $wp_filter['pre_wp_mail'] : null;
		remove_all_filters( 'pre_wp_mail' );
		try {
			return (bool) wp_mail( $to, $subject, $message, $headers, $attachments );
		} finally {
			if ( $saved ) {
				$wp_filter['pre_wp_mail'] = $saved;
			}
		}
	}
}
