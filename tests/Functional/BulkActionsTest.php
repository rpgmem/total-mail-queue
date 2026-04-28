<?php

declare(strict_types=1);

namespace TMQ\Tests\Functional;

use TotalMailQueue\Admin\Tables\LogTable;

class BulkRedirectIntercepted extends \RuntimeException {}

/**
 * @covers \TotalMailQueue\Admin\Tables\LogTable::process_bulk_action
 */
final class BulkActionsTest extends FunctionalTestCase {

    protected function setUp(): void {
        parent::setUp();
        // Bulk actions only work for an admin user.
        $admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
        wp_set_current_user( $admin );
    }

    public function test_delete_action_removes_selected_rows(): void {
        $keep   = $this->insertQueueItem( array( 'status' => 'sent', 'subject' => 'keep' ) );
        $remove = $this->insertQueueItem( array( 'status' => 'sent', 'subject' => 'remove' ) );

        $this->dispatchBulkAction( 'delete', array( $remove ) );

        global $wpdb;
        $survivors = $wpdb->get_col( "SELECT subject FROM `{$this->queueTable()}`" );
        self::assertSame( array( 'keep' ), $survivors );
        unset( $keep );
    }

    public function test_resend_requeues_an_errored_email_and_removes_the_original_log_entry(): void {
        $error_id = $this->insertQueueItem( array( 'status' => 'error', 'subject' => 'broken send' ) );

        $this->dispatchBulkAction( 'resend', array( $error_id ) );

        global $wpdb;
        $rows = $wpdb->get_results( "SELECT * FROM `{$this->queueTable()}`", ARRAY_A );
        self::assertCount( 1, $rows, 'Original error row must be removed and replaced by a single new queue entry.' );
        self::assertSame( 'queue', $rows[0]['status'] );
        self::assertNotSame( (string) $error_id, $rows[0]['id'] );
    }

    public function test_resend_skips_emails_already_sent(): void {
        $sent_id = $this->insertQueueItem( array( 'status' => 'sent', 'subject' => 'already delivered' ) );

        $this->dispatchBulkAction( 'resend', array( $sent_id ) );

        global $wpdb;
        $rows = $wpdb->get_results( "SELECT * FROM `{$this->queueTable()}`", ARRAY_A );
        self::assertCount( 1, $rows, 'A sent email must not be cloned back into the queue.' );
        self::assertSame( 'sent', $rows[0]['status'] );
    }

    public function test_force_resend_only_acts_on_errored_emails(): void {
        $error_id = $this->insertQueueItem( array( 'status' => 'error', 'retry_count' => 5, 'subject' => 'force this' ) );
        $sent_id  = $this->insertQueueItem( array( 'status' => 'sent', 'subject' => 'leave alone' ) );

        $this->dispatchBulkAction( 'force_resend', array( $error_id, $sent_id ) );

        global $wpdb;
        $rows = $wpdb->get_results( "SELECT * FROM `{$this->queueTable()}` ORDER BY id", ARRAY_A );

        // The sent row stays; the errored one is replaced by a fresh queue row with retry_count=0.
        $statuses = array_column( $rows, 'status' );
        self::assertContains( 'sent', $statuses );
        self::assertContains( 'queue', $statuses );

        foreach ( $rows as $row ) {
            if ( $row['status'] === 'queue' ) {
                self::assertSame( '0', $row['retry_count'], 'Force resend must reset the retry counter.' );
                self::assertStringContainsString( 'Force resent', (string) $row['info'] );
            }
        }
    }

    /**
     * Build a LogTable, configure $_POST/$_REQUEST as if the admin
     * UI had submitted the bulk form, and trigger process_bulk_action().
     *
     * The handler issues wp_safe_redirect() + exit on the success path. We
     * intercept the redirect via the `wp_redirect` filter and throw, so the
     * underlying exit never runs and the test thread keeps going.
     *
     * @param array<int,int> $ids
     */
    private function dispatchBulkAction( string $action, array $ids ): void {
        $logtable = new LogTable( LogTable::MODE_LOG, array( 'plural' => 'tmq_items' ) );

        $_POST['_wpnonce']     = wp_create_nonce( 'bulk-tmq_items' );
        $_REQUEST['_wpnonce']  = $_POST['_wpnonce'];
        $_POST['action']       = $action;
        $_REQUEST['action']    = $action;
        $_POST['id']           = array_map( 'strval', $ids );
        $_REQUEST['id']        = $_POST['id'];

        $intercept = static function ( $url ) {
            throw new BulkRedirectIntercepted( (string) $url );
        };
        add_filter( 'wp_redirect', $intercept, 1 );

        // The handler renders admin notices via echo; capture and discard them
        // so PHPUnit's strict-output mode is happy.
        ob_start();
        try {
            $logtable->process_bulk_action();
        } catch ( BulkRedirectIntercepted $e ) {
            // expected on the success-redirect path; DB side effects are persisted before the redirect is attempted.
        } catch ( \WPDieException $e ) {
            // some paths bail via wp_die() — same outcome.
        } finally {
            ob_end_clean();
            remove_filter( 'wp_redirect', $intercept, 1 );
        }
    }

    protected function tearDown(): void {
        unset( $_POST['_wpnonce'], $_POST['action'], $_POST['id'] );
        unset( $_REQUEST['_wpnonce'], $_REQUEST['action'], $_REQUEST['id'] );
        parent::tearDown();
    }
}
