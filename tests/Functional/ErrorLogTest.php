<?php

declare(strict_types=1);

namespace TMQ\Tests\Functional;

use TotalMailQueue\Queue\MailFailedHandler;
use TotalMailQueue\Queue\QueueRepository;
use TotalMailQueue\Queue\Tracker;
use TotalMailQueue\Support\RuntimeState;

/**
 * Persistent per-row error log: failures accumulate, success clears it.
 *
 * @covers \TotalMailQueue\Queue\QueueRepository::appendErrorLog
 * @covers \TotalMailQueue\Queue\MailFailedHandler::handle
 * @covers \TotalMailQueue\Cron\BatchProcessor::run
 */
final class ErrorLogTest extends FunctionalTestCase {

    protected function setUp(): void {
        parent::setUp();
        reset_phpmailer_instance();
    }

    private function errorLog( int $id ): string {
        global $wpdb;
        return (string) $wpdb->get_var( $wpdb->prepare( "SELECT error_log FROM `{$this->queueTable()}` WHERE id = %d", $id ) );
    }

    private function makeError( string $message ): object {
        $err         = new \stdClass();
        $err->errors = array( 'wp_mail_failed' => array( $message ) );
        return $err;
    }

    public function test_append_accumulates_and_reader_returns_it(): void {
        $id = $this->insertQueueItem();

        QueueRepository::appendErrorLog( $id, 'first' );
        QueueRepository::appendErrorLog( $id, 'second' );

        $log = QueueRepository::errorLogFor( $id );
        self::assertStringContainsString( 'first', $log );
        self::assertStringContainsString( 'second', $log );
        self::assertTrue( strpos( $log, 'first' ) < strpos( $log, 'second' ), 'Entries must keep insertion order.' );
    }

    public function test_failed_send_records_full_detail_in_error_log(): void {
        $this->setPluginOptions( array( 'enabled' => '1', 'max_retries' => '0' ) );
        $id = $this->insertQueueItem();

        Tracker::set( $id );
        MailFailedHandler::handle( $this->makeError( 'SMTP connect() failed — could not authenticate' ) );

        $log = $this->errorLog( $id );
        self::assertStringContainsString( 'Attempt #1', $log );
        self::assertStringContainsString( 'could not authenticate', $log );
    }

    public function test_error_log_is_cleared_once_the_email_sends(): void {
        $this->setPluginOptions( array( 'enabled' => '1', 'queue_amount' => 5 ) );
        $id = $this->insertQueueItem( array( 'error_log' => "[2026-01-01] Attempt #1 failed: boom\n" ) );

        RuntimeState::reset();
        \TotalMailQueue\Cron\BatchProcessor::run();

        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT status, error_log FROM `{$this->queueTable()}` WHERE id = %d", $id ), ARRAY_A );
        self::assertSame( 'sent', $row['status'] );
        self::assertSame( '', (string) $row['error_log'], 'A successful send must clear the prior error history.' );
    }
}
