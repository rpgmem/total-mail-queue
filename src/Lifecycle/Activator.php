<?php
/**
 * Plugin activation handler.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Lifecycle;

use TotalMailQueue\Database\Migrator;

/**
 * Callback invoked by WordPress when the plugin is activated.
 */
final class Activator {

	/**
	 * Run install steps. Idempotent: re-activating an already-active install
	 * is a no-op against the schema and just re-stamps the version option.
	 */
	public static function activate(): void {
		Migrator::install();
	}
}
