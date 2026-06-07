<?php

declare(strict_types=1);

namespace TMQ\Tests\Functional;

use TotalMailQueue\Smtp\Repository;

/**
 * The waiting-row "why is the queue stuck?" message.
 *
 * @covers \TotalMailQueue\Smtp\Repository::blockSummary
 */
final class BlockSummaryTest extends FunctionalTestCase {

    public function test_reports_when_no_account_is_enabled(): void {
        $summary = Repository::blockSummary( 300 );

        self::assertStringContainsString( 'no SMTP account available', $summary );
    }

    public function test_names_the_limit_that_capped_the_account(): void {
        $this->insertSmtpAccount(
            array(
                'name'             => 'Primary SMTP',
                'enabled'          => 1,
                'send_interval'    => 60,
                'send_bulk'        => 25,
                'cycle_sent'       => 25,
                'daily_limit'      => 190,
                'daily_sent'       => 30,
                'monthly_limit'    => 950,
                'monthly_sent'     => 100,
                'last_cycle_reset' => gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( 10 * MINUTE_IN_SECONDS ) ),
            )
        );

        $summary = Repository::blockSummary( 300 );

        self::assertStringContainsString( 'no SMTP account available', $summary );
        self::assertStringContainsString( 'Primary SMTP', $summary );
        self::assertStringContainsString( 'per-cycle quota reached (25/25)', $summary );
        self::assertStringNotContainsString( 'daily limit reached', $summary, 'Only the limits actually hit should be listed.' );
    }
}
