<?php

declare(strict_types=1);

namespace TMQ\Tests\Functional;

use TotalMailQueue\Database\Migrator;
use TotalMailQueue\Support\Encryption;

/**
 * Stored SMTP passwords move from the legacy, unauthenticated envelope to
 * the authenticated one on the next version bump.
 *
 * @covers \TotalMailQueue\Smtp\Repository
 * @covers \TotalMailQueue\Support\Encryption
 */
final class SmtpPasswordUpgradeTest extends FunctionalTestCase {

    public function test_a_legacy_password_is_re_encrypted_and_still_opens(): void {
        $id = $this->insertSmtpAccount( array( 'password' => $this->legacy( 'old-secret' ) ) );

        Migrator::install();

        $stored = $this->storedPassword( $id );
        $this->assertTrue( Encryption::isCurrent( $stored ) );
        $this->assertSame( 'old-secret', Encryption::decrypt( $stored ) );
    }

    public function test_a_current_password_is_left_untouched(): void {
        $id     = $this->insertSmtpAccount( array( 'password' => Encryption::encrypt( 'new-secret' ) ) );
        $before = $this->storedPassword( $id );

        Migrator::install();

        $this->assertSame( $before, $this->storedPassword( $id ) );
    }

    public function test_a_password_that_no_longer_decrypts_is_kept_rather_than_erased(): void {
        // Written under another salt: re-encrypting the empty result would
        // wipe it, so the row is left for the account form to fix.
        $iv      = str_repeat( 'C', 16 );
        $foreign = base64_encode( $iv . '::' . openssl_encrypt( 'x', 'aes-256-cbc', 'another-install-salt', 0, $iv ) );
        $id      = $this->insertSmtpAccount( array( 'password' => $foreign ) );

        Migrator::install();

        $this->assertSame( $foreign, $this->storedPassword( $id ) );
    }

    private function legacy( string $plain ): string {
        $iv = str_repeat( 'B', 16 );
        return base64_encode( $iv . '::' . openssl_encrypt( $plain, 'aes-256-cbc', wp_salt( 'auth' ), 0, $iv ) );
    }

    private function storedPassword( int $id ): string {
        global $wpdb;
        return (string) $wpdb->get_var( $wpdb->prepare( "SELECT `password` FROM `{$this->smtpTable()}` WHERE `id` = %d", $id ) );
    }
}
