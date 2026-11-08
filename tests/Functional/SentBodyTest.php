<?php

declare(strict_types=1);

namespace TMQ\Tests\Functional;

use TotalMailQueue\Queue\MailSucceededHandler;
use TotalMailQueue\Queue\QueueRepository;
use TotalMailQueue\Queue\Tracker;
use TotalMailQueue\Retention\SentBody;

/**
 * "Message after sending" (#180): a sent row keeps its metadata and loses
 * its message, headers and attachment list, on both paths that mark a row
 * sent, and only when the setting is on.
 *
 * @covers \TotalMailQueue\Retention\SentBody
 * @covers \TotalMailQueue\Cron\BatchProcessor
 * @covers \TotalMailQueue\Queue\MailSucceededHandler
 */
final class SentBodyTest extends FunctionalTestCase {

    protected function setUp(): void {
        parent::setUp();
        reset_phpmailer_instance();
    }

    private function row( int $id ): array {
        return (array) QueueRepository::findById( $id );
    }

    private function queued(): int {
        return $this->insertQueueItem( array(
            'recipient' => wp_json_encode( 'ana@example.org' ),
            'subject'   => 'Report',
            'message'   => 'The body',
            'headers'   => wp_json_encode( array( 'Cc: bia@example.org' ) ),
        ) );
    }

    public function test_off_by_default_a_sent_row_keeps_its_message(): void {
        $this->setPluginOptions( array( 'enabled' => '1', 'queue_amount' => 5 ) );
        $id = $this->queued();

        \TotalMailQueue\Cron\BatchProcessor::run();

        $row = $this->row( $id );
        self::assertSame( 'sent', $row['status'] );
        self::assertSame( 'The body', $row['message'] );
        self::assertSame( '', $row['info'] );
    }

    public function test_on_a_row_sent_by_the_queue_keeps_only_its_metadata(): void {
        $this->setPluginOptions( array( 'enabled' => '1', 'queue_amount' => 5, 'log_drop_body' => '1' ) );
        $id = $this->queued();

        \TotalMailQueue\Cron\BatchProcessor::run();

        $row = $this->row( $id );
        self::assertSame( 'sent', $row['status'] );
        self::assertSame( '', $row['message'] );
        self::assertSame( '', $row['headers'] );
        self::assertSame( '', $row['attachments'] );
        self::assertSame( 'Report', $row['subject'] );
        self::assertSame( wp_json_encode( 'ana@example.org' ), $row['recipient'] );
        self::assertSame( 'Message deleted after sending, as the Log settings ask.', $row['info'] );
        self::assertStringContainsString( 'The body', tests_retrieve_phpmailer_instance()->get_sent()->body, 'The email itself went out whole.' );
    }

    public function test_on_a_row_sent_instantly_is_emptied_too(): void {
        $this->setPluginOptions( array( 'enabled' => '1', 'log_drop_body' => '1' ) );
        $id = $this->insertQueueItem( array( 'status' => 'instant', 'message' => 'The body' ) );
        Tracker::set( $id );

        MailSucceededHandler::handle();

        $row = $this->row( $id );
        self::assertSame( 'sent', $row['status'] );
        self::assertSame( '', $row['message'] );
    }

    public function test_a_failed_row_keeps_its_message_for_a_resend(): void {
        $this->setPluginOptions( array( 'enabled' => '1', 'log_drop_body' => '1' ) );
        $id = $this->insertQueueItem( array( 'status' => 'error', 'message' => 'The body' ) );

        // Only the two "sent" paths call it; a failed row never reaches them.
        \TotalMailQueue\Cron\BatchProcessor::run();

        self::assertSame( 'The body', $this->row( $id )['message'] );
    }

    public function test_the_setting_off_leaves_the_row_untouched(): void {
        $this->setPluginOptions( array( 'log_drop_body' => '0' ) );
        $id = $this->insertQueueItem( array( 'status' => 'sent', 'message' => 'The body' ) );

        self::assertFalse( SentBody::afterSent( $id ) );
        self::assertSame( 'The body', $this->row( $id )['message'] );
    }
}
