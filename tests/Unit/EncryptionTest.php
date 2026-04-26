<?php

declare(strict_types=1);

namespace TMQ\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * @covers \TotalMailQueue\Support\Encryption::encrypt
 * @covers \TotalMailQueue\Support\Encryption::decrypt
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

    public function test_decrypt_returns_false_for_payload_tampered_with_garbage_ciphertext(): void {
        // Valid structure (iv::ciphertext) but the ciphertext part is not a real openssl_encrypt output.
        $iv      = str_repeat( 'A', openssl_cipher_iv_length( 'aes-256-cbc' ) );
        $tampered = base64_encode( $iv . '::not-real-ciphertext' );

        self::assertFalse( \TotalMailQueue\Support\Encryption::decrypt( $tampered ) );
    }
}
