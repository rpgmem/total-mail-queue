<?php
/**
 * Stage and tear down attachment files for queued emails.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Queue;

use TotalMailQueue\Support\Paths;

/**
 * Owns the on-disk staging area under `wp-content/uploads/tmq-attachments/`.
 *
 * When `wp_mail()` is called with attachments, the original file paths may
 * be temp uploads that disappear before the cron sends the queued mail.
 * {@see AttachmentStore::stage()} copies each attachment into a per-email
 * subfolder and returns the new paths; the cron deletes the subfolder
 * after the send via {@see AttachmentStore::releaseFor()}.
 */
final class AttachmentStore {

	/**
	 * Copy attachments into a fresh subfolder under the staging directory and
	 * return their new paths. Returns null when the subfolder couldn't be
	 * created (caller should record an error on the queued row).
	 *
	 * @param string|list<string> $attachments Paths from the original wp_mail() call.
	 * @return list<string>|null New paths inside the staging directory, or null on failure.
	 */
	public static function stage( $attachments ): ?array {
		$base = Paths::attachmentsDir();
		self::ensureBaseDirIsProtected( $base );

		$subfolder = time() . '-' . wp_generate_password( 12, false );
		$created   = wp_mkdir_p( $base . $subfolder );
		if ( ! $created ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- intentional diagnostic for server logs
			error_log( 'Total Mail Queue: Could not create subfolder for email attachment' );
			return null;
		}

		if ( ! is_array( $attachments ) ) {
			$attachments = array( $attachments );
		}

		$fs     = self::filesystem();
		$staged = array();
		foreach ( $attachments as $item ) {
			$dest = $base . $subfolder . '/' . basename( $item );
			$fs->copy( $item, $dest );
			$staged[] = $dest;
		}
		return $staged;
	}

	/**
	 * Delete the subfolder that contains the given attachment paths.
	 *
	 * Called by the cron after a queued email has been sent (or failed
	 * permanently). Safe to call when $attachments is empty or not an array.
	 *
	 * @param mixed $attachments Whatever shape was decoded from the queued row.
	 */
	public static function releaseFor( $attachments ): void {
		if ( ! is_array( $attachments ) || empty( $attachments ) ) {
			return;
		}
		$first  = $attachments[0];
		$folder = pathinfo( $first );
		if ( ! isset( $folder['dirname'] ) ) {
			return;
		}
		self::filesystem()->delete( $folder['dirname'], true, 'd' );
	}

	/**
	 * Make sure the staging directory exists and contains the .htaccess /
	 * index.php guards that prevent web access. Idempotent.
	 *
	 * @param string $base Absolute path to the staging directory.
	 */
	private static function ensureBaseDirIsProtected( string $base ): void {
		if ( file_exists( $base . '.htaccess' ) ) {
			return;
		}
		wp_mkdir_p( $base );
		$fs = self::filesystem();
		$fs->put_contents( $base . '.htaccess', "Deny from all\n" );
		$fs->put_contents( $base . 'index.php', "<?php // Silence is golden.\n" );
	}

	/**
	 * Return the WP_Filesystem singleton, lazy-initialising it once per request.
	 *
	 * @return \WP_Filesystem_Base
	 */
	private static function filesystem() {
		global $wp_filesystem;
		if ( ! is_a( $wp_filesystem, 'WP_Filesystem_Base' ) ) {
			include_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}
		return $wp_filesystem;
	}
}
