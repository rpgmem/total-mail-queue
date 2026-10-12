<?php

declare(strict_types=1);

namespace TMQ\Tests\Functional;

use TotalMailQueue\Database\Migrator;
use TotalMailQueue\Templates\TestEmailSender;
use WP_Ajax_UnitTestCase;
use WPAjaxDieContinueException;
use WPAjaxDieStopException;

/**
 * The "Send test email" AJAX handler: who may call it, what it validates,
 * and the two messages it sends (the generic test and a wp_core preview).
 *
 * @covers \TotalMailQueue\Templates\TestEmailSender
 */
final class TestEmailSenderTest extends WP_Ajax_UnitTestCase {

    /**
     * Messages wp_mail() was asked to send.
     *
     * @var array<int,array<string,mixed>>
     */
    private array $sent = array();

    protected function setUp(): void {
        parent::setUp();
        Migrator::install();
        delete_option( 'wp_tmq_settings' );
        if ( ! has_action( 'wp_ajax_' . TestEmailSender::ACTION ) ) {
            TestEmailSender::register();
        }
        $this->sent = array();
        add_filter( 'pre_wp_mail', array( $this, 'capture' ), 10, 2 );
    }

    protected function tearDown(): void {
        remove_filter( 'pre_wp_mail', array( $this, 'capture' ), 10 );
        $_POST = array();
        parent::tearDown();
    }

    /**
     * Record the message and report it as sent, or as failed when the test
     * addresses it to fail@.
     *
     * @param mixed               $short Short-circuit value.
     * @param array<string,mixed> $atts  wp_mail() arguments.
     */
    public function capture( $short, array $atts ): bool {
        $this->sent[] = $atts;
        return 'fail@example.test' !== $atts['to'];
    }

    public function test_a_subscriber_is_refused(): void {
        wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
        $_POST = array(
            '_nonce' => wp_create_nonce( TestEmailSender::NONCE ),
            'to'     => 'me@example.test',
        );

        $payload = $this->call();

        self::assertFalse( $payload['success'] );
        self::assertSame( 'Permission denied.', $payload['data']['message'] );
        self::assertSame( array(), $this->sent );
    }

    public function test_a_forged_nonce_is_refused(): void {
        $this->admin();
        $_POST = array(
            '_nonce' => 'forged',
            'to'     => 'me@example.test',
        );

        self::assertStringContainsString( 'Security check failed', $this->call()['data']['message'] );
        self::assertSame( array(), $this->sent );
    }

    public function test_an_invalid_recipient_is_refused(): void {
        $this->admin();
        $_POST = array(
            '_nonce' => wp_create_nonce( TestEmailSender::NONCE ),
            'to'     => 'not an address',
        );

        self::assertSame( 'Please enter a valid recipient email.', $this->call()['data']['message'] );
    }

    public function test_without_a_source_the_generic_test_is_sent(): void {
        $this->admin();
        $_POST = array(
            '_nonce' => wp_create_nonce( TestEmailSender::NONCE ),
            'to'     => 'me@example.test',
        );

        $payload = $this->call();

        self::assertTrue( $payload['success'] );
        self::assertSame( 'Test email queued / sent to me@example.test.', $payload['data']['message'] );
        self::assertCount( 1, $this->sent );
        self::assertStringContainsString( 'template test', (string) $this->sent[0]['subject'] );
    }

    public function test_a_source_that_is_not_a_core_template_gets_the_generic_test(): void {
        $this->admin();
        $_POST = array(
            '_nonce'     => wp_create_nonce( TestEmailSender::NONCE ),
            'to'         => 'me@example.test',
            'source_key' => 'plugin:woocommerce',
        );

        $this->call();

        self::assertStringContainsString( 'template test', (string) $this->sent[0]['subject'] );
    }

    public function test_a_core_template_source_sends_a_marked_preview(): void {
        $this->admin();
        $_POST = array(
            '_nonce'     => wp_create_nonce( TestEmailSender::NONCE ),
            'to'         => 'me@example.test',
            'source_key' => 'wp_core:password_reset',
        );

        $payload = $this->call();

        self::assertTrue( $payload['success'] );
        self::assertSame( 'Preview of wp_core:password_reset queued / sent to me@example.test.', $payload['data']['message'] );
        self::assertStringStartsWith( '[PREVIEW] ', (string) $this->sent[0]['subject'] );
    }

    public function test_a_refused_send_is_reported_for_both_kinds(): void {
        $this->admin();
        $_POST = array(
            '_nonce' => wp_create_nonce( TestEmailSender::NONCE ),
            'to'     => 'fail@example.test',
        );
        $generic = $this->call();

        $_POST['source_key'] = 'wp_core:password_reset';
        $preview             = $this->call();

        foreach ( array( $generic, $preview ) as $payload ) {
            self::assertFalse( $payload['success'] );
            self::assertStringContainsString( 'wp_mail() returned false', $payload['data']['message'] );
        }
    }

    private function admin(): void {
        wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
    }

    /**
     * Run the handler and decode what it answered.
     *
     * @return array{success:bool,data:mixed}
     */
    private function call(): array {
        $this->_last_response = '';
        try {
            $this->_handleAjax( TestEmailSender::ACTION );
        } catch ( WPAjaxDieContinueException | WPAjaxDieStopException $e ) {
            // wp_send_json_* ends through wp_die(), which the test case turns into an exception.
            unset( $e );
        }
        $decoded = json_decode( $this->_last_response, true );
        self::assertIsArray( $decoded, 'Expected JSON, got: ' . $this->_last_response );
        return $decoded;
    }
}
