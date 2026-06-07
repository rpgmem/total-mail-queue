<?php
/**
 * Cross-process queue lock with a real, self-expiring TTL.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Cron;

/**
 * Serializes the cron batch across processes using a timestamped option.
 *
 * Two cron processes can fire concurrently (the built-in scheduler plus an
 * external server cron / uptime probe). The previous implementation used a
 * MySQL `GET_LOCK()`, which is bound to the database connection: it releases
 * automatically when a process *dies*, but a process that merely *hangs*
 * mid-batch — e.g. blocked on a slow SMTP socket — keeps the connection, and
 * therefore the lock, indefinitely. The whole queue then freezes until that
 * process is killed.
 *
 * This version stores `<token>|<expires>` in an option instead, so the lock
 * carries a wall-clock deadline:
 *
 * - {@see acquire()} claims it only when no live (unexpired) holder exists,
 *   confirming the win with a cache-bypassing read so two simultaneous
 *   claimers can't both proceed.
 * - {@see keepalive()} is called once per drained row: it extends the
 *   deadline while the batch is making progress, and reports the lock lost
 *   when a newer batch has taken over. A hung send stops calling it, so the
 *   deadline lapses and the next cron run reclaims the lock — the queue
 *   recovers on its own after at most `cron_lock_ttl` seconds.
 * - {@see release()} clears it on clean finish (and via a shutdown hook on
 *   fatal), but only when the token still matches, so a process that already
 *   lost the lock never deletes the new holder's claim.
 */
final class CronLock {

	/**
	 * Option name holding the `<token>|<expires>` lock value.
	 */
	public const OPTION = 'wp_tmq_cron_lock';

	/**
	 * Hard floor for the lock TTL (seconds). Enforced even if the user sets a
	 * shorter `cron_lock_ttl`, so the lock can't expire before a realistic
	 * batch makes any progress.
	 */
	private const MIN_TTL = 30;

	/**
	 * Our claim token for the current process, or '' when we hold no lock.
	 *
	 * @var string
	 */
	private static string $token = '';

	/**
	 * Wall-clock deadline (unix) of our current claim — cached so the per-row
	 * {@see keepalive()} can short-circuit without a query while the window is
	 * still comfortably valid.
	 *
	 * @var int
	 */
	private static int $expires = 0;

	/**
	 * Try to acquire the lock. Returns true on success, false when another
	 * process holds a live (unexpired) claim or wins a simultaneous race.
	 *
	 * @param int $ttl Configured lock TTL (`cron_lock_ttl`); clamped to MIN_TTL.
	 */
	public static function acquire( int $ttl ): bool {
		$ttl = max( $ttl, self::MIN_TTL );
		$now = time();

		// Clean up a leftover transient lock written by very old versions.
		delete_transient( self::OPTION );

		$current = self::read();
		if ( null !== $current && $current['expires'] > $now ) {
			return false;
		}

		$token   = uniqid( 'tmq', true );
		$expires = $now + $ttl;
		update_option( self::OPTION, $token . '|' . $expires, false );

		// Confirm the claim with a cache-bypassing read: if a second process
		// wrote after us, its token is the one now stored and we back off.
		$confirmed = self::read();
		if ( null === $confirmed || $confirmed['token'] !== $token ) {
			return false;
		}

		self::$token   = $token;
		self::$expires = $expires;
		register_shutdown_function( array( self::class, 'release' ) );
		return true;
	}

	/**
	 * Per-row keepalive: extend our deadline while the batch is progressing,
	 * and report whether we still hold the lock.
	 *
	 * Returns false once a newer batch has taken the lock (because ours
	 * lapsed while a send hung) — the caller should stop draining so it can't
	 * double-send rows the new batch is now handling.
	 *
	 * @param int $ttl Configured lock TTL (`cron_lock_ttl`); clamped to MIN_TTL.
	 */
	public static function keepalive( int $ttl ): bool {
		if ( '' === self::$token ) {
			return false;
		}
		$ttl = max( $ttl, self::MIN_TTL );
		$now = time();

		// Still comfortably inside our window — nothing to do, no query.
		if ( $now < self::$expires - (int) ( $ttl / 2 ) ) {
			return true;
		}

		// Back half of the window (or past it): a matching token means no one
		// took over, so extend; a different/absent token means we lost it.
		$current = self::read();
		if ( null === $current || $current['token'] !== self::$token ) {
			self::$token   = '';
			self::$expires = 0;
			return false;
		}

		self::$expires = $now + $ttl;
		update_option( self::OPTION, self::$token . '|' . self::$expires, false );
		return true;
	}

	/**
	 * Release the lock — but only when the stored token is still ours, so a
	 * process that already lost the lock can't wipe the new holder's claim.
	 * Safe to call when not held.
	 */
	public static function release(): void {
		if ( '' === self::$token ) {
			return;
		}
		$current = self::read();
		if ( null !== $current && $current['token'] === self::$token ) {
			delete_option( self::OPTION );
		}
		self::$token   = '';
		self::$expires = 0;
	}

	/**
	 * Reset the in-process claim state. Tests call this between cases;
	 * production code never does.
	 *
	 * @internal
	 */
	public static function reset(): void {
		self::$token   = '';
		self::$expires = 0;
	}

	/**
	 * Read the lock value straight from the options table, bypassing the
	 * per-request options cache so cross-process visibility is immediate.
	 *
	 * @return array{token:string,expires:int}|null Parsed lock, or null when unset/malformed.
	 */
	private static function read(): ?array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$raw = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM $wpdb->options WHERE option_name = %s", self::OPTION ) );
		if ( ! is_string( $raw ) || false === strpos( $raw, '|' ) ) {
			return null;
		}
		list( $token, $expires ) = explode( '|', $raw, 2 );
		return array(
			'token'   => $token,
			'expires' => (int) $expires,
		);
	}
}
