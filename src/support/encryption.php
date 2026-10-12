<?php
/**
 * Authenticated encryption helper for SMTP passwords.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Support;

/**
 * Encrypts and decrypts short strings (SMTP credentials) for at-rest storage.
 *
 * The current envelope is `tmq2:` followed by base64 of IV, AES-256-CBC
 * ciphertext and an HMAC-SHA256 over both (encrypt-then-MAC). A payload
 * whose MAC does not verify is refused before it reaches the cipher, so a
 * modified ciphertext can neither decrypt to something else nor be used to
 * probe the padding. The encryption and MAC keys are derived separately
 * from {@see wp_salt()}, so ciphertexts only open on the install that made
 * them.
 *
 * The legacy envelope (base64 of `iv::ciphertext`, no MAC, the salt used as
 * the key) is still read: SMTP rows are re-encrypted by
 * {@see \TotalMailQueue\Smtp\Repository::upgradePasswordEncryption()}, but
 * a pending queue row may carry a captured third-party password written by
 * an earlier version, and a row whose salt changed cannot be re-encrypted.
 */
final class Encryption {

	private const CIPHER = 'aes-256-cbc';

	/**
	 * Bytes of the IV: the AES block size, fixed for this cipher.
	 */
	private const IV_LENGTH = 16;

	/**
	 * Marks the authenticated envelope.
	 */
	public const PREFIX = 'tmq2:';

	/**
	 * Bytes of the HMAC-SHA256 tag.
	 */
	private const MAC_LENGTH = 32;

	/**
	 * Encrypt a plaintext string.
	 *
	 * @param string $plain_text Payload (empty input returns empty output).
	 * @return string `tmq2:`-prefixed envelope, or '' for empty input.
	 */
	public static function encrypt( string $plain_text ): string {
		if ( '' === $plain_text ) {
			return '';
		}
		$keys       = self::keys();
		$iv         = random_bytes( self::IV_LENGTH );
		$ciphertext = openssl_encrypt( $plain_text, self::CIPHER, $keys['encrypt'], OPENSSL_RAW_DATA, $iv );
		if ( false === $ciphertext ) {
			return '';
		}
		$mac = hash_hmac( 'sha256', $iv . $ciphertext, $keys['mac'], true );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- safe storage of the binary IV, ciphertext and tag
		return self::PREFIX . base64_encode( $iv . $ciphertext . $mac );
	}

	/**
	 * Decrypt a payload produced by {@see Encryption::encrypt()}, or by the
	 * legacy envelope. A malformed envelope, a MAC that does not verify, a
	 * wrong key, or any openssl failure collapses to an empty string —
	 * callers (PHPMailer's $Password, the captured-config replay) treat
	 * empty as "no credential" and the SMTP send fails loudly rather than
	 * shipping garbage.
	 *
	 * @param string $encrypted_text Envelope.
	 * @return string Plaintext, or '' when the envelope is empty / unreadable.
	 */
	public static function decrypt( string $encrypted_text ): string {
		if ( '' === $encrypted_text ) {
			return '';
		}
		if ( self::isCurrent( $encrypted_text ) ) {
			return self::decryptCurrent( substr( $encrypted_text, strlen( self::PREFIX ) ) );
		}
		return self::decryptLegacy( $encrypted_text );
	}

	/**
	 * Whether a stored value already uses the authenticated envelope.
	 *
	 * @param string $encrypted_text Stored value.
	 */
	public static function isCurrent( string $encrypted_text ): bool {
		return 0 === strpos( $encrypted_text, self::PREFIX );
	}

	/**
	 * Open the authenticated envelope.
	 *
	 * @param string $payload Base64 part after the prefix.
	 */
	private static function decryptCurrent( string $payload ): string {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- decoding the stored IV, ciphertext and tag
		$data = base64_decode( $payload, true );
		if ( false === $data || strlen( $data ) <= self::IV_LENGTH + self::MAC_LENGTH ) {
			return '';
		}
		$keys       = self::keys();
		$iv         = substr( $data, 0, self::IV_LENGTH );
		$ciphertext = substr( $data, self::IV_LENGTH, -self::MAC_LENGTH );
		$mac        = substr( $data, -self::MAC_LENGTH );
		if ( ! hash_equals( hash_hmac( 'sha256', $iv . $ciphertext, $keys['mac'], true ), $mac ) ) {
			return '';
		}
		$plain = openssl_decrypt( $ciphertext, self::CIPHER, $keys['encrypt'], OPENSSL_RAW_DATA, $iv );
		return false === $plain ? '' : $plain;
	}

	/**
	 * Open the legacy envelope: base64 of `<iv>::<base64 ciphertext>`.
	 *
	 * @param string $encrypted_text Legacy envelope.
	 */
	private static function decryptLegacy( string $encrypted_text ): string {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- decoding a stored legacy IV and ciphertext
		$data = base64_decode( $encrypted_text );
		if ( false === $data ) {
			return '';
		}

		// The envelope is `<iv><::><ciphertext>` with a fixed-length binary IV.
		// We must split by that known length, NOT by exploding on `::`: the
		// random IV can itself contain the bytes `::` (or end in `:`), which
		// would truncate it to 15 bytes and make openssl_decrypt() fail
		// intermittently. The base64 ciphertext never contains `:`, so the
		// only `::` we can trust is the separator right after the IV.
		if ( strlen( $data ) < self::IV_LENGTH + 2 || '::' !== substr( $data, self::IV_LENGTH, 2 ) ) {
			return '';
		}
		$iv        = substr( $data, 0, self::IV_LENGTH );
		$encrypted = substr( $data, self::IV_LENGTH + 2 );
		$plain     = openssl_decrypt( $encrypted, self::CIPHER, wp_salt( 'auth' ), 0, $iv );
		return false === $plain ? '' : $plain;
	}

	/**
	 * The two keys, derived separately from the install's salt so the
	 * cipher and the MAC never share key material.
	 *
	 * @return array{encrypt:string,mac:string}
	 */
	private static function keys(): array {
		$salt = wp_salt( 'auth' );
		return array(
			'encrypt' => hash_hmac( 'sha256', 'total-mail-queue:encrypt', $salt, true ),
			'mac'     => hash_hmac( 'sha256', 'total-mail-queue:authenticate', $salt, true ),
		);
	}
}
