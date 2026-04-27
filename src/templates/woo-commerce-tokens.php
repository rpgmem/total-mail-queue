<?php
/**
 * WooCommerce-aware token handler.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Templates;

/**
 * Captures the WC_Order context that WooCommerce stuffs onto its email
 * objects, then exposes a handful of order-related tokens via the
 * {@see Tokens} filter pipeline.
 *
 * Registration is unconditional — when WooCommerce is missing,
 * `woocommerce_email_header` simply never fires, no order is captured,
 * and {@see addTokens()} returns the token map untouched. The cost on
 * non-WC sites is two filter registrations, ~0 µs.
 *
 * The order is captured from the `WC_Email` instance passed to the
 * `woocommerce_email_header` action — every customer-facing WC email
 * fires that action with the live `$email` object, whose `->object`
 * property holds the WC_Order (or WC_Customer / WC_Refund) being
 * delivered. We duck-type rather than typehint so the file loads on
 * sites without WC.
 */
final class WooCommerceTokens {

	/**
	 * Most-recently-captured order. Cleared by {@see reset()}.
	 *
	 * @var object|null
	 */
	private static $current_order = null;

	/**
	 * Hook into the WC email pipeline (capture) and the engine's token
	 * filter (publish).
	 */
	public static function register(): void {
		add_action( 'woocommerce_email_header', array( self::class, 'captureOrder' ), 1, 2 );
		add_filter( 'wp_tmq_template_tokens', array( self::class, 'addTokens' ), 10, 2 );
	}

	/**
	 * `woocommerce_email_header` action callback. Stores the order from
	 * the WC_Email instance for later consumption by {@see addTokens()}.
	 *
	 * @param mixed $email_heading Email heading string (unused).
	 * @param mixed $email         The WC_Email instance being rendered.
	 */
	public static function captureOrder( $email_heading, $email = null ): void {
		unset( $email_heading );

		if ( ! is_object( $email ) || ! property_exists( $email, 'object' ) ) {
			return;
		}
		$order = $email->object;
		if ( is_object( $order ) && method_exists( $order, 'get_order_number' ) ) {
			self::$current_order = $order;
		}
	}

	/**
	 * `wp_tmq_template_tokens` filter callback. Adds order-related
	 * tokens when an order has been captured for the current request.
	 * Returns the token map untouched otherwise.
	 *
	 * @param array<string,string> $tokens Existing tokens.
	 * @param array<string,mixed>  $args   wp_mail() arguments (unused).
	 * @return array<string,string>
	 */
	public static function addTokens( $tokens, $args = array() ): array {
		unset( $args );
		if ( ! is_array( $tokens ) ) {
			$tokens = array();
		}
		if ( null === self::$current_order ) {
			return $tokens;
		}
		$order = self::$current_order;

		$tokens['order_number']        = self::call( $order, 'get_order_number' );
		$tokens['customer_first_name'] = self::call( $order, 'get_billing_first_name' );
		$tokens['customer_last_name']  = self::call( $order, 'get_billing_last_name' );
		$tokens['customer_email']      = self::call( $order, 'get_billing_email' );
		$tokens['order_total']         = self::call( $order, 'get_total' );

		if ( method_exists( $order, 'get_date_created' ) ) {
			$date = $order->get_date_created();
			if ( is_object( $date ) && method_exists( $date, 'date_i18n' ) ) {
				$tokens['order_date'] = (string) $date->date_i18n( (string) get_option( 'date_format' ) );
			} else {
				$tokens['order_date'] = '';
			}
		} else {
			$tokens['order_date'] = '';
		}

		return $tokens;
	}

	/**
	 * Reset captured state. Called by tests and by callers that want to
	 * make sure stale order context doesn't bleed into a later email.
	 */
	public static function reset(): void {
		self::$current_order = null;
	}

	/**
	 * Whether WooCommerce is currently active. Cheap class-exists check
	 * (`autoload=false` so we don't accidentally trigger autoloading).
	 */
	public static function isActive(): bool {
		return class_exists( 'WooCommerce', false );
	}

	/**
	 * Safely invoke a getter that may not exist on the captured object,
	 * returning an empty string when missing.
	 *
	 * @param object $order  Captured order (or order-like) object.
	 * @param string $method Method name to call.
	 */
	private static function call( $order, string $method ): string {
		if ( ! method_exists( $order, $method ) ) {
			return '';
		}
		$value = $order->{$method}();
		return is_scalar( $value ) ? (string) $value : '';
	}
}
