<?php

declare(strict_types=1);

namespace TMQ\Tests\Functional;

/**
 * Verifies the cleanup paths inside wp_tmq_search_mail_from_queue() that
 * trim the log table by age (clear_queue) and by hard cap (log_max_records).
 *
 * @covers ::wp_tmq_search_mail_from_queue
 */
final class RetentionCleanupTest extends FunctionalTestCase {

    public function test_clear_queue_deletes_log_entries_older_than_retention_window(): void {
        // 1 day of retention = 24 hours.
        $this->setPluginOptions( array( 'enabled' => '1', 'clear_queue' => 1, 'queue_amount' => 1 ) );

        // One fresh sent + one stale (older than 24h).
        $this->insertQueueItem( array(
            'status'    => 'sent',
            'timestamp' => current_time( 'mysql', false ),
        ) );
        $this->insertQueueItem( array(
            'status'    => 'sent',
            'timestamp' => gmdate( 'Y-m-d H:i:s', strtotime( '-3 days' ) ),
        ) );

        wp_tmq_search_mail_from_queue();

        global $wpdb;
        $rows = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$this->queueTable()}` WHERE `status` = 'sent'" );
        self::assertSame( 1, $rows, 'Only the recent sent log entry must survive cleanup.' );
    }

    public function test_clear_queue_does_not_delete_pending_queue_items(): void {
        // Even if a queue item is "old" (timestamp), it must NOT be deleted —
        // the retention sweep targets statuses other than queue/high.
        $this->setPluginOptions( array( 'enabled' => '1', 'clear_queue' => 1, 'queue_amount' => 0 ) );

        $this->insertQueueItem( array(
            'status'    => 'queue',
            'timestamp' => gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) ),
        ) );

        wp_tmq_search_mail_from_queue();

        global $wpdb;
        $rows = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$this->queueTable()}` WHERE `status` = 'queue'" );
        self::assertSame( 1, $rows, 'Pending queue rows must never be removed by retention cleanup.' );
    }

    public function test_log_max_records_deletes_oldest_entries_when_over_cap(): void {
        $this->setPluginOptions( array(
            'enabled'         => '1',
            'clear_queue'     => 365,
            'log_max_records' => 2,
            'queue_amount'    => 0,
        ) );

        // Three sent entries at decreasing ages.
        $this->insertQueueItem( array( 'status' => 'sent', 'timestamp' => gmdate( 'Y-m-d H:i:s', strtotime( '-3 hours' ) ), 'subject' => 'oldest' ) );
        $this->insertQueueItem( array( 'status' => 'sent', 'timestamp' => gmdate( 'Y-m-d H:i:s', strtotime( '-2 hours' ) ), 'subject' => 'middle' ) );
        $this->insertQueueItem( array( 'status' => 'sent', 'timestamp' => gmdate( 'Y-m-d H:i:s', strtotime( '-1 hour' ) ),  'subject' => 'newest' ) );

        wp_tmq_search_mail_from_queue();

        global $wpdb;
        $surviving = $wpdb->get_col( "SELECT subject FROM `{$this->queueTable()}` ORDER BY timestamp DESC" );
        self::assertCount( 2, $surviving );
        self::assertSame( array( 'newest', 'middle' ), $surviving );
    }

    public function test_log_max_records_zero_means_no_record_cap(): void {
        $this->setPluginOptions( array(
            'enabled'         => '1',
            'clear_queue'     => 365,
            'log_max_records' => 0,
            'queue_amount'    => 0,
        ) );

        for ( $i = 0; $i < 5; $i++ ) {
            $this->insertQueueItem( array(
                'status'    => 'sent',
                'timestamp' => gmdate( 'Y-m-d H:i:s', strtotime( "-$i hour" ) ),
            ) );
        }

        wp_tmq_search_mail_from_queue();

        global $wpdb;
        $rows = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$this->queueTable()}`" );
        self::assertSame( 5, $rows, 'log_max_records=0 must keep every row.' );
    }
}
