<?php

declare(strict_types=1);

namespace TMQ\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TotalMailQueue\Sources\Detector;

/**
 * Each primary listener in {@see Detector} must be a thin pass-through:
 * it returns the filter argument unchanged AND it stamps the matching
 * source marker so the next `pre_wp_mail` invocation can read it.
 *
 * @covers \TotalMailQueue\Sources\Detector
 */
final class SourcesDetectorListenersTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        Detector::reset();
    }

    /**
     * @dataProvider listenerCases
     */
    public function test_listener_records_the_expected_source(
        string $method,
        string $expected_key,
        string $expected_group
    ): void {
        $payload = (object) array( 'subject' => 'unchanged', 'message' => 'unchanged' );

        // Each listener takes one arg and must return it unchanged.
        $returned = Detector::$method( $payload );

        self::assertSame( $payload, $returned, "Listener $method must return its first argument unchanged." );

        $current = Detector::consume();
        self::assertNotNull( $current, "Listener $method must set a marker." );
        self::assertSame( $expected_key, $current['key'] );
        self::assertSame( $expected_group, $current['group'] );
        self::assertNotSame( '', $current['label'] );
    }

    /**
     * @return array<string,array{0:string,1:string,2:string}>
     */
    public static function listenerCases(): array {
        return array(
            // Existing S2 listeners.
            'password_reset'                  => array( 'markPasswordReset', 'wp_core:password_reset', 'WordPress Core' ),
            'new_user'                        => array( 'markNewUser', 'wp_core:new_user', 'WordPress Core' ),
            'new_user_admin'                  => array( 'markNewUserAdmin', 'wp_core:new_user_admin', 'WordPress Core' ),
            'password_change'                 => array( 'markPasswordChange', 'wp_core:password_change', 'WordPress Core' ),
            'email_change'                    => array( 'markEmailChange', 'wp_core:email_change', 'WordPress Core' ),
            'auto_update'                     => array( 'markAutoUpdate', 'wp_core:auto_update', 'WordPress Core' ),
            'comment_notification'            => array( 'markCommentNotification', 'wp_core:comment_notification', 'WordPress Core' ),
            'comment_moderation'              => array( 'markCommentModeration', 'wp_core:comment_moderation', 'WordPress Core' ),
            // New listeners added for full WP-core coverage.
            'password_change_admin_notify'    => array( 'markPasswordChangeAdminNotify', 'wp_core:password_change_admin_notify', 'WordPress Core' ),
            'admin_email_change_confirm'      => array( 'markAdminEmailChangeConfirm', 'wp_core:admin_email_change_confirm', 'WordPress Core' ),
            'auto_update_plugins_themes'      => array( 'markAutoUpdatePluginsThemes', 'wp_core:auto_update_plugins_themes', 'WordPress Core' ),
            'user_action_confirm'             => array( 'markUserActionConfirm', 'wp_core:user_action_confirm', 'WordPress Core' ),
            'privacy_export_ready'            => array( 'markPrivacyExportReady', 'wp_core:privacy_export_ready', 'WordPress Core' ),
            'privacy_erasure_done'            => array( 'markPrivacyErasureDone', 'wp_core:privacy_erasure_done', 'WordPress Core' ),
            'recovery_mode'                   => array( 'markRecoveryMode', 'wp_core:recovery_mode', 'WordPress Core' ),
        );
    }
}
