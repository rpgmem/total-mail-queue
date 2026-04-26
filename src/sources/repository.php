<?php
/**
 * Repository for the registered message-source catalog.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Sources;

use TotalMailQueue\Database\Schema;

/**
 * Read/write access to the `{$prefix}total_mail_queue_sources` table.
 *
 * The catalog is **opt-out**: every freshly detected `source_key` lands here
 * with `enabled = 1` so existing sites keep delivering every email after the
 * upgrade. The admin can later toggle individual rows off in the "Sources"
 * tab; queued messages whose source resolves to a disabled row will be stored
 * with the `blocked_by_source` status (introduced in S4) instead of being
 * scheduled for sending.
 *
 * Methods here only deal with the catalog itself — detection, auto-registration
 * triggered from `pre_wp_mail`, and the enforcement check live in classes
 * introduced by the later S2 / S4 phases.
 */
final class Repository {

	/**
	 * Prefix used for "system" sources that the admin must never be allowed
	 * to disable (currently only `total_mail_queue:alert`).
	 *
	 * `setEnabled()` and `setEnabledByGroup()` short-circuit when the key
	 * starts with this prefix; `isSystem()` is the public predicate used by
	 * the admin UI to render a non-toggleable badge.
	 */
	public const SYSTEM_PREFIX = 'total_mail_queue:';

	/**
	 * Whether the given `source_key` is a system source (always-enabled,
	 * un-toggleable from the admin UI).
	 *
	 * @param string $source_key Canonical key.
	 */
	public static function isSystem( string $source_key ): bool {
		return 0 === strpos( $source_key, self::SYSTEM_PREFIX );
	}

	/**
	 * Look up a single row by its `source_key`.
	 *
	 * @param string $source_key Canonical key, e.g. `wp_core:password_reset`.
	 * @return array<string,mixed>|null Row as an associative array, or null when missing.
	 */
	public static function findByKey( string $source_key ): ?array {
		global $wpdb;
		$table = Schema::sourcesTable();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `$table` WHERE `source_key` = %s", $source_key ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Find a row by id.
	 *
	 * @param int $id Row id.
	 * @return array<string,mixed>|null
	 */
	public static function findById( int $id ): ?array {
		global $wpdb;
		$table = Schema::sourcesTable();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `$table` WHERE `id` = %d", $id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Insert a new source row, or no-op when one with the same `source_key`
	 * already exists. Returns the row id either way.
	 *
	 * Auto-registration: callers feed a key they detected on-the-fly without
	 * checking first; the UNIQUE index on `source_key` prevents duplicates and
	 * we resolve the existing id when the insert is rejected.
	 *
	 * @param string $source_key  Canonical key, e.g. `wp_core:password_reset`.
	 * @param string $label       Human-readable label shown in the admin.
	 * @param string $group_label Group label used to fold rows in the admin UI.
	 * @return int Row id (existing or newly inserted), 0 on insert failure.
	 */
	public static function register( string $source_key, string $label, string $group_label ): int {
		$existing = self::findByKey( $source_key );
		if ( null !== $existing ) {
			return (int) $existing['id'];
		}

		global $wpdb;
		$table = Schema::sourcesTable();
		$now   = current_time( 'mysql', false );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$inserted = $wpdb->insert(
			$table,
			array(
				'source_key'   => $source_key,
				'label'        => $label,
				'group_label'  => $group_label,
				'enabled'      => 1,
				'detected_at'  => $now,
				'last_seen_at' => $now,
				'total_count'  => 0,
			)
		);
		if ( false === $inserted ) {
			// Race: another request may have inserted the same key first.
			$row = self::findByKey( $source_key );
			return null !== $row ? (int) $row['id'] : 0;
		}
		return (int) $wpdb->insert_id;
	}

	/**
	 * Bump the `total_count` and update `last_seen_at` for a source.
	 *
	 * @param int $id Row id (typically the value returned by {@see register()}).
	 */
	public static function markSeen( int $id ): void {
		if ( $id <= 0 ) {
			return;
		}
		global $wpdb;
		$table = Schema::sourcesTable();
		$now   = current_time( 'mysql', false );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE `$table` SET `total_count` = `total_count` + 1, `last_seen_at` = %s WHERE `id` = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$now,
				$id
			)
		);
	}

	/**
	 * Whether the source corresponding to `$source_key` is currently allowed
	 * to deliver. Unknown keys are treated as **enabled** so the first time we
	 * see a source it doesn't get silently blocked before the admin has a
	 * chance to inspect it.
	 *
	 * @param string $source_key Canonical key.
	 * @return bool
	 */
	public static function isEnabled( string $source_key ): bool {
		if ( '' === $source_key ) {
			return true;
		}
		$row = self::findByKey( $source_key );
		if ( null === $row ) {
			return true;
		}
		return 1 === (int) $row['enabled'];
	}

	/**
	 * Toggle the `enabled` flag for a single source. System sources
	 * (see {@see isSystem()}) silently refuse to flip — they're hardcoded
	 * to always-enabled so the admin can't accidentally silence the
	 * plugin's own monitoring email.
	 *
	 * @param int  $id      Row id.
	 * @param bool $enabled New value.
	 */
	public static function setEnabled( int $id, bool $enabled ): void {
		if ( $id <= 0 ) {
			return;
		}
		$row = self::findById( $id );
		if ( null !== $row && self::isSystem( (string) $row['source_key'] ) ) {
			return;
		}
		global $wpdb;
		$table = Schema::sourcesTable();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$table,
			array( 'enabled' => $enabled ? 1 : 0 ),
			array( 'id' => $id ),
			array( '%d' ),
			array( '%d' )
		);
	}

	/**
	 * Toggle every source that belongs to a given `group_label` (e.g. enable
	 * or disable "WooCommerce" en masse). System sources inside the group
	 * are excluded from the update.
	 *
	 * @param string $group_label Group label as stored on the row.
	 * @param bool   $enabled     New value applied to every member of the group.
	 * @return int Number of rows updated.
	 */
	public static function setEnabledByGroup( string $group_label, bool $enabled ): int {
		global $wpdb;
		$table = Schema::sourcesTable();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE `$table` SET `enabled` = %d WHERE `group_label` = %s AND `source_key` NOT LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$enabled ? 1 : 0,
				$group_label,
				$wpdb->esc_like( self::SYSTEM_PREFIX ) . '%'
			)
		);
		return (int) $updated;
	}

	/**
	 * List every source row, ordered by group label then key.
	 *
	 * @return list<array<string,mixed>>
	 */
	public static function all(): array {
		global $wpdb;
		$table = Schema::sourcesTable();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( "SELECT * FROM `$table` ORDER BY `group_label` ASC, `source_key` ASC", ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Total number of registered sources.
	 */
	public static function count(): int {
		global $wpdb;
		$table = Schema::sourcesTable();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$table`" );
	}
}
