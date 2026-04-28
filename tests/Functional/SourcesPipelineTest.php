<?php

declare(strict_types=1);

namespace TMQ\Tests\Functional;

use TotalMailQueue\Database\Schema;
use TotalMailQueue\Queue\MailInterceptor;
use TotalMailQueue\Sources\Detector;
use TotalMailQueue\Sources\Repository as SourcesRepository;

/**
 * End-to-end pipeline check for S2: a real wp_mail() call should land in
 * the queue table with the expected `source_key`, and the corresponding
 * row should be auto-registered in the sources catalog.
 *
 * @covers \TotalMailQueue\Sources\Detector
 * @covers \TotalMailQueue\Sources\Repository
 * @covers \TotalMailQueue\Queue\MailInterceptor::handle
 */
final class SourcesPipelineTest extends FunctionalTestCase {

    protected function setUp(): void {
        parent::setUp();
        $this->setPluginOptions( array( 'enabled' => '1' ) );
        // Hook the interceptor in this request — Plugin::boot() ran before
        // setPluginOptions persisted enabled=1, so the filter never registered.
        MailInterceptor::register();

        global $wpdb;
        $wpdb->query( "TRUNCATE TABLE `{$this->sourcesTable()}`" );
    }

    public function test_listener_marker_propagates_into_the_queue_row(): void {
        Detector::setCurrent( 'wp_core:password_reset', 'Password reset', 'WordPress Core' );

        $sent = wp_mail( 'user@example.test', 'Subject', 'Body' );
        self::assertTrue( $sent, 'wp_mail() returns true once MailInterceptor enqueues the row.' );

        global $wpdb;
        $row = $wpdb->get_row( "SELECT * FROM `{$this->queueTable()}` ORDER BY id DESC LIMIT 1", ARRAY_A );
        self::assertNotNull( $row );
        self::assertSame( 'wp_core:password_reset', $row['source_key'] );

        $catalog = SourcesRepository::findByKey( 'wp_core:password_reset' );
        self::assertNotNull( $catalog, 'Auto-registration must add the source to the catalog.' );
        self::assertSame( 'Password reset', $catalog['label'] );
        self::assertSame( 'WordPress Core', $catalog['group_label'] );
        self::assertSame( '1', (string) $catalog['total_count'], 'markSeen must bump total_count to 1.' );
    }

    public function test_backtrace_fallback_classifies_unmarked_emails(): void {
        // No marker set → MailInterceptor falls back to inferFromBacktrace,
        // which from a PHPUnit frame lands on `wp_core:unknown`.
        wp_mail( 'user@example.test', 'Backtrace test', 'Body' );

        global $wpdb;
        $row = $wpdb->get_row( "SELECT * FROM `{$this->queueTable()}` ORDER BY id DESC LIMIT 1", ARRAY_A );
        self::assertNotNull( $row );
        self::assertSame( 'wp_core:unknown', $row['source_key'] );

        self::assertNotNull( SourcesRepository::findByKey( 'wp_core:unknown' ) );
    }

    public function test_repeat_sends_bump_total_count_without_duplicating_the_catalog_row(): void {
        Detector::setCurrent( 'plugin:woocommerce', 'WooCommerce', 'Plugins' );
        wp_mail( 'a@example.test', 'one', 'body' );
        Detector::setCurrent( 'plugin:woocommerce', 'WooCommerce', 'Plugins' );
        wp_mail( 'b@example.test', 'two', 'body' );
        Detector::setCurrent( 'plugin:woocommerce', 'WooCommerce', 'Plugins' );
        wp_mail( 'c@example.test', 'three', 'body' );

        $catalog = SourcesRepository::findByKey( 'plugin:woocommerce' );
        self::assertNotNull( $catalog );
        self::assertSame( '3', (string) $catalog['total_count'] );
        self::assertSame( 1, SourcesRepository::count() );
    }

    private function sourcesTable(): string {
        return Schema::sourcesTable();
    }
}
