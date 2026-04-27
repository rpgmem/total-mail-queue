<?php

declare(strict_types=1);

namespace TMQ\Tests\Functional;

use TotalMailQueue\Templates\Engine;

/**
 * T1 — HTML template engine: wraps wp_mail() bodies in a styled envelope,
 * forces text/html content type, supports a skip filter, and stays out of
 * the way in block mode and when the feature is toggled off.
 *
 * @covers \TotalMailQueue\Templates\Engine
 */
final class TemplateEngineTest extends FunctionalTestCase {

	protected function setUp(): void {
		parent::setUp();

		// Each test starts from a clean engine state.
		delete_option( Engine::OPTION_NAME );

		// Plugin::boot() ran during bootstrap and may have already wired
		// the engine's filters with the bootstrap settings. Strip them so
		// each test re-registers under its own scenario.
		remove_filter( 'wp_mail', array( Engine::class, 'apply' ), Engine::FILTER_PRIORITY );
		remove_filter( 'wp_mail_content_type', array( Engine::class, 'forceHtmlContentType' ), Engine::FILTER_PRIORITY );
		remove_filter( 'wp_mail_from', array( Engine::class, 'overrideFromEmail' ), Engine::FILTER_PRIORITY );
		remove_filter( 'wp_mail_from_name', array( Engine::class, 'overrideFromName' ), Engine::FILTER_PRIORITY );
	}

	public function test_engine_wraps_plain_text_in_html(): void {
		$args = $this->args( array( 'message' => 'Hello world from the test.' ) );

		$out = Engine::apply( $args );

		self::assertIsArray( $out );
		self::assertArrayHasKey( 'message', $out );
		self::assertStringContainsString( '<!DOCTYPE html>', $out['message'] );
		self::assertStringContainsString( '<html', $out['message'] );
		self::assertStringContainsString( '</html>', $out['message'] );
		self::assertStringContainsString( 'Hello world from the test.', $out['message'] );
	}

	public function test_engine_skips_when_message_is_full_html(): void {
		$body = "<!DOCTYPE html>\n<html><body><p>Already wrapped by Elementor or similar.</p></body></html>";
		$args = $this->args( array( 'message' => $body ) );

		$out = Engine::apply( $args );

		self::assertSame( $body, $out['message'], 'Messages that are already a full HTML document must pass through untouched.' );
	}

	public function test_engine_skips_when_toggle_disabled(): void {
		update_option( Engine::OPTION_NAME, array( 'enabled' => false ) );

		Engine::register();

		self::assertFalse(
			has_filter( 'wp_mail', array( Engine::class, 'apply' ) ),
			'Disabling the master toggle must keep wp_mail untouched.'
		);
	}

	public function test_engine_skips_in_block_mode(): void {
		$this->setPluginOptions( array( 'enabled' => '2' ) );

		Engine::register();

		self::assertFalse(
			has_filter( 'wp_mail', array( Engine::class, 'apply' ) ),
			'In block mode no mail leaves anyway — the engine must not register.'
		);
	}

	public function test_engine_substitutes_global_placeholders(): void {
		update_option(
			Engine::OPTION_NAME,
			array(
				'footer_text' => 'Sent by {site_title} to {recipient} on {year}.',
			)
		);

		$args = $this->args(
			array(
				'to'      => 'someone@example.test',
				'message' => 'Body',
			)
		);

		$out = Engine::apply( $args );

		self::assertStringContainsString( (string) get_bloginfo( 'name' ), $out['message'] );
		self::assertStringContainsString( 'someone@example.test', $out['message'] );
		self::assertStringContainsString( date_i18n( 'Y' ), $out['message'] );
		self::assertStringNotContainsString( '{site_title}', $out['message'], 'All known placeholders must be replaced.' );
		self::assertStringNotContainsString( '{recipient}', $out['message'] );
	}

	public function test_engine_registers_on_wp_mail_at_priority_100(): void {
		// Default options + non-block plugin mode → engine wires up.
		$this->setPluginOptions( array( 'enabled' => '0' ) );

		Engine::register();

		self::assertSame( 100, Engine::FILTER_PRIORITY, 'Filter priority is locked at 100.' );
		self::assertSame(
			Engine::FILTER_PRIORITY,
			has_filter( 'wp_mail', array( Engine::class, 'apply' ) ),
			'Engine must register on wp_mail at the configured priority.'
		);
		self::assertSame(
			Engine::FILTER_PRIORITY,
			has_filter( 'wp_mail_content_type', array( Engine::class, 'forceHtmlContentType' ) )
		);
	}

	public function test_force_html_content_type(): void {
		self::assertSame( 'text/html', Engine::forceHtmlContentType( 'text/plain' ) );
		self::assertSame( 'text/html', Engine::forceHtmlContentType( '' ) );
		self::assertSame( 'text/html', Engine::forceHtmlContentType( 'multipart/alternative' ) );
	}

	public function test_clean_reset_password_link_strips_brackets(): void {
		$msg = "Someone requested a reset.\nClick: <https://example.test/wp-login.php?action=rp&key=abc>\nThanks.";

		$out = Engine::cleanResetPasswordLink( $msg );

		self::assertStringNotContainsString( '<https://', $out, 'The angle brackets WP wraps reset URLs in must be stripped.' );
		self::assertStringContainsString( 'https://example.test/wp-login.php?action=rp&key=abc', $out );
		self::assertStringContainsString( '<a ', $out, 'make_clickable() must turn the bare URL into an anchor.' );
	}

	public function test_skip_filter_short_circuits(): void {
		add_filter( 'wp_tmq_template_skip', '__return_true' );

		$args = $this->args( array( 'message' => 'Untouched body' ) );
		$out  = Engine::apply( $args );

		self::assertSame( 'Untouched body', $out['message'], 'wp_tmq_template_skip=true must short-circuit the engine.' );

		remove_filter( 'wp_tmq_template_skip', '__return_true' );
	}

	/**
	 * Build a wp_mail() args array with sensible defaults that tests can
	 * override piecemeal.
	 *
	 * @param array<string,mixed> $overrides
	 * @return array<string,mixed>
	 */
	private function args( array $overrides = array() ): array {
		return array_merge(
			array(
				'to'          => 'user@example.test',
				'subject'     => 'Test subject',
				'message'     => 'Test body',
				'headers'     => '',
				'attachments' => array(),
			),
			$overrides
		);
	}
}
