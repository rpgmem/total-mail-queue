<?php

declare(strict_types=1);

namespace TMQ\Tests\Integration;

use Brain\Monkey\Functions;

/**
 * @covers \TotalMailQueue\Smtp\PhpMailerCapturer::capture
 */
final class CapturePhpmailerConfigTest extends IntegrationTestCase {

    public function test_returns_null_when_no_other_plugin_changed_the_mailer(): void {
        // Default: do_action_ref_array is a no-op, so the temporary PHPMailer
        // keeps its defaults and there is nothing for the plugin to capture.
        Functions\when( 'do_action_ref_array' )->justReturn();

        $config = \TotalMailQueue\Smtp\PhpMailerCapturer::capture();

        self::assertNull( $config );
    }

    public function test_captures_config_when_phpmailer_init_changes_host_or_port_or_auth(): void {
        Functions\when( 'do_action_ref_array' )->alias( static function ( string $tag, array $args ): void {
            if ( $tag !== 'phpmailer_init' ) {
                return;
            }
            // The first arg is a reference to the temporary PHPMailer instance.
            $mailer = $args[0];
            $mailer->Host       = 'smtp.example.test';
            $mailer->Port       = 2525;
            $mailer->SMTPSecure = 'tls';
            $mailer->SMTPAuth   = true;
            $mailer->Username   = 'mailer-user';
            $mailer->Password   = 'mailer-pass';
            $mailer->From       = 'noreply@example.test';
            $mailer->FromName   = 'Example';
            $mailer->Mailer     = 'smtp';
        } );

        $config = \TotalMailQueue\Smtp\PhpMailerCapturer::capture();

        self::assertIsArray( $config );
        self::assertSame( 'smtp.example.test', $config['Host'] );
        self::assertSame( 2525, $config['Port'] );
        self::assertSame( 'tls', $config['SMTPSecure'] );
        self::assertTrue( $config['SMTPAuth'] );
        self::assertSame( 'mailer-user', $config['Username'] );
        self::assertSame( 'noreply@example.test', $config['From'] );
        self::assertSame( 'Example', $config['FromName'] );
        self::assertSame( 'smtp', $config['Mailer'] );
    }

    public function test_captured_password_is_encrypted_not_stored_in_plaintext(): void {
        $plain = 'super-secret-mailer-password';

        Functions\when( 'do_action_ref_array' )->alias( static function ( string $tag, array $args ) use ( $plain ): void {
            if ( $tag !== 'phpmailer_init' ) {
                return;
            }
            $mailer = $args[0];
            $mailer->Host     = 'smtp.example.test';
            $mailer->Port     = 587;
            $mailer->SMTPAuth = true;
            $mailer->Username = 'u';
            $mailer->Password = $plain;
        } );

        $config = \TotalMailQueue\Smtp\PhpMailerCapturer::capture();

        self::assertIsArray( $config );
        self::assertNotSame( $plain, $config['Password'], 'Password must not be persisted in plaintext.' );
        self::assertSame( $plain, \TotalMailQueue\Support\Encryption::decrypt( $config['Password'] ), 'Encrypted password must round-trip through wp_tmq_decrypt_password.' );
    }
}
