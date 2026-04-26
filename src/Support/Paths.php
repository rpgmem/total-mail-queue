<?php
/**
 * Centralised filesystem paths used across the plugin.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Support;

/**
 * Single source of truth for plugin filesystem paths.
 *
 * Anything that reads or writes to the attachments directory, the legacy
 * pre-2.2.0 attachments directory, or any future plugin-managed path goes
 * through this class so the location is defined exactly once.
 */
final class Paths {

	/**
	 * Cached attachments directory (memoised so repeated calls don't re-hit wp_upload_dir()).
	 *
	 * @var string|null
	 */
	private static ?string $attachments_dir = null;

	/**
	 * Cached legacy attachments directory.
	 *
	 * @var string|null
	 */
	private static ?string $legacy_attachments_dir = null;

	/**
	 * Absolute path to the attachments directory inside wp-content/uploads.
	 *
	 * Created lazily by callers via {@see wp_mkdir_p()}; this method only
	 * computes and returns the path string.
	 */
	public static function attachmentsDir(): string {
		if ( null === self::$attachments_dir ) {
			$upload                = wp_upload_dir();
			self::$attachments_dir = trailingslashit( $upload['basedir'] ) . 'tmq-attachments/';
		}
		return self::$attachments_dir;
	}

	/**
	 * Absolute path to the legacy attachments directory used by plugin versions
	 * before 2.2.0. Only the uninstall routine needs to know about this.
	 *
	 * @param string $main_file Absolute path to the plugin's main entry file.
	 */
	public static function legacyAttachmentsDir( string $main_file ): string {
		if ( null === self::$legacy_attachments_dir ) {
			self::$legacy_attachments_dir = plugin_dir_path( $main_file ) . 'attachments/';
		}
		return self::$legacy_attachments_dir;
	}

	/**
	 * Reset cached values. Tests call this between cases; production never does.
	 *
	 * @internal
	 */
	public static function reset(): void {
		self::$attachments_dir        = null;
		self::$legacy_attachments_dir = null;
	}
}
