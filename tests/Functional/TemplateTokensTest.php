<?php

declare(strict_types=1);

namespace TMQ\Tests\Functional;

use TotalMailQueue\Templates\Tokens;
use TotalMailQueue\Templates\WooCommerceTokens;

/**
 * T4 — token registry: globals, filter-driven extensions, and the
 * bundled WooCommerce handler. Pure resolution coverage; the engine is
 * exercised end-to-end in TemplateEngineTest / TemplateInterceptionTest.
 *
 * @covers \TotalMailQueue\Templates\Tokens
 * @covers \TotalMailQueue\Templates\WooCommerceTokens
 */
final class TemplateTokensTest extends FunctionalTestCase {

	protected function setUp(): void {
		parent::setUp();
		WooCommerceTokens::reset();
		// Strip any test-introduced filters from prior runs.
		remove_all_filters( 'wp_tmq_template_tokens' );
		// Re-register the bundled WC handler (Plugin::boot wired it
		// during bootstrap, but remove_all_filters above also removed it).
		WooCommerceTokens::register();
	}

	public function test_global_tokens_substituted(): void {
		$args = array(
			'to'      => 'someone@example.test',
			'subject' => 'Hello',
			'message' => 'Body',
		);

		$html = 'Hi {recipient}, welcome to {site_title}. Today: {date}, year: {year}.';
		$out  = Tokens::replace( $html, $args );

		self::assertStringContainsString( 'someone@example.test', $out );
		self::assertStringContainsString( (string) get_bloginfo( 'name' ), $out );
		self::assertStringContainsString( date_i18n( 'Y' ), $out );
		self::assertStringNotContainsString( '{recipient}', $out );
		self::assertStringNotContainsString( '{site_title}', $out );
		self::assertStringNotContainsString( '{year}', $out );
	}

	public function test_unknown_tokens_are_left_intact(): void {
		// Typos / future tokens stay visible rather than getting silently
		// stripped — easier to debug a mis-configured footer.
		$out = Tokens::replace( 'Hello {nonexistent_token}', array() );
		self::assertSame( 'Hello {nonexistent_token}', $out );
	}

	public function test_custom_tokens_via_filter(): void {
		add_filter(
			'wp_tmq_template_tokens',
			static function ( array $tokens, array $args ): array {
				$tokens['custom_brand'] = 'Acme Corp';
				return $tokens;
			},
			10,
			2
		);

		$out = Tokens::replace( 'From: {custom_brand}', array() );
		self::assertSame( 'From: Acme Corp', $out );
	}

	public function test_woocommerce_tokens_when_order_captured(): void {
		// Build a stub that quacks like WC_Order — duck-typing in
		// WooCommerceTokens means we don't need WC installed.
		$order = new class() {
			public function get_order_number(): string {
				return '1234';
			}
			public function get_billing_first_name(): string {
				return 'Jane';
			}
			public function get_billing_last_name(): string {
				return 'Doe';
			}
			public function get_billing_email(): string {
				return 'jane@example.test';
			}
			public function get_total(): string {
				return '99.50';
			}
			public function get_date_created(): object {
				return new class() {
					public function date_i18n( string $format ): string {
						return '2026-01-15';
					}
				};
			}
		};

		$email         = new \stdClass();
		$email->object = $order;

		WooCommerceTokens::captureOrder( 'Heading', $email );

		$tokens = Tokens::resolve( array() );

		self::assertSame( '1234', $tokens['order_number'] );
		self::assertSame( 'Jane', $tokens['customer_first_name'] );
		self::assertSame( 'Doe', $tokens['customer_last_name'] );
		self::assertSame( 'jane@example.test', $tokens['customer_email'] );
		self::assertSame( '99.50', $tokens['order_total'] );
		self::assertSame( '2026-01-15', $tokens['order_date'] );
	}

	public function test_woocommerce_tokens_absent_when_no_order_captured(): void {
		// No captureOrder call → WC tokens shouldn't appear.
		$tokens = Tokens::resolve( array() );

		self::assertArrayNotHasKey( 'order_number', $tokens );
		self::assertArrayNotHasKey( 'customer_email', $tokens );
		self::assertArrayNotHasKey( 'order_total', $tokens );
	}

	public function test_capture_ignores_objects_without_order_shape(): void {
		// An email object whose ->object is something else (a WC_Customer,
		// a stdClass, anything without `get_order_number`) must not be
		// captured as an order.
		$email         = new \stdClass();
		$email->object = new \stdClass(); // no methods at all

		WooCommerceTokens::captureOrder( 'Heading', $email );

		$tokens = Tokens::resolve( array() );
		self::assertArrayNotHasKey( 'order_number', $tokens );
	}

	public function test_replace_substitutes_inside_html(): void {
		// End-to-end: the substitution layer must work on a realistic
		// chunk of the rendered wrapper (multiple tokens, mixed casing).
		$html = '<div>Hello {recipient} — sent on {date} from {site_title}.</div>';
		$out  = Tokens::replace(
			$html,
			array( 'to' => 'a@b.test' )
		);

		self::assertStringContainsString( 'Hello a@b.test', $out );
		self::assertStringContainsString( '— sent on ' . date_i18n( (string) get_option( 'date_format' ) ), $out );
	}
}
