<?php

declare(strict_types=1);

namespace TMQ\Tests\Functional;

use TotalMailQueue\Admin\Tables\LogTable;
use TotalMailQueue\Queue\ListFilter;
use TotalMailQueue\Queue\QueueRepository;

/**
 * Search and date range on the Log and Retention lists (#83).
 *
 * @covers \TotalMailQueue\Queue\ListFilter
 * @covers \TotalMailQueue\Queue\QueueRepository
 * @covers \TotalMailQueue\Admin\Tables\LogTable
 */
final class LogSearchFilterTest extends FunctionalTestCase {

    protected function tearDown(): void {
        foreach ( ListFilter::KEYS as $key ) {
            unset( $_REQUEST[ $key ], $_GET[ $key ] );
        }
        parent::tearDown();
    }

    public function test_search_matches_recipient_and_subject_but_not_the_body(): void {
        $this->log( 'invoice@example.test', 'Welcome', 'Body' );
        $this->log( 'someone@example.test', 'Your invoice', 'Body' );
        $this->log( 'other@example.test', 'Welcome', 'The invoice is attached' );

        $subjects = $this->subjects( new ListFilter( '', '', 'invoice' ) );
        sort( $subjects );
        self::assertSame( array( 'Welcome', 'Your invoice' ), $subjects );
        self::assertSame( 2, QueueRepository::logCount( new ListFilter( '', '', 'invoice' ) ) );
    }

    public function test_search_finds_an_accented_recipient_stored_as_escaped_json(): void {
        $this->log( 'João Silva <joao@example.test>', 'Hello', 'Body' );

        self::assertSame( array( 'Hello' ), $this->subjects( new ListFilter( '', '', 'João' ) ) );
    }

    public function test_like_wildcards_in_the_search_are_literal(): void {
        $this->log( 'a@example.test', '100% done', 'Body' );
        $this->log( 'b@example.test', 'Nothing here', 'Body' );

        self::assertSame( array( '100% done' ), $this->subjects( new ListFilter( '', '', '100%' ) ) );
        self::assertSame( array(), $this->subjects( new ListFilter( '', '', '_' ) ) );
    }

    public function test_the_date_range_is_inclusive_and_accepts_a_reversed_range(): void {
        $this->log( 'a@example.test', 'Before', 'B', '2026-03-02 23:59:59' );
        $this->log( 'a@example.test', 'First day', 'B', '2026-03-03 00:00:00' );
        $this->log( 'a@example.test', 'Last day', 'B', '2026-03-05 23:59:59' );
        $this->log( 'a@example.test', 'After', 'B', '2026-03-06 00:00:00' );

        self::assertSame( array( 'Last day', 'First day' ), $this->subjects( new ListFilter( '', '', '', '2026-03-03', '2026-03-05' ) ) );
        self::assertSame( array( 'Last day', 'First day' ), $this->subjects( new ListFilter( '', '', '', '2026-03-05', '2026-03-03' ) ) );
        self::assertSame( array( 'After', 'Last day' ), $this->subjects( new ListFilter( '', '', '', '2026-03-04' ) ) );
    }

    public function test_an_invalid_date_is_ignored(): void {
        $filter = new ListFilter( '', '', '', '2026-02-30', 'yesterday' );

        self::assertSame( '', $filter->from );
        self::assertSame( '', $filter->to );
        self::assertFalse( $filter->isActive() );
    }

    public function test_search_dates_status_and_source_combine(): void {
        $this->log( 'a@example.test', 'Invoice A', 'B', '2026-03-03 10:00:00', 'sent', 'plugin:shop' );
        $this->log( 'a@example.test', 'Invoice B', 'B', '2026-03-03 10:00:00', 'error', 'plugin:shop' );
        $this->log( 'a@example.test', 'Invoice C', 'B', '2026-03-03 10:00:00', 'sent', 'plugin:crm' );
        $this->log( 'a@example.test', 'Invoice D', 'B', '2026-04-03 10:00:00', 'sent', 'plugin:shop' );

        self::assertSame( array( 'Invoice A' ), $this->subjects( new ListFilter( 'sent', 'plugin:shop', 'invoice', '2026-03-01', '2026-03-31' ) ) );
    }

