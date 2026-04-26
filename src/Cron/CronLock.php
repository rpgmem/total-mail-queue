<?php
/**
 * Cross-process MySQL advisory lock that protects the cron batch.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Cron;

/**
 * Wraps MySQL `GET_LOCK()` / `RELEASE_LOCK()`.
 *
 * Two cron processes can fire concurrently when WP-Cron is being kicked
 * by both the built-in scheduler and an external trigger (server cron,
 * uptime probe, etc.). MySQL advisory locks serialize them: the second
 * caller's `GET_LOCK(name, 0)` returns 0 immediately and the second
 * batch bails out without touching the queue.
 *
 * If PHP fatals mid-batch the MySQL connection drops and the lock is
 * released automatically — no stale-lock recovery needed.
 */
final class CronLock {

	/**
	 * Lock name. Mirrored across activate/deactivate/cron without going
	 * through a constant elsewhere so this class is self-contained.
	 */
	public const NAME = 'wp_tmq_cron_lock';

	/**
	 * Hard floor for the lock TTL (seconds). Enforced even if the user
	 * sets a shorter `cron_lock_ttl` to avoid the lock expiring before a
	 * realistic batch finishes.
	 */
	private const MIN_TTL = 30;

	/**
	 * Try to acquire the lock. Returns true on success, false when another
	 * process already holds it.
	 *
	 * @param int $ttl Configured lock TTL (`cron_lock_ttl`); clamped to MIN_TTL.
	 */
	public static function acquire( int $ttl ): bool {
		global $wpdb;
		// Clean up any leftover transient lock written by very old plugin
		// versions. Cheap and idempotent.
		delete_transient( self::NAME );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$got = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', self::NAME ) );
		if ( ! $got ) {
			return false;
		}

		$effective_ttl = max( $ttl, self::MIN_TTL );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( 'SET @tmq_lock_timeout = %d', $effective_ttl ) );

		// Belt-and-suspenders release in case PHP fatals before the explicit
		// release at the end of the batch.
		register_shutdown_function( array( self::class, 'release' ) );

		return true;
	}

	/**
	 * Release the lock. Safe to call even when not held (RELEASE_LOCK returns
	 * 0 in that case, which we ignore).
	 */
	public static function release(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', self::NAME ) );
	}
}
