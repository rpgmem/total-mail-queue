<?php

declare(strict_types=1);

namespace TMQ\Tests\Functional;

use TotalMailQueue\Cron\Diagnostics;

/**
 * The "last individual send" stamp that drives the stalled-queue notice.
 *
 * @covers \TotalMailQueue\Cron\Diagnostics::recordSend
 * @covers \TotalMailQueue\Cron\Diagnostics::lastSendTimestamp
 * @covers \TotalMailQueue\Cron\BatchProcessor::run
 */
final class LastSendTrackingTest extends FunctionalTestCase {

    protected function setUp(): void {
        parent::setUp();
        reset_phpmailer_instance();
        delete_option( Diagnostics::LAST_SEND_OPTION );
    }

    public function test_last_send_is_zero_before_anything_is_sent(): void {
        self::assertSame( 0, Diagnostics::lastSendTimestamp() );
    }

    public function test_record_send_round_trips_to_a_recent_timestamp(): void {
        Diagnostics::recordSend();

        $stamp = Diagnostics::lastSendTimestamp();
        self::assertGreaterThan( 0, $stamp );
        self::assertLessThanOrEqual( 5, abs( time() - $stamp ), 'The recorded send time should be ~now.' );
    }

    public function test_a_successful_batch_run_stamps_the_last_send(): void {
        $this->setPluginOptions( array( 'enabled' => '1', 'queue_amount' => 5 ) );
        $this->insertQueueItem( array( 'subject' => 'deliver-me' ) );

        \TotalMailQueue\Cron\BatchProcessor::run();

        self::assertGreaterThan( 0, Diagnostics::lastSendTimestamp(), 'Sending a queued email must update the last-send stamp.' );
    }

    public function test_a_run_that_sends_nothing_leaves_the_stamp_untouched(): void {
        // smtp-only mode with no accounts: the batch runs but sends nothing.
        $this->setPluginOptions( array( 'enabled' => '1', 'send_method' => 'smtp' ) );
        $this->insertQueueItem();

        \TotalMailQueue\Cron\BatchProcessor::run();

        self::assertSame( 0, Diagnostics::lastSendTimestamp(), 'A run with no successful send must not stamp a delivery time.' );
    }
}
