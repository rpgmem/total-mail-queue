<?php
/**
 * WP-CLI commands.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Cli;

use TotalMailQueue\Admin\Pages\CronInfoPage;
use TotalMailQueue\Cron\BatchProcessor;
use TotalMailQueue\Cron\Diagnostics;
use TotalMailQueue\Cron\Scheduler;
use TotalMailQueue\Queue\QueueRepository;
use TotalMailQueue\Settings\Options;
use TotalMailQueue\Smtp\Repository as SmtpRepository;

/**
 * Reads and sends the email queue from the command line (#184).
 *
 * ## EXAMPLES
 *
 *     # The queue at a glance.
 *     $ wp mail-queue status
 *
 *     # Send one batch now, as the worker would.
 *     $ wp mail-queue send
 */
final class Command {

	/**
	 * Register the command, only under WP-CLI.
	 */
	public static function register(): void {
		if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( '\WP_CLI' ) ) {
			\WP_CLI::add_command( 'mail-queue', self::class );
		}
	}

	/**
	 * Show the queue's state.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : How to print it.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 * ---
	 *
	 * @param array<int,string>    $args       Positional arguments (none).
	 * @param array<string,string> $assoc_args Named arguments.
	 */
	public function status( $args, $assoc_args ): void {
		unset( $args );
		$data = self::statusData( time() );
		if ( 'json' === ( $assoc_args['format'] ?? 'table' ) ) {
			\WP_CLI::line( (string) wp_json_encode( $data ) );
			return;
		}
		$width = (int) max( 0, ...array_map( 'strlen', array_keys( $data ) ) );
		foreach ( $data as $key => $value ) {
			\WP_CLI::line( str_pad( $key, $width ) . '  ' . ( null === $value ? '-' : (string) $value ) );
		}
	}

	/**
	 * Send one batch of the queue now.
	 *
	 * Runs the same worker WP-Cron runs, with the same batch size, SMTP
	 * limits and lock, without waiting for the interval. Nothing is sent
	 * outside Queue mode, while another batch holds the lock or while no SMTP
	 * account has room; each ends with a warning that says which, and a batch
	 * with failed emails ends with one too. The exit code stays 0: a warning
	 * is a state of the queue, not a failure of the command.
	 *
	 * @param array<int,string>    $args       Positional arguments (none).
	 * @param array<string,string> $assoc_args Named arguments (none).
	 */
	public function send( $args, $assoc_args ): void {
		unset( $args, $assoc_args );
		$result = self::sendBatch();
		$line   = sprintf( 'Sent %d, failed %d, still waiting %d.', $result['sent'], $result['errors'], $result['pending'] );
		if ( '' !== $result['skipped'] ) {
			\WP_CLI::warning( 'Nothing sent: ' . $result['skipped'] . '.' );
			return;
		}
		if ( $result['errors'] > 0 ) {
			\WP_CLI::warning( $line . ' See the Log for why.' );
			return;
		}
		\WP_CLI::success( $line );
	}

	/**
	 * The values `status` prints, in order.
	 *
	 * @param int $now Unix time.
	 * @return array<string,int|string|null>
	 */
	public static function statusData( int $now ): array {
		$options = Options::get();
		$modes   = array(
			'0' => 'disabled',
			'1' => 'queue',
			'2' => 'block',
		);
		$pending = QueueRepository::pendingCount();
		$next    = wp_next_scheduled( Scheduler::HOOK );
		$next    = false === $next ? null : (int) $next;
		$last    = Diagnostics::lastSendTimestamp();
		return array(
			'mode'          => $modes[ (string) $options['enabled'] ] ?? (string) $options['enabled'],
			'send_method'   => (string) $options['send_method'],
			'pending'       => $pending,
			'batch_size'    => (int) $options['queue_amount'],
			'interval'      => (int) $options['queue_interval'],
			'worker'        => CronInfoPage::workerState( (string) $options['enabled'], $pending, $next, $now, (int) $options['queue_interval'] ),
			'next_run'      => null === $next ? null : gmdate( 'Y-m-d H:i:s', $next ) . ' UTC',
			'last_send'     => $last > 0 ? gmdate( 'Y-m-d H:i:s', $last ) . ' UTC' : null,
			'smtp_accounts' => count( SmtpRepository::all() ),
		);
	}

	/**
	 * Run one batch and read back what it did.
	 *
	 * @return array{sent: int, errors: int, pending: int, skipped: string}
	 */
	public static function sendBatch(): array {
		delete_option( Diagnostics::OPTION_NAME );
		BatchProcessor::run();
		$diag   = (array) get_option( Diagnostics::OPTION_NAME, array() );
		$result = (string) ( $diag['result'] ?? '' );
		$sent   = (int) ( $diag['sent'] ?? 0 );
		// The worker's own words for a batch that sent nothing on purpose:
		// not in Queue mode, the lock held, an alert, no SMTP account.
		$skipped = ( '' !== $result && 'ok' !== $result && 0 === $sent ) ? (string) preg_replace( '/^skipped: /', '', $result ) : '';
		return array(
			'sent'    => $sent,
			'errors'  => (int) ( $diag['errors'] ?? 0 ),
			'pending' => QueueRepository::pendingCount(),
			'skipped' => $skipped,
		);
	}
}
