<?php

declare(strict_types=1);

namespace TMQ\Tests\Functional;

use TotalMailQueue\Templates\Options;

/**
 * T2 — template options storage, defaults, and the sanitizer the admin UI
 * (T3) will lean on. Pure data-shape coverage; nothing here exercises the
 * wp_mail flow.
 *
 * @covers \TotalMailQueue\Templates\Options
 */
final class TemplateOptionsTest extends FunctionalTestCase {

	protected function setUp(): void {
		parent::setUp();
		Options::reset();
	}

	public function test_defaults_returned_when_option_empty(): void {
		$opts = Options::get();

		// Every locked-scope key is present.
		foreach (
			array(
				'enabled',
				'header_bg',
				'header_text_color',
				'header_alignment',
				'header_padding',
				'header_logo_url',
				'header_logo_max_width',
				'body_bg',
				'body_text_color',
				'body_link_color',
				'body_font_family',
				'body_font_size',
				'body_padding',
				'body_max_width',
				'footer_bg',
				'footer_text_color',
				'footer_text',
				'footer_powered_by',
				'wrapper_bg',
				'wrapper_border_radius',
				'wrapper_padding',
				'from_email',
				'from_name',
			) as $key
		) {
			self::assertArrayHasKey( $key, $opts, "Defaults must expose `$key`." );
		}

		self::assertTrue( $opts['enabled'], 'Templates feature ships ON by default.' );
		self::assertSame( '#2c3e50', $opts['header_bg'] );
		self::assertSame( 'system', $opts['body_font_family'] );
		self::assertSame( 600, $opts['body_max_width'] );
	}

	public function test_saved_options_roundtrip(): void {
		Options::update(
			array(
				'enabled'         => true,
				'header_bg'       => '#abcdef',
				'body_font_size'  => 18,
				'header_alignment' => 'left',
				'from_email'      => 'mailer@example.test',
				'from_name'       => '  Spaces around  ',
				'footer_text'     => 'Hello <strong>world</strong>',
			)
		);

		$opts = Options::get();

		self::assertTrue( $opts['enabled'] );
		self::assertSame( '#abcdef', $opts['header_bg'] );
		self::assertSame( 18, $opts['body_font_size'] );
		self::assertSame( 'left', $opts['header_alignment'] );
		self::assertSame( 'mailer@example.test', $opts['from_email'] );
		self::assertSame( 'Spaces around', $opts['from_name'], 'sanitize_text_field must trim whitespace.' );
		self::assertSame( 'Hello <strong>world</strong>', $opts['footer_text'], 'wp_kses_post keeps allowed inline markup.' );
	}

	public function test_color_sanitizer_falls_back_to_default_on_invalid_input(): void {
		$defaults = Options::defaults();

		Options::update(
			array(
				'header_bg' => 'definitely not a color',
				'body_bg'   => '#zzzzzz',
				'wrapper_bg' => '#a1b2c3', // valid → kept
			)
		);

		$opts = Options::get();

		self::assertSame( $defaults['header_bg'], $opts['header_bg'], 'Invalid color falls back to default rather than getting saved as-is.' );
		self::assertSame( $defaults['body_bg'], $opts['body_bg'] );
		self::assertSame( '#a1b2c3', $opts['wrapper_bg'] );
	}

	public function test_email_and_enum_sanitizers(): void {
		$defaults = Options::defaults();

		Options::update(
			array(
				'from_email'      => 'not an email',
				'header_alignment' => 'diagonal',     // not in whitelist
				'body_font_family' => 'comic-sans',    // not in whitelist
			)
		);

		$opts = Options::get();

		self::assertSame( '', $opts['from_email'], 'sanitize_email returns empty string for malformed input.' );
		self::assertSame( $defaults['header_alignment'], $opts['header_alignment'] );
		self::assertSame( $defaults['body_font_family'], $opts['body_font_family'] );
	}

	public function test_integer_sanitizer_coerces_negative_values_to_zero(): void {
		Options::update(
			array(
				'header_padding'    => -10,
				'body_max_width'    => '720px',  // string with unit → absint strips non-digits
				'wrapper_padding'   => '32',
			)
		);

		$opts = Options::get();

		self::assertSame( 10, $opts['header_padding'], 'absint() flips negatives to positive.' );
		self::assertSame( 720, $opts['body_max_width'] );
		self::assertSame( 32, $opts['wrapper_padding'] );
	}

	public function test_reset_returns_options_to_defaults(): void {
		Options::update( array( 'header_bg' => '#aabbcc' ) );
		self::assertSame( '#aabbcc', Options::get()['header_bg'] );

		Options::reset();

		$defaults = Options::defaults();
		self::assertSame( $defaults['header_bg'], Options::get()['header_bg'] );
	}

	public function test_is_enabled_reads_the_toggle_with_default_true(): void {
		self::assertTrue( Options::isEnabled(), 'Fresh install ships with the engine on.' );

		Options::update( array( 'enabled' => false ) );
		self::assertFalse( Options::isEnabled() );

		Options::update( array( 'enabled' => true ) );
		self::assertTrue( Options::isEnabled() );
	}

	public function test_default_options_filter_can_override_baseline(): void {
		add_filter(
			'wp_tmq_template_default_options',
			static function ( array $defaults ): array {
				$defaults['header_bg'] = '#0a0a0a';
				return $defaults;
			}
		);

		self::assertSame( '#0a0a0a', Options::defaults()['header_bg'] );

		remove_all_filters( 'wp_tmq_template_default_options' );
	}
}
