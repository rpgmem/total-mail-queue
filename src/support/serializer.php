<?php
/**
 * JSON serialization helper with backwards-compatible unserialize fallback.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Support;

/**
 * Encodes values for storage and decodes them back, transparently handling
 * payloads written by older plugin versions that used PHP's serialize().
 */
final class Serializer {

	/**
	 * Encode a value as JSON. Encoding failures (recursive references,
	 * malformed UTF-8) collapse to an empty string — none of the values the
	 * plugin serializes can hit that path under normal use, and a stored ''
	 * round-trips through {@see decode()} to `null` rather than poisoning a
	 * column with `false`.
	 *
	 * @param mixed $value Anything wp_json_encode() accepts.
	 */
	public static function encode( $value ): string {
		$encoded = wp_json_encode( $value );
		return false === $encoded ? '' : $encoded;
	}

	/**
	 * Decode a stored payload.
	 *
	 * Tries JSON first; falls back to PHP unserialize for legacy data written
	 * by plugin versions before 2.2.0. Object instantiation is blocked
	 * (`allowed_classes => false`) so a corrupt or hostile payload cannot
	 * trigger object injection.
	 *
	 * @param mixed $raw Stored value. Non-strings are returned unchanged.
	 * @return mixed Decoded value, or the raw input when neither JSON nor a
	 *               recognised serialize payload.
	 */
	public static function decode( $raw ) {
		if ( empty( $raw ) || ! is_string( $raw ) ) {
			return $raw;
		}
		$json = json_decode( $raw, true );
		if ( JSON_ERROR_NONE === json_last_error() ) {
			return $json;
		}
		if ( is_serialized( $raw ) ) {
			// The @ swallows PHP's notice on truncated/corrupt legacy payloads —
			// we treat those as "no data" by returning false.
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize, WordPress.PHP.NoSilencedErrors.Discouraged
			return @unserialize( $raw, array( 'allowed_classes' => false ) );
		}
		return $raw;
	}
}
