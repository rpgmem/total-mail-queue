<?php

declare(strict_types=1);

namespace TMQ\Tests\Functional;

use WP_Ajax_UnitTestCase;
use WPAjaxDieContinueException;
use WPAjaxDieStopException;

/**
 * @covers ::wp_tmq_ajax_test_smtp_connection
 */
final class AjaxTestSmtpConnectionTest extends WP_Ajax_UnitTestCase {

    protected function setUp(): void {
        parent::setUp();
        // Re-run activation in case a previous test dropped the tables.
        wp_tmq_updateDatabaseTables();
        delete_option( 'wp_tmq_settings' );
        $GLOBALS['wp_tmq_options'] = wp_tmq_get_settings();
    }

    public function test_subscribers_receive_a_403_permission_error(): void {
        $subscriber = self::factory()->user->create( array( 'role' => 'subscriber' ) );
        wp_set_current_user( $subscriber );

        $_POST['_nonce'] = wp_create_nonce( 'wp_tmq_test_smtp' );
        $_POST['host']   = 'smtp.example.test';

        $payload = $this->captureAjaxResponse( 'wp_tmq_test_smtp' );

        self::assertFalse( $payload['success'] );
        self::assertStringContainsString( 'Permission denied', (string) $payload['data']['message'] );
    }

    public function test_admin_with_invalid_nonce_is_rejected(): void {
        $admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
        wp_set_current_user( $admin );

        $_POST['_nonce'] = 'not-a-valid-nonce';
        $_POST['host']   = 'smtp.example.test';

        $payload = $this->captureAjaxResponse( 'wp_tmq_test_smtp' );

        self::assertFalse( $payload['success'] );
        self::assertStringContainsString( 'Security check failed', (string) $payload['data']['message'] );
    }

    public function test_admin_with_empty_host_receives_validation_error(): void {
        $admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
        wp_set_current_user( $admin );

        $_POST['_nonce'] = wp_create_nonce( 'wp_tmq_test_smtp' );
        $_POST['host']   = '';

        $payload = $this->captureAjaxResponse( 'wp_tmq_test_smtp' );

        self::assertFalse( $payload['success'] );
        self::assertStringContainsString( 'SMTP host is required', (string) $payload['data']['message'] );
    }

    public function test_admin_connection_failure_returns_error_response(): void {
        // Pointing at a closed local port fails fast and exercises the
        // PHPMailer exception path. The exact ErrorInfo message varies by
        // PHPMailer version and platform, so we only assert on the success
        // flag, not the message string itself.
        $admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
        wp_set_current_user( $admin );

        $_POST['_nonce']     = wp_create_nonce( 'wp_tmq_test_smtp' );
        $_POST['host']       = '127.0.0.1';
        $_POST['port']       = 1; // closed
        $_POST['encryption'] = 'none';
        $_POST['auth']       = 0;

        $payload = $this->captureAjaxResponse( 'wp_tmq_test_smtp' );

        self::assertFalse( $payload['success'] );
        self::assertArrayHasKey( 'message', $payload['data'] );
    }

    /**
     * Run an AJAX action and decode the JSON wp_send_json_*() emitted.
     *
     * WP_Ajax_UnitTestCase replaces wp_die() with a throwing variant so the
     * test thread survives.
     *
     * @return array{success:bool,data:mixed}
     */
    private function captureAjaxResponse( string $action ): array {
        try {
            $this->_handleAjax( $action );
        } catch ( WPAjaxDieContinueException $e ) {
            // wp_send_json_* uses wp_die without forcing termination — caught here.
        } catch ( WPAjaxDieStopException $e ) {
            // Some error paths force-terminate; same handling.
        }

        $body = $this->_last_response;
        $decoded = json_decode( $body, true );
        if ( ! is_array( $decoded ) ) {
            $this->fail( "Expected JSON response, got: $body" );
        }
        return $decoded;
    }

    protected function tearDown(): void {
        unset( $_POST['_nonce'], $_POST['host'], $_POST['port'], $_POST['encryption'], $_POST['auth'] );
        parent::tearDown();
    }
}
