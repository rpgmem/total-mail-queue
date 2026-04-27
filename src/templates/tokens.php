<?php
/**
 * Template token registry — `{token}` substitution for the wrapper.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Templates;

/**
 * Resolves the `{token}` placeholders the engine substitutes inside the
 * rendered HTML wrapper and the `footer_text` option.
 *
 * Two layers:
 *
 * - **Globals.** Nine fixed tokens that are valid for any email regardless
 *   of source — site title, recipient, date, etc.
 * - **Filter-driven extensions.** The `wp_tmq_template_tokens` filter lets
 *   integrations (the bundled {@see WooCommerceTokens} handler, third-party
 *   plugins, custom code) add their own tokens. Integrations decide
 *   internally when they apply (e.g. WC checks for a captured order).
 *
 * The class is pure functions — no state — so direct callers and the
 * `wp_mail` filter both produce identical output.
 */
final class Tokens {

	/**
	 * Build the global token map for an email.
	 *
	 * @param array<string,mixed> $args wp_mail() arguments (used for the
	 *                                   `{recipient}` token).
	 * @return array<string,string>
	 */
	public static function globals( array $args ): array {
		$to        = isset( $args['to'] ) ? $args['to'] : '';
		$recipient = is_array( $to ) ? (string) reset( $to ) : (string) $to;

		return array(
			'site_title'       => (string) get_bloginfo( 'name' ),
			'site_url'         => (string) get_option( 'siteurl' ),
			'home_url'         => (string) get_option( 'home' ),
			'site_description' => (string) get_bloginfo( 'description' ),
			'admin_email'      => (string) get_option( 'admin_email' ),
			'recipient'        => $recipient,
			'date'             => date_i18n( (string) get_option( 'date_format' ) ),
			'time'             => date_i18n( (string) get_option( 'time_format' ) ),
			'year'             => date_i18n( 'Y' ),
		);
	}

	/**
	 * Resolve the full token map: globals + filter-supplied additions.
	 *
	 * @param array<string,mixed> $args wp_mail() arguments.
	 * @return array<string,string>
	 */
	public static function resolve( array $args ): array {
		$tokens = self::globals( $args );

		/**
		 * Filter the token map used by the wrapper. Add per-source or
		 * per-plugin tokens here. The bundled WooCommerce handler
		 * registers on this filter.
		 *
		 * @param array<string,string> $tokens Token name (without braces) → value.
		 * @param array<string,mixed>  $args   wp_mail() arguments.
		 */
		$tokens = (array) apply_filters( 'wp_tmq_template_tokens', $tokens, $args );

		// Coerce to strings — the substitution loop relies on it, and
		// integrations sometimes return non-string values (e.g. ints).
		$out = array();
		foreach ( $tokens as $name => $value ) {
			if ( is_scalar( $value ) || ( is_object( $value ) && method_exists( $value, '__toString' ) ) ) {
				$out[ (string) $name ] = (string) $value;
			} else {
				$out[ (string) $name ] = '';
			}
		}
		return $out;
	}

	/**
	 * Substitute every `{token}` occurrence in `$html` with its resolved
	 * value. Unknown tokens are left untouched (so they remain visible to
	 * the recipient — opinionated choice over silently swallowing typos).
	 *
	 * @param string              $html Rendered HTML to scan.
	 * @param array<string,mixed> $args wp_mail() arguments.
	 * @return string
	 */
	public static function replace( string $html, array $args ): string {
		$tokens  = self::resolve( $args );
		$search  = array();
		$replace = array();
		foreach ( $tokens as $name => $value ) {
			$search[]  = '{' . $name . '}';
			$replace[] = $value;
		}
		return str_replace( $search, $replace, $html );
	}
}