    public function test_an_unknown_status_falls_back_to_the_whole_log(): void {
        $this->log( 'a@example.test', 'Sent one', 'B' );
        $this->insertQueueItem( array( 'status' => 'queue', 'subject' => 'Still waiting' ) );

        self::assertSame( array( 'Sent one' ), $this->subjects( new ListFilter( 'queue' ) ) );
    }

    public function test_the_retention_queue_is_searched_and_ignores_the_status_filter(): void {
        $this->insertQueueItem( array( 'status' => 'queue', 'subject' => 'Password reset', 'timestamp' => '2026-03-03 10:00:00' ) );
        $this->insertQueueItem( array( 'status' => 'queue', 'subject' => 'Newsletter', 'timestamp' => '2026-03-03 10:00:00' ) );
        $this->log( 'a@example.test', 'Password reset', 'B', '2026-03-03 10:00:00' );

        $filter = new ListFilter( 'sent', '', 'password', '2026-03-03', '2026-03-03' );
        self::assertSame( 1, QueueRepository::queueCount( $filter ) );
        self::assertSame( array( 'Password reset' ), array_column( QueueRepository::queuePage( $filter, 50, 0 ), 'subject' ) );
    }

    public function test_the_filters_are_read_from_the_request(): void {
        $_REQUEST['status_filter'] = 'error';
        $_REQUEST['source_filter'] = 'plugin:shop';
        $_REQUEST['s']             = '  invoice ';
        $_REQUEST['date_from']     = '2026-03-01';
        $_REQUEST['date_to']       = '2026-03-31';

        $filter = ListFilter::fromRequest();

        self::assertSame(
            array( 'status_filter' => 'error', 'source_filter' => 'plugin:shop', 's' => 'invoice', 'date_from' => '2026-03-01', 'date_to' => '2026-03-31' ),
            $filter->queryArgs()
        );
    }

    public function test_the_list_renders_the_filters_in_the_get_form_and_keeps_them_when_paging(): void {
        wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
        set_current_screen( 'toplevel_page_wp_tmq_mail_queue' );
        for ( $i = 0; $i < 60; $i++ ) {
            $this->log( 'a@example.test', 'Invoice ' . $i, 'B' );
        }
        $_REQUEST['s'] = 'invoice';
        $_GET['s']     = 'invoice';
        $_SERVER['REQUEST_URI'] = '/wp-admin/admin.php?page=wp_tmq_mail_queue-tab-log&s=invoice';

        $table = new LogTable( LogTable::MODE_LOG, array( 'plural' => 'tmq_items' ) );
        $table->prepare_items();
        ob_start();
        $table->display();
        $html = (string) ob_get_clean();

        self::assertStringContainsString( 'name="s" id="tmq-list-search" value="invoice" placeholder', $html );
        self::assertMatchesRegularExpression( '/<input type="date" name="date_from"[^>]*form="' . LogTable::FILTER_FORM . '"/', $html, 'The fields belong to the GET form, not the bulk POST form.' );
        self::assertMatchesRegularExpression( '/<select name="status_filter"[^>]*form="' . LogTable::FILTER_FORM . '"/', $html );
        self::assertMatchesRegularExpression( '/name="filter_action"[^>]*form="' . LogTable::FILTER_FORM . '"/', $html );
        $links = (string) preg_replace( '/&#038;|&amp;/', '&', $html );
        self::assertMatchesRegularExpression( "/href='[^']*s=invoice[^']*paged=2'/", $links, 'The next-page link must keep the search.' );
        self::assertStringContainsString( 'Clear filters', $html );
    }

    /**
     * Insert a log row.
     */
    private function log( string $to, string $subject, string $message, string $timestamp = '', string $status = 'sent', string $source = '' ): void {
        $this->insertQueueItem(
            array(
                'recipient'  => (string) wp_json_encode( $to ),
                'subject'    => $subject,
                'message'    => $message,
                'status'     => $status,
                'source_key' => $source,
                'timestamp'  => '' !== $timestamp ? $timestamp : current_time( 'mysql', false ),
            )
        );
    }

    /**
     * Subjects of the Log page the filter selects, newest first.
     *
     * @return list<string>
     */
    private function subjects( ListFilter $filter ): array {
        return array_values( array_column( QueueRepository::logPage( $filter, 50, 0 ), 'subject' ) );
    }
}
