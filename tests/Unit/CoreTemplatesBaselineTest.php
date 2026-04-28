<?php

declare(strict_types=1);

namespace TMQ\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TotalMailQueue\Sources\CoreTemplates;

/**
 * Sanity guard for the WordPress-core template baseline hardcoded in
 * {@see \TotalMailQueue\Sources\CoreTemplates}. The bodies there mirror
 * what WP itself ships on the version we authored against (6.9.4).
 * When WP rewrites a template in a future major release, the hardcoded
 * "WP default" surfaced in the Sources edit form drifts from reality.
 *
 * This test catches drift in two ways:
 *
 *   1. **Always:** every shipped template must still contain its
 *      historically-stable phrases. Phrases that survived 5+ years of
 *      WP releases are unlikely to be rewritten lightly.
 *
 *   2. **When `vendor/wordpress/` is present** (functional CI / local
 *      dev with `bin/install-wp-tests.sh` already run): the same
 *      phrases must also still appear in the live WP source files.
 *      If WP changed the wording, this fails and a maintainer updates
 *      the hardcoded baseline + the phrase here.
 *
 * @covers \TotalMailQueue\Sources\CoreTemplates
 */
final class CoreTemplatesBaselineTest extends TestCase {

	/**
	 * Phrases that have shipped unchanged in WP core for years and serve
	 * as a sanity check for both our hardcoded copy and (when available)
	 * the live WP source under vendor/wordpress.
	 *
	 * Each entry: [source_key => [WP file (relative to wp root), phrase]]
	 *
	 * @return array<string,array{0:string,1:string}>
	 */
	private function expectations(): array {
		return array(
			'wp_core:password_reset'               => array( 'wp-includes/user.php', 'Someone has requested a password reset' ),
			'wp_core:new_user'                     => array( 'wp-includes/pluggable.php', 'To set your password, visit the following address' ),
			'wp_core:new_user_admin'               => array( 'wp-includes/pluggable.php', 'New user registration on your site' ),
			'wp_core:password_change'              => array( 'wp-includes/user.php', 'This notice confirms that your password was changed' ),
			'wp_core:password_change_admin_notify' => array( 'wp-includes/pluggable.php', 'Password changed for user' ),
			'wp_core:email_change'                 => array( 'wp-includes/user.php', 'This notice confirms that your email address' ),
			'wp_core:admin_email_change_confirm'   => array( 'wp-admin/includes/misc.php', 'A site administrator' ),
			'wp_core:user_action_confirm'          => array( 'wp-includes/user.php', 'A request has been made to perform the following action' ),
			'wp_core:privacy_export_ready'         => array( 'wp-admin/includes/privacy-tools.php', 'personal data' ),
			'wp_core:privacy_erasure_done'         => array( 'wp-includes/user.php', 'Your request to erase your personal data' ),
			'wp_core:recovery_mode'                => array( 'wp-includes/class-wp-recovery-mode-email-service.php', 'WordPress has a built-in feature' ),
		);
	}

	public function test_every_template_in_our_baseline_carries_its_canonical_phrase(): void {
		$missing = array();
		foreach ( $this->expectations() as $key => $spec ) {
			$tpl = CoreTemplates::get( $key );
			self::assertNotNull( $tpl, "CoreTemplates::all() must include $key" );

			$body   = (string) $tpl['body'];
			$phrase = $spec[1];
			if ( false === strpos( $body, $phrase ) ) {
				$missing[] = "$key: body lost the canonical phrase \"$phrase\"";
			}
		}
		self::assertSame(
			array(),
			$missing,
			"The hardcoded WP-baseline body for one or more wp_core templates no longer carries its historically-stable phrase. If WP rewrote the template, update CoreTemplates AND this test in lockstep:\n  " . implode( "\n  ", $missing )
		);
	}

	public function test_live_wp_source_still_carries_each_canonical_phrase(): void {
		$wp_root = dirname( __DIR__, 2 ) . '/vendor/wordpress';
		if ( ! is_dir( $wp_root ) ) {
			self::markTestSkipped( 'vendor/wordpress not installed; run bin/install-wp-tests.sh first.' );
		}

		$drifted = array();
		foreach ( $this->expectations() as $key => $spec ) {
			$rel  = $spec[0];
			$path = $wp_root . '/' . $rel;
			if ( ! is_file( $path ) ) {
				$drifted[] = "$key: WP file moved or renamed: expected at $rel";
				continue;
			}
			$source = (string) file_get_contents( $path );
			$phrase = $spec[1];
			if ( false === strpos( $source, $phrase ) ) {
				$drifted[] = "$key: WP rewrote the template — phrase \"$phrase\" no longer found in $rel";
			}
		}
		self::assertSame(
			array(),
			$drifted,
			"WordPress core has changed an email template our baseline tracks. Update CoreTemplates + this test:\n  " . implode( "\n  ", $drifted )
		);
	}
}
