<?php
/**
 * AES-256-CBC encryption helper for SMTP passwords.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Support;

/**
 * Encrypts and decrypts short strings (SMTP credentials) for at-rest storage.
 *
 * The key is derived from {@see wp_salt()} so ciphertexts produced on one
 * WordPress install can only be decrypted on the same install. A new
 * 16-byte IV is generated per call, prepended to the ciphertext and
 * base64-encoded so the entire payload survives storage in TEXT columns
 * and email headers.
 */
final class Encryption {

	private const CIPHER = 'aes-256-cbc';

	/**
	 * Encrypt a plaintext string.
	 *
	 * @param string $plain_text Payload (empty input returns empty output).
	 * @return string Base64-encoded "iv::ciphertext" envelope, or '' for empty input.
	 */
	public static function encrypt( string $plain_text ): string {
		if ( '' === $plain_text ) {
			return '';
		}
		$key       = wp_salt( 'auth' );
		$iv_length = openssl_cipher_iv_length( self::CIPHER );
		$iv        = openssl_random_pseudo_bytes( (int) $iv_length );
		$encrypted = openssl_encrypt( $plain_text, self::CIPHER, $key, 0, $iv );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- safe storage of binary IV+ciphertext
		return base64_encode( $iv . '::' . $encrypted );
	}

	/**
	 * Decrypt a payload produced by {@see Encryption::encrypt()}. A malformed
	 * envelope, a wrong key, or any openssl failure collapses to an empty
	 * string — callers (PHPMailer's $Password, the captured-config replay)
	 * treat empty as "no credential" and the SMTP send fails loudly rather
	 * than shipping garbage.
	 *
	 * @param string $encrypted_text Base64-encoded envelope.
	 * @return string Plaintext, or '' when the envelope is empty / unreadable.
	 */
	public static function decrypt( string $encrypted_text ): string {
		if ( '' === $encrypted_text ) {
			return '';
		}
		$key = wp_salt( 'auth' );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- decoding stored IV+ciphertext from Encryption::encrypt
		$data = base64_decode( $encrypted_text );
		if ( false === $data ) {
			return '';
		}
		$parts = explode( '::', $data, 2 );
		if ( 2 !== count( $parts ) ) {
			return '';
		}
		[ $iv, $encrypted ] = $parts;
		$plain              = openssl_decrypt( $encrypted, self::CIPHER, $key, 0, $iv );
		return false === $plain ? '' : $plain;
	}
}
