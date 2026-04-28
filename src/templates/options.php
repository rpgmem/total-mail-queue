<?php
/**
 * Template-engine settings: defaults, parsing, sanitization, persistence.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Templates;

/**
 * Single source of truth for the `wp_tmq_template_options` row.
 *
 * Mirrors the shape of {@see \TotalMailQueue\Settings\Options} for the main
 * plugin settings: a `defaults()` map of every key, a `get()` reader that
 * merges persisted values with defaults, and a strict {@see Options::sanitize()}
 * step the admin form uses to scrub user input before it lands in
 * `wp_options`.
 *
 * The shape is intentionally flat (no nested arrays) so the admin UI maps
 * directly to one form input per key.
 */
final class Options {

	/**
	 * Option name under which the template settings live in `wp_options`.
	 */
	public const OPTION_NAME = 'wp_tmq_template_options';

	/**
	 * Allowed values for the `header_alignment` field.
	 */
	public const HEADER_ALIGNMENTS = array( 'left', 'center', 'right' );

	/**
	 * Allowed values for the `body_font_family` field. See
	 * {@see \TotalMailQueue\Templates\default-html.php} for the corresponding
	 * font stacks.
	 */
	public const BODY_FONT_FAMILIES = array( 'system', 'serif', 'mono', 'arial', 'georgia' );

	/**
	 * Hardcoded defaults — every option key the engine knows about.
	 *
	 * Plugins can override the baseline via the `wp_tmq_template_default_options`
	 * filter without changing the stored row.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		/**
		 * Filter the engine's built-in defaults.
		 *
		 * @param array<string,mixed> $defaults Built-in defaults map.
		 */
		return (array) apply_filters(
			'wp_tmq_template_default_options',
			array(
				'enabled'               => true,
				'header_bg'             => '#2c3e50',
				'header_text_color'     => '#ffffff',
				'header_alignment'      => 'center',
				'header_padding'        => 24,
				'header_logo_url'       => '',
				'header_logo_max_width' => 180,
				'body_bg'               => '#ffffff',
				'body_text_color'       => '#333333',
				'body_link_color'       => '#2271b1',
				'body_font_family'      => 'system',
				'body_font_size'        => 14,
				'body_padding'          => 24,
				'body_max_width'        => 600,
				'footer_bg'             => '#f5f5f5',
				'footer_text_color'     => '#666666',
				'footer_text'           => 'Sent by {site_title}',
				'footer_powered_by'     => true,
				'wrapper_bg'            => '#f0f0f1',
				'wrapper_border_radius' => 6,
				'wrapper_padding'       => 32,
			)
		);
	}

	/**
	 * Read the persisted option row, merge with defaults, and apply the
	 * post-merge filter.
	 *
	 * @return array<string,mixed>
	 */
	public static function get(): array {
		$stored = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		/**
		 * Filter the resolved template options after defaults have been
		 * merged in. Use to override values without touching `wp_options`.
		 *
		 * @param array<string,mixed> $options Resolved options.
		 */
		return (array) apply_filters( 'wp_tmq_template_options', array_merge( self::defaults(), $stored ) );
	}

	/**
	 * Whether the templates feature is currently enabled. The defaults map
	 * sets this to `true`, so a fresh install is on by default.
	 */
	public static function isEnabled(): bool {
		$options = self::get();
		if ( ! array_key_exists( 'enabled', $options ) ) {
			return true;
		}
		$value = $options['enabled'];
		return ( true === $value || '1' === $value || 1 === $value );
	}

	/**
	 * Sanitize a raw input array (typically `$_POST`) into a clean,
	 * persistable shape. Keys missing from the input fall back to defaults
	 * — never to `null` — so the resulting array is always self-contained.
	 *
	 * @param array<string,mixed> $raw User-supplied values.
	 * @return array<string,mixed>     Clean values, every key present.
	 */
	public static function sanitize( array $raw ): array {
		$defaults = self::defaults();
		$out      = $defaults;

		// Boolean fields.
		foreach ( array( 'enabled', 'footer_powered_by' ) as $field ) {
			if ( array_key_exists( $field, $raw ) ) {
				$out[ $field ] = self::toBool( $raw[ $field ] );
			}
		}

		// Hex color fields. Invalid values fall back to the default rather
		// than getting persisted as empty strings.
		$color_fields = array(
			'header_bg',
			'header_text_color',
			'body_bg',
			'body_text_color',
			'body_link_color',
			'footer_bg',
			'footer_text_color',
			'wrapper_bg',
		);
		foreach ( $color_fields as $field ) {
			if ( array_key_exists( $field, $raw ) ) {
				$clean = sanitize_hex_color( (string) $raw[ $field ] );
				if ( null !== $clean && '' !== $clean ) {
					$out[ $field ] = $clean;
				}
			}
		}

		// Integer fields (non-negative).
		$int_fields = array(
			'header_padding',
			'header_logo_max_width',
			'body_font_size',
			'body_padding',
			'body_max_width',
			'wrapper_border_radius',
			'wrapper_padding',
		);
		foreach ( $int_fields as $field ) {
			if ( array_key_exists( $field, $raw ) ) {
				$out[ $field ] = absint( $raw[ $field ] );
			}
		}

		// Enums.
		if ( array_key_exists( 'header_alignment', $raw ) ) {
			$val                     = (string) $raw['header_alignment'];
			$out['header_alignment'] = in_array( $val, self::HEADER_ALIGNMENTS, true )
				? $val
				: $defaults['header_alignment'];
		}
		if ( array_key_exists( 'body_font_family', $raw ) ) {
			$val                     = (string) $raw['body_font_family'];
			$out['body_font_family'] = in_array( $val, self::BODY_FONT_FAMILIES, true )
				? $val
				: $defaults['body_font_family'];
		}

		// URL — `esc_url_raw` keeps a clean URL or returns ''.
		if ( array_key_exists( 'header_logo_url', $raw ) ) {
			$out['header_logo_url'] = esc_url_raw( (string) $raw['header_logo_url'] );
		}

		// `footer_text` allows a small amount of HTML so users can drop in
		// `<a>` / `<strong>` / etc. — same rules as a post body excerpt.
		if ( array_key_exists( 'footer_text', $raw ) ) {
			$out['footer_text'] = wp_kses_post( (string) $raw['footer_text'] );
		}

		return $out;
	}

	/**
	 * Persist sanitized options. Returns the cleaned values that ended up
	 * in the row so callers can reflect them in the UI without re-reading
	 * the option.
	 *
	 * @param array<string,mixed> $raw Raw user input.
	 * @return array<string,mixed>
	 */
	public static function update( array $raw ): array {
		$clean = self::sanitize( $raw );
		update_option( self::OPTION_NAME, $clean );
		return $clean;
	}

	/**
	 * Wipe the row entirely. Subsequent reads return the defaults.
	 */
	public static function reset(): void {
		delete_option( self::OPTION_NAME );
	}

	/**
	 * Coerce a mixed value to a strict bool that round-trips through form
	 * submissions (`$_POST` strings) and JSON / config files.
	 *
	 * @param mixed $value Raw scalar (bool / int / string).
	 */
	private static function toBool( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_int( $value ) ) {
			return 0 !== $value;
		}
		if ( is_string( $value ) ) {
			return in_array( strtolower( trim( $value ) ), array( '1', 'true', 'on', 'yes' ), true );
		}
		return (bool) $value;
	}
}
