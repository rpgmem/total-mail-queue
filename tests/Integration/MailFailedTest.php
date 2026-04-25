<?php

declare(strict_types=1);

namespace TMQ\Tests\Integration;

/**
 * @covers ::wp_tmq_mail_failed
 */
final class MailFailedTest extends IntegrationTestCase {

    public function test_does_nothing_when_mail_id_is_zero(): void {
        $GLOBALS['wp_tmq_mailid'] = 0;

        wp_tmq_mail_failed( $this->makeError( 'unused' ) );

        self::assertSame( array(), $this->wpdb->calls );
    }

    public function test_marks_email_as_error_immediately_when_retries_disabled(): void {
        $GLOBALS['wp_tmq_mailid'] = 17;
        $GLOBALS['wp_tmq_options']['max_retries'] = '0';
        $this->wpdb->will_return( 'get_row', array( 'retry_count' => 0, 'status' => 'queue' ) );
        $this->wpdb->will_return( 'update', 1 );

        wp_tmq_mail_failed( $this->makeError( 'SMTP connect failed' ) );

        $update = $this->wpdb->call( 'update' );
        self::assertNotNull( $update );
        self::assertSame( 'wp_total_mail_queue', $update['args'][0] );
        self::assertSame( 'error', $update['args'][1]['status'] );
        self::assertStringContainsString( 'SMTP connect failed', $update['args'][1]['info'] );
        self::assertSame( array( 'id' => 17 ), $update['args'][2] );
    }

    public function test_increments_retry_counter_and_keeps_status_queue_below_limit(): void {
        $GLOBALS['wp_tmq_mailid'] = 17;
        $GLOBALS['wp_tmq_options']['max_retries'] = '3';
        $this->wpdb->will_return( 'get_row', array( 'retry_count' => 1, 'status' => 'queue' ) );
        $this->wpdb->will_return( 'update', 1 );

        wp_tmq_mail_failed( $this->makeError( 'transient error' ) );

        $update = $this->wpdb->call( 'update' );
        self::assertSame( 'queue', $update['args'][1]['status'], 'Status must return to queue so the email is retried.' );
        self::assertSame( 2, $update['args'][1]['retry_count'] );
        self::assertStringContainsString( 'Retry 2/3', $update['args'][1]['info'] );
        self::assertStringContainsString( 'transient error', $update['args'][1]['info'] );
    }

    public function test_marks_email_as_error_after_exceeding_retry_limit(): void {
        $GLOBALS['wp_tmq_mailid'] = 17;
        $GLOBALS['wp_tmq_options']['max_retries'] = '3';
        $this->wpdb->will_return( 'get_row', array( 'retry_count' => 3, 'status' => 'queue' ) );
        $this->wpdb->will_return( 'update', 1 );

        wp_tmq_mail_failed( $this->makeError( 'final error' ) );

        $update = $this->wpdb->call( 'update' );
        self::assertSame( 'error', $update['args'][1]['status'] );
        self::assertStringContainsString( 'Failed after 4 attempt(s)', $update['args'][1]['info'] );
        self::assertStringContainsString( 'final error', $update['args'][1]['info'] );
    }

    public function test_uses_unknown_label_when_wp_error_has_no_message(): void {
        $GLOBALS['wp_tmq_mailid'] = 5;
        $GLOBALS['wp_tmq_options']['max_retries'] = '0';
        $this->wpdb->will_return( 'get_row', array( 'retry_count' => 0, 'status' => 'queue' ) );
        $this->wpdb->will_return( 'update', 1 );

        $error = new \stdClass();
        $error->errors = array(); // No wp_mail_failed entry

        wp_tmq_mail_failed( $error );

        $update = $this->wpdb->call( 'update' );
        self::assertSame( 'error', $update['args'][1]['status'] );
        self::assertStringContainsString( 'Unknown', $update['args'][1]['info'] );
    }

    /**
     * Build the WP_Error-shaped object that wp_tmq_mail_failed() inspects.
     */
    private function makeError( string $message ): object {
        $err = new \stdClass();
        $err->errors = array( 'wp_mail_failed' => array( $message ) );
        return $err;
    }
}
