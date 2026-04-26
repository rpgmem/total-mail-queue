<?php

declare(strict_types=1);

namespace TMQ\Tests\Integration;

use Brain\Monkey\Functions;

/**
 * @covers \TotalMailQueue\Queue\MailInterceptor::handle
 */
final class PreWpMailTest extends IntegrationTestCase {

    protected function setUp(): void {
        parent::setUp();
        // wp_mail_content_type/charset/from/from_name filters: pass through default value.
        Functions\when( 'apply_filters' )->alias( static function ( string $tag, mixed $value, mixed ...$rest ): mixed {
            return $value;
        } );
        Functions\when( 'get_bloginfo' )->justReturn( 'UTF-8' );
        Functions\when( 'sanitize_email' )->returnArg();
    }

    public function test_passes_through_when_another_filter_already_returned_a_value(): void {
        $result = \TotalMailQueue\Queue\MailInterceptor::handle( true, $this->basicAtts() );

        self::assertTrue( $result );
        self::assertSame( array(), $this->wpdb->calls, 'No DB activity should happen when another filter handled the mail.' );
    }

    public function test_inserts_mail_into_queue_table_with_default_status(): void {
        $this->wpdb->will_return( 'insert', 1 );

        $result = \TotalMailQueue\Queue\MailInterceptor::handle( null, $this->basicAtts() );

        self::assertTrue( $result );
        $insert = $this->wpdb->call( 'insert' );
        self::assertNotNull( $insert );
        self::assertSame( 'wp_total_mail_queue', $insert['args'][0] );
        $data = $insert['args'][1];
        self::assertSame( 'queue', $data['status'] );
        self::assertSame( 'Subject', $data['subject'] );
        self::assertSame( 'Body', $data['message'] );
    }

    public function test_returns_false_when_wpdb_insert_fails(): void {
        $this->wpdb->will_return( 'insert', false );

        $result = \TotalMailQueue\Queue\MailInterceptor::handle( null, $this->basicAtts() );

        self::assertFalse( $result );
    }

    public function test_high_priority_header_is_stripped_and_status_becomes_high(): void {
        $this->wpdb->will_return( 'insert', 1 );

        $atts = $this->basicAtts();
        $atts['headers'] = array( 'X-Mail-Queue-Prio: High' );

        \TotalMailQueue\Queue\MailInterceptor::handle( null, $atts );

        $data = $this->wpdb->call( 'insert' )['args'][1];
        self::assertSame( 'high', $data['status'] );
        self::assertStringNotContainsString( 'X-Mail-Queue-Prio', $data['headers'] );
    }

    public function test_instant_priority_returns_null_to_let_wp_mail_proceed(): void {
        $this->wpdb->will_return( 'insert', 1 );

        $atts = $this->basicAtts();
        $atts['headers'] = array( 'X-Mail-Queue-Prio: Instant' );

        $result = \TotalMailQueue\Queue\MailInterceptor::handle( null, $atts );

        self::assertNull( $result, 'Instant emails must let wp_mail() continue with the actual send.' );
        $data = $this->wpdb->call( 'insert' )['args'][1];
        self::assertSame( 'instant', $data['status'] );
        // The queue insert is the first one MockWpdb sees; its insert_id is 1.
        // (Subsequent inserts from the source-catalog auto-registration bump
        // $wpdb->insert_id, but Tracker captured the queue value.)
        self::assertSame( 1, \TotalMailQueue\Queue\Tracker::get() );
    }

    public function test_block_mode_keeps_instant_emails_in_queue_instead_of_sending(): void {
        $this->options['enabled'] = '2';
        $this->wpdb->will_return( 'insert', 1 );

        $atts = $this->basicAtts();
        $atts['headers'] = array( 'X-Mail-Queue-Prio: Instant' );

        $result = \TotalMailQueue\Queue\MailInterceptor::handle( null, $atts );

        self::assertTrue( $result, 'Block mode must absorb the email — return true so the caller sees a "successful" enqueue.' );
        $data = $this->wpdb->call( 'insert' )['args'][1];
        self::assertSame( 'queue', $data['status'], 'In block mode even instant emails must be retained.' );
    }

    public function test_string_headers_are_exploded_into_array(): void {
        $this->wpdb->will_return( 'insert', 1 );

        $atts = $this->basicAtts();
        $atts['headers'] = "X-Custom: one\r\nX-Custom: two";

        \TotalMailQueue\Queue\MailInterceptor::handle( null, $atts );

        $stored_headers = json_decode( $this->wpdb->call( 'insert' )['args'][1]['headers'], true );
        self::assertContains( 'X-Custom: one', $stored_headers );
        self::assertContains( 'X-Custom: two', $stored_headers );
    }

    public function test_adds_content_type_header_when_filter_supplies_one(): void {
        $this->wpdb->will_return( 'insert', 1 );

        Functions\when( 'apply_filters' )->alias( static function ( string $tag, mixed $value, mixed ...$rest ): mixed {
            if ( $tag === 'wp_mail_content_type' ) {
                return 'text/html';
            }
            return $value;
        } );

        \TotalMailQueue\Queue\MailInterceptor::handle( null, $this->basicAtts() );

        $stored_headers = json_decode( $this->wpdb->call( 'insert' )['args'][1]['headers'], true );
        $found = false;
        foreach ( $stored_headers as $h ) {
            if ( stripos( $h, 'Content-Type: text/html' ) === 0 ) {
                $found = true;
                break;
            }
        }
        self::assertTrue( $found, 'Content-Type header from wp_mail_content_type filter must be persisted.' );
    }

    public function test_skips_content_type_and_from_filters_for_instant_emails(): void {
        // For Instant emails, wp_mail() runs normally and applies its own filters,
        // so the plugin must not duplicate them in the stored headers.
        $this->wpdb->will_return( 'insert', 1 );
        Functions\when( 'apply_filters' )->alias( static function ( string $tag, mixed $value, mixed ...$rest ): mixed {
            if ( $tag === 'wp_mail_content_type' ) {
                return 'text/html';
            }
            if ( $tag === 'wp_mail_from' ) {
                return 'noreply@example.test';
            }
            return $value;
        } );

        $atts = $this->basicAtts();
        $atts['headers'] = array( 'X-Mail-Queue-Prio: Instant' );
        \TotalMailQueue\Queue\MailInterceptor::handle( null, $atts );

        $insert_data = $this->wpdb->call( 'insert' )['args'][1];
        // After stripping the Instant header the headers array is empty, so the
        // plugin doesn't even persist a `headers` key — that's the correct
        // behavior because wp_mail() will build its own headers when sending.
        if ( ! isset( $insert_data['headers'] ) ) {
            self::assertSame( 'instant', $insert_data['status'] );
            return;
        }
        $stored_headers = json_decode( $insert_data['headers'], true );
        foreach ( (array) $stored_headers as $h ) {
            self::assertStringNotContainsString( 'Content-Type:', $h );
            self::assertStringNotContainsString( 'From:', $h );
        }
    }

    /**
     * @return array{to:string,subject:string,message:string,headers:array<int,string>,attachments:array<int,string>}
     */
    private function basicAtts(): array {
        return array(
            'to'          => 'user@example.test',
            'subject'     => 'Subject',
            'message'     => 'Body',
            'headers'     => array(),
            'attachments' => array(),
        );
    }
}
