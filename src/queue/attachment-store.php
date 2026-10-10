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
	 * return their new paths. Returns null when they could not all be staged
	 * (caller records an error on the row and queues it without attachments).
	 *
	 * All or nothing: a subset of the attachments is worse than none, so a
	 * failed copy removes what was already copied. Two files sharing a
	 * basename get a numeric suffix, because `copy()` refuses to overwrite and
	 * the second one would otherwise ship the first one's bytes.
	 *
	 * @param string|list<string> $attachments Paths from the original wp_mail() call.
	 * @return list<string>|null New paths inside the staging directory, or null on failure.
	 */
	public static function stage( $attachments ): ?array {
		// This runs inside whatever request sent the mail, typically a
		// visitor's form submit: an unavailable filesystem must degrade the
		// email, never fatal the page.
		$fs = self::filesystem();
		if ( null === $fs ) {
			self::log( 'WP_Filesystem unavailable, attachments not stored' );
			return null;
		}

		$base = Paths::attachmentsDir();
		self::ensureBaseDirIsProtected( $base, $fs );

		$subfolder = time() . '-' . wp_generate_password( 12, false );
		if ( ! wp_mkdir_p( $base . $subfolder ) ) {
			self::log( 'Could not create subfolder for email attachment' );
			return null;
		}

		if ( ! is_array( $attachments ) ) {
			$attachments = array( $attachments );
		}

		$staged = array();
		$used   = array();
		foreach ( $attachments as $item ) {
			$dest = $base . $subfolder . '/' . self::uniqueName( basename( (string) $item ), $used );
			if ( ! is_readable( (string) $item ) || ! $fs->copy( (string) $item, $dest ) ) {
				self::log( 'Could not copy attachment: ' . basename( (string) $item ) );
				$fs->delete( $base . $subfolder, true, 'd' );
				return null;
			}
			$staged[] = $dest;
		}
		return $staged;
	}

	/**
	 * Delete the subfolder that contains the given attachment paths.
	 *
	 * Called when a row reaches a final state: sent, failed for good, or
	 * deleted by an administrator. Never while a retry is pending, or the
	 * retry goes out without its files. Only a subfolder strictly inside the
	 * staging directory is ever removed, whatever path the row carries.
	 *
	 * @param mixed $attachments Whatever shape was decoded from the queued row.
	 */
	public static function releaseFor( $attachments ): void {
		if ( ! is_array( $attachments ) || empty( $attachments ) ) {
			return;
		}
		$base   = realpath( Paths::attachmentsDir() );
		$folder = realpath( dirname( (string) reset( $attachments ) ) );
		if ( false === $base || false === $folder || $folder === $base ) {
			return;
		}
		if ( 0 !== strpos( $folder . '/', trailingslashit( $base ) ) ) {
			return;
		}
		$fs = self::filesystem();
		if ( null !== $fs ) {
			$fs->delete( $folder, true, 'd' );
		}
	}

	/**
	 * A basename not used yet in this subfolder: `a.pdf`, then `a-2.pdf`, `a-3.pdf`…
	 *
	 * @param string             $name Original basename.
	 * @param array<string,bool> $used Names already taken — modified in place.
	 */
	private static function uniqueName( string $name, array &$used ): string {
		$candidate = $name;
		$ext       = pathinfo( $name, PATHINFO_EXTENSION );
		$stem      = pathinfo( $name, PATHINFO_FILENAME );
		for ( $n = 2; isset( $used[ $candidate ] ); $n++ ) {
			$candidate = $stem . '-' . $n . ( '' !== $ext ? '.' . $ext : '' );
		}
		$used[ $candidate ] = true;
		return $candidate;
	}

	/**
	 * Write a diagnostic to the server log.
	 *
	 * @param string $message Message, without the plugin prefix.
	 */
	private static function log( string $message ): void {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- intentional diagnostic for server logs
		error_log( 'Total Mail Queue: ' . $message );
	}

	/**
	 * Make sure the staging directory exists and contains the .htaccess /
	 * index.php guards that prevent web access. Idempotent.
	 *
	 * @param string              $base Absolute path to the staging directory.
	 * @param \WP_Filesystem_Base $fs   Filesystem to write the guards with.
	 */
	private static function ensureBaseDirIsProtected( string $base, $fs ): void {
		if ( file_exists( $base . '.htaccess' ) ) {
			return;
		}
		wp_mkdir_p( $base );
		$fs->put_contents( $base . '.htaccess', "Deny from all\n" );
		$fs->put_contents( $base . 'index.php', "<?php // Silence is golden.\n" );
	}

	/**
	 * Return the WP_Filesystem singleton, lazy-initialising it once per request.
	 *
	 * `WP_Filesystem()` returns false when no method resolves (an FTP method
	 * without credentials, for instance), leaving the global null.
	 *
	 * @return \WP_Filesystem_Base|null
	 */
	private static function filesystem() {
		global $wp_filesystem;
		if ( ! is_a( $wp_filesystem, 'WP_Filesystem_Base' ) ) {
			include_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}
		return is_a( $wp_filesystem, 'WP_Filesystem_Base' ) ? $wp_filesystem : null;
	}
}
