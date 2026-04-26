<?php
/**
 * Pre-seeded catalog entries for popular plugins + plugin-internal sources.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Sources;

use TotalMailQueue\Cron\AlertSender;

/**
 * Seeds the sources catalog with a small set of known entries on
 * activation and on every version bump.
 *
 * Pre-seeding is purely additive — every entry would eventually be
 * inserted by the auto-detection in {@see \TotalMailQueue\Queue\MailInterceptor}
 * the first time the source actually sends. The benefit is that admins
 * can preview the toggles in the Sources tab BEFORE the first email of
 * each kind arrives (especially useful for high-impact integrations like
 * WooCommerce that you might want to silence ahead of time).
 *
 * `Sources\Repository::register()` is idempotent (UNIQUE on source_key),
 * so calling {@see seed()} multiple times is safe — repeated calls are
 * effectively no-ops.
 */
final class KnownSources {

	/**
	 * The catalog rows to seed. Each tuple is `[source_key, label, group]`.
	 *
	 * @return array<int,array{0:string,1:string,2:string}>
	 */
	public static function entries(): array {
		return array(
			// Plugin-internal source — appears in the catalog from day one
			// so the admin sees it (rendered as a non-toggleable system row
			// in the Sources tab).
			array( AlertSender::SOURCE_KEY, 'Total Mail Queue alert', 'Total Mail Queue' ),

			// Popular third-party plugins. Backtrace detection would also
			// produce these keys the first time each plugin sends, but
			// pre-seeding lets the admin disable them up front.
			array( 'plugin:woocommerce', 'WooCommerce', 'Plugins' ),
			array( 'plugin:contact-form-7', 'Contact Form 7', 'Plugins' ),
			array( 'plugin:wpforms', 'WPForms', 'Plugins' ),
			array( 'plugin:wpforms-lite', 'WPForms Lite', 'Plugins' ),
		);
	}

	/**
	 * Insert the seed entries into the catalog. Idempotent: existing rows
	 * are left untouched (keeps any admin-toggled `enabled` value).
	 */
	public static function seed(): void {
		foreach ( self::entries() as $entry ) {
			Repository::register( $entry[0], $entry[1], $entry[2] );
		}
	}
}
