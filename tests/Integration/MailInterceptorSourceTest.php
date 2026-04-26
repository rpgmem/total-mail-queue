<?php

declare(strict_types=1);

namespace TMQ\Tests\Integration;

use Brain\Monkey\Functions;
use TotalMailQueue\Queue\MailInterceptor;
use TotalMailQueue\Sources\Detector;

/**
 * Verifies the source-tracking integration on top of MailInterceptor: the
 * `source_key` column is populated, the source is registered in the
 * sources catalog, and the catalog is bumped (`markSeen`) on every send.
 *
 * @covers \TotalMailQueue\Queue\MailInterceptor::handle
 * @covers \TotalMailQueue\Sources\Detector::consume
 */
final class MailInterceptorSourceTest extends IntegrationTestCase {

    protected function setUp(): void {
        parent::setUp();
        Functions\when( 'apply_filters' )->alias( static function ( string $tag, mixed $value, mixed ...$rest ): mixed {
            return $value;
        } );
        Functions\when( 'get_bloginfo' )->justReturn( 'UTF-8' );
        Functions\when( 'sanitize_email' )->returnArg();
    }

    public function test_uses_marker_set_by_a_primary_listener_when_present(): void {
        Detector::setCurrent( 'wp_core:password_reset', 'Password reset', 'WordPress Core' );
        // First insert call → QueueRepository (the queued message); subsequent
        // insert call → Sources\Repository::register inserting the catalog row.
        $this->wpdb->will_return( 'insert', 1 );
        $this->wpdb->will_return( 'get_row', null ); // Sources\Repository::findByKey returns null → triggers register.
        $this->wpdb->will_return( 'insert', 7 );

        MailInterceptor::handle( null, $this->basicAtts() );

        $inserts = $this->wpdb->callsTo( 'insert' );
        self::assertCount( 2, $inserts, 'Expected one queue insert + one sources-catalog insert.' );

        // Queue row must carry the source_key.
        self::assertSame( 'wp_total_mail_queue', $inserts[0]['args'][0] );
        self::assertSame( 'wp_core:password_reset', $inserts[0]['args'][1]['source_key'] );

        // Catalog row must carry the same key + the listener-supplied label/group.
        self::assertSame( 'wp_total_mail_queue_sources', $inserts[1]['args'][0] );
        self::assertSame( 'wp_core:password_reset', $inserts[1]['args'][1]['source_key'] );
        self::assertSame( 'Password reset', $inserts[1]['args'][1]['label'] );
        self::assertSame( 'WordPress Core', $inserts[1]['args'][1]['group_label'] );

        // markSeen issues an UPDATE via $wpdb->query.
        $query = $this->wpdb->call( 'query' );
        self::assertNotNull( $query );
        self::assertStringContainsString( '`total_count` = `total_count` + 1', $query['args'][0] );
    }

    public function test_consume_clears_state_so_the_next_call_uses_the_fallback(): void {
        Detector::setCurrent( 'plugin:woocommerce', 'WooCommerce', 'Plugins' );
        // First mail consumes the marker.
        $this->wpdb->will_return( 'insert', 1 );
        $this->wpdb->will_return( 'get_row', null );
        $this->wpdb->will_return( 'insert', 1 );

        MailInterceptor::handle( null, $this->basicAtts() );

        $first_queue_insert = $this->wpdb->callsTo( 'insert' )[0];
        self::assertSame( 'plugin:woocommerce', $first_queue_insert['args'][1]['source_key'] );

        // Reset the recorded calls and DB seeds for the second invocation.
        $this->wpdb->calls = array();
        $this->wpdb->will_return( 'insert', 2 );
        $this->wpdb->will_return( 'get_row', null );
        $this->wpdb->will_return( 'insert', 1 );

        MailInterceptor::handle( null, $this->basicAtts() );

        $second_queue_insert = $this->wpdb->callsTo( 'insert' )[0];
        // No marker was set this round, so the source must come from the
        // backtrace fallback (which from PHPUnit's frames lands on `wp_core:unknown`).
        self::assertSame( 'wp_core:unknown', $second_queue_insert['args'][1]['source_key'] );
    }

    public function test_does_not_register_or_mark_when_queue_insert_fails(): void {
        // Insert fails → no follow-up DB activity for the catalog.
        $this->wpdb->will_return( 'insert', false );

        $result = MailInterceptor::handle( null, $this->basicAtts() );

        self::assertFalse( $result );
        $inserts = $this->wpdb->callsTo( 'insert' );
        self::assertCount( 1, $inserts, 'A failed queue insert must not trigger a catalog insert.' );
        self::assertNull( $this->wpdb->call( 'query' ), 'markSeen must be skipped when the queue insert failed.' );
    }

    public function test_skips_source_resolution_entirely_when_another_filter_short_circuited(): void {
        Detector::setCurrent( 'plugin:bogus', 'Bogus', 'Plugins' );

        $result = MailInterceptor::handle( true, $this->basicAtts() );

        self::assertTrue( $result );
        self::assertSame( array(), $this->wpdb->calls, 'Short-circuit must bail before any DB hit.' );
        // The marker must still be intact for the next "real" mail.
        self::assertSame( array( 'key' => 'plugin:bogus', 'label' => 'Bogus', 'group' => 'Plugins' ), Detector::consume() );
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
