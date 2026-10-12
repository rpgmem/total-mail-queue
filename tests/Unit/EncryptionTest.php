<?php

declare(strict_types=1);

namespace TMQ\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * @covers \TotalMailQueue\Support\Encryption
 */
final class EncryptionTest extends TestCase {

    public function test_encrypt_returns_empty_string_for_empty_input(): void {
        self::assertSame( '', \TotalMailQueue\Support\Encryption::encrypt( '' ) );
    }

    public function test_decrypt_returns_empty_string_for_empty_input(): void {
        self::assertSame( '', \TotalMailQueue\Support\Encryption::decrypt( '' ) );
    }

    public function test_encrypt_decrypt_round_trip_for_simple_password(): void {
        $plain = 'sup3r-secret!';

        $encrypted = \TotalMailQueue\Support\Encryption::encrypt( $plain );

        self::assertNotSame( $plain, $encrypted, 'Encrypted output must not equal the plaintext.' );
        self::assertNotSame( '', $encrypted );
        self::assertSame( $plain, \TotalMailQueue\Support\Encryption::decrypt( $encrypted ) );
    }

    public function test_encrypt_decrypt_round_trip_for_unicode_and_special_chars(): void {
        $plain = "çãõ-π-€-\"'\\\n\t空白";

        $encrypted = \TotalMailQueue\Support\Encryption::encrypt( $plain );

        self::assertSame( $plain, \TotalMailQueue\Support\Encryption::decrypt( $encrypted ) );
    }

    public function test_encrypt_uses_random_iv_so_same_input_yields_different_ciphertext(): void {
        $plain = 'identical-password';

        $first  = \TotalMailQueue\Support\Encryption::encrypt( $plain );
        $second = \TotalMailQueue\Support\Encryption::encrypt( $plain );

        self::assertNotSame( $first, $second, 'A random IV must be applied per encrypt call.' );
        self::assertSame( $plain, \TotalMailQueue\Support\Encryption::decrypt( $first ) );
        self::assertSame( $plain, \TotalMailQueue\Support\Encryption::decrypt( $second ) );
    }

    public function test_decrypt_returns_empty_string_for_malformed_payload(): void {
        // Missing the "iv::ciphertext" separator after base64 decode.
        $bogus = base64_encode( 'no-separator-here' );

        self::assertSame( '', \TotalMailQueue\Support\Encryption::decrypt( $bogus ) );
    }

    public function test_decrypt_handles_iv_containing_the_separator_bytes(): void {
        // Regression: openssl_random_pseudo_bytes() can return an IV that
        // contains "::" (or ends in ":"). Exploding the envelope on "::" then
        // truncated the IV below 16 bytes and openssl_decrypt() failed
        // intermittently. The decoder must split by the fixed IV length.
        $cipher    = 'aes-256-cbc';
        $key       = wp_salt( 'auth' );
        $plain     = 'colliding-iv-password';
        $iv_length = openssl_cipher_iv_length( $cipher );

        // A 16-byte IV that both contains and ends with the separator bytes.
        $iv = str_repeat( 'A', $iv_length - 4 ) . '::::';
        self::assertSame( $iv_length, strlen( $iv ) );

        $ciphertext = openssl_encrypt( $plain, $cipher, $key, 0, $iv );
        $envelope   = base64_encode( $iv . '::' . $ciphertext );

        self::assertSame( $plain, \TotalMailQueue\Support\Encryption::decrypt( $envelope ) );
    }

    public function test_decrypt_returns_empty_string_for_payload_tampered_with_garbage_ciphertext(): void {
        // Valid structure (iv::ciphertext) but the ciphertext part is not a real openssl_encrypt output.
        $iv      = str_repeat( 'A', openssl_cipher_iv_length( 'aes-256-cbc' ) );
        $tampered = base64_encode( $iv . '::not-real-ciphertext' );

        // openssl_decrypt() returns false; the helper collapses that to ''
        // so callers (PHPMailer's $Password) never receive a non-string.
        self::assertSame( '', \TotalMailQueue\Support\Encryption::decrypt( $tampered ) );
    }

    public function test_encrypt_writes_the_authenticated_envelope(): void {
        $encrypted = \TotalMailQueue\Support\Encryption::encrypt( 'secret' );

        $this->assertStringStartsWith( \TotalMailQueue\Support\Encryption::PREFIX, $encrypted );
        $this->assertTrue( \TotalMailQueue\Support\Encryption::isCurrent( $encrypted ) );
    }

    /**
     * @dataProvider tamperedBytes
     */
    public function test_a_modified_authenticated_envelope_is_refused( string $part ): void {
        $encrypted = \TotalMailQueue\Support\Encryption::encrypt( 'secret-password' );
        $raw       = base64_decode( substr( $encrypted, strlen( \TotalMailQueue\Support\Encryption::PREFIX ) ), true );
        $offset    = array( 'iv' => 0, 'ciphertext' => 16, 'mac' => strlen( $raw ) - 1 )[ $part ];
        $raw[ $offset ] = chr( ord( $raw[ $offset ] ) ^ 0x01 );

        $this->assertSame( '', \TotalMailQueue\Support\Encryption::decrypt( \TotalMailQueue\Support\Encryption::PREFIX . base64_encode( $raw ) ), 'One flipped bit in the ' . $part . ' must fail the MAC.' );
    }

    /**
     * @return array<string,array{0:string}>
     */
    public function tamperedBytes(): array {
        return array(
            'iv'         => array( 'iv' ),
            'ciphertext' => array( 'ciphertext' ),
            'mac'        => array( 'mac' ),
        );
    }

    public function test_a_truncated_authenticated_envelope_is_refused(): void {
        $this->assertSame( '', \TotalMailQueue\Support\Encryption::decrypt( \TotalMailQueue\Support\Encryption::PREFIX . base64_encode( str_repeat( 'x', 48 ) ) ) );
        $this->assertSame( '', \TotalMailQueue\Support\Encryption::decrypt( \TotalMailQueue\Support\Encryption::PREFIX . '%%%not base64' ) );
    }

    public function test_the_legacy_envelope_still_decrypts(): void {
        // What versions before the authenticated envelope wrote: the salt as
        // the key, base64 of "iv::base64(ciphertext)", no MAC.
        $iv     = str_repeat( 'B', 16 );
        $legacy = base64_encode( $iv . '::' . openssl_encrypt( 'old-password', 'aes-256-cbc', wp_salt( 'auth' ), 0, $iv ) );

        $this->assertFalse( \TotalMailQueue\Support\Encryption::isCurrent( $legacy ) );
        $this->assertSame( 'old-password', \TotalMailQueue\Support\Encryption::decrypt( $legacy ) );
    }
}

