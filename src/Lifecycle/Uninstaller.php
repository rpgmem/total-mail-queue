<?php
/**
 * Plugin uninstall handler.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Lifecycle;

use TotalMailQueue\Database\Migrator;
use TotalMailQueue\Database\Schema;
use TotalMailQueue\Support\Paths;

/**
 * Callback invoked by WordPress when the plugin is deleted via the admin UI.
 *
 * Runs in a special context: WordPress loads the plugin file but only to
 * resolve the hook target — none of the plugin's normal init runs. The
 * handler must therefore be self-contained and rely only on the Composer
 * autoloader (which the bootstrap requires before {@see \TotalMailQueue\Plugin::boot}).
 */
final class Uninstaller {

	/**
	 * Tear down everything the plugin persisted.
	 *
	 * @param string $main_file Absolute path to the plugin's main entry file
	 *                          (used to locate the legacy attachments folder).
	 */
	public static function uninstall( string $main_file ): void {
		// Options.
		delete_option( 'wp_tmq_settings' );
		delete_option( Migrator::VERSION_OPTION );
		delete_option( 'wp_tmq_last_cron' );

		// Tables.
		Schema::drop();

		// Attachments directories — current location in uploads + legacy in plugin dir.
		global $wp_filesystem;
		if ( ! is_a( $wp_filesystem, 'WP_Filesystem_Base' ) ) {
			include_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}
		$new_path = Paths::attachmentsDir();
		if ( is_dir( $new_path ) ) {
			$wp_filesystem->delete( $new_path, true, 'd' );
		}
		$legacy_path = Paths::legacyAttachmentsDir( $main_file );
		if ( is_dir( $legacy_path ) ) {
			$wp_filesystem->delete( $legacy_path, true, 'd' );
		}
	}
}
