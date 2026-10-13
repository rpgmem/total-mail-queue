<?php

declare(strict_types=1);

namespace TMQ\Tests\Functional;

use TotalMailQueue\Smtp\Repository as SmtpRepository;

/**
 * When a capped account frees up again, the reason a waiting row shows,
 * and the plain reads and writes the admin screens and the import use.
 *
 * @covers \TotalMailQueue\Smtp\Repository
 */
final class SmtpRepositoryTest extends FunctionalTestCase {

    private const INTERVAL = 300;

    public function test_no_enabled_account_means_nothing_to_wait_for(): void {
        $this->insertSmtpAccount( array( 'enabled' => 0 ) );

        self::assertNull( SmtpRepository::nextAvailableAt( self::INTERVAL ) );
        self::assertStringContainsString( 'no account is enabled', SmtpRepository::blockSummary( self::INTERVAL ) );
    }

    public function test_an_account_with_room_is_free_now(): void {
        $this->insertSmtpAccount();

        self::assertEqualsWithDelta( time(), SmtpRepository::nextAvailableAt( self::INTERVAL ), 2 );
        self::assertStringContainsString( 'check that accounts are enabled', SmtpRepository::blockSummary( self::INTERVAL ), 'No limit is claimed when none is reached.' );
    }

    public function test_a_daily_cap_frees_at_the_next_local_midnight(): void {
        $this->insertSmtpAccount( array( 'name' => 'Daily', 'daily_limit' => 10, 'daily_sent' => 10 ) );

        $before = self::nextLocalMidnight();
        $free   = SmtpRepository::nextAvailableAt( self::INTERVAL );

        self::assertContains( $free, array( $before, self::nextLocalMidnight() ) );
        self::assertStringContainsString( 'Daily: daily limit reached (10/10), resumes ~', SmtpRepository::blockSummary( self::INTERVAL ) );
    }

    public function test_a_monthly_cap_frees_on_the_first_of_next_month(): void {
        $this->insertSmtpAccount( array( 'name' => 'Monthly', 'monthly_limit' => 100, 'monthly_sent' => 100 ) );

        $free = SmtpRepository::nextAvailableAt( self::INTERVAL );

        self::assertSame( '01 00:00', wp_date( 'd H:i', $free ) );
        self::assertGreaterThan( time(), $free );
        self::assertStringContainsString( 'monthly limit reached (100/100)', SmtpRepository::blockSummary( self::INTERVAL ) );
    }

    public function test_a_per_cycle_cap_frees_after_its_own_interval_or_the_next_worker_run(): void {
        $cycle_start = time() - 120;
        $own         = $this->insertSmtpAccount(
            array(
                'name'             => 'Own cycle',
                'send_bulk'        => 2,
                'cycle_sent'       => 2,
                'send_interval'    => 10,
                'last_cycle_reset' => get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $cycle_start ) ),
            )
        );
        self::assertSame( $cycle_start + 10 * MINUTE_IN_SECONDS, SmtpRepository::nextAvailableAt( self::INTERVAL ) );

        global $wpdb;
        $wpdb->update( $this->smtpTable(), array( 'send_interval' => 0 ), array( 'id' => $own ) );
        self::assertEqualsWithDelta( time() + self::INTERVAL, SmtpRepository::nextAvailableAt( self::INTERVAL ), 2 );
        self::assertStringContainsString( 'per-cycle quota reached (2/2)', SmtpRepository::blockSummary( self::INTERVAL ) );
    }

    public function test_the_earliest_account_decides_and_every_capped_one_is_named(): void {
        $this->insertSmtpAccount( array( 'name' => 'Month', 'monthly_limit' => 1, 'monthly_sent' => 1 ) );
        $this->insertSmtpAccount( array( 'name' => 'Day', 'daily_limit' => 1, 'daily_sent' => 1 ) );

        $free = SmtpRepository::nextAvailableAt( self::INTERVAL );
        self::assertLessThanOrEqual( self::nextLocalMidnight(), $free );

        $summary = SmtpRepository::blockSummary( self::INTERVAL );
        self::assertStringContainsString( 'every account is at a limit', $summary );
        self::assertStringContainsString( 'Month: ', $summary );
        self::assertStringContainsString( ' | Day: ', $summary );
    }

    public function test_crud_names_and_counters(): void {
        $id = SmtpRepository::insert(
            array(
                'name'       => 'Inserted',
                'host'       => 'h.example.test',
                'password'   => 'cipher',
                'daily_sent' => 4,
            )
        );
        self::assertGreaterThan( 0, $id );
        self::assertSame( array( $id => 'Inserted' ), SmtpRepository::namesById() );
        self::assertSame( 'cipher', SmtpRepository::findPasswordById( $id ) );
        self::assertSame( '', SmtpRepository::findPasswordById( $id + 100 ) );

        SmtpRepository::update( $id, array( 'name' => 'Renamed' ) );
        self::assertSame( 'Renamed', SmtpRepository::findById( $id )['name'] );

        SmtpRepository::zeroSentCounters();
        self::assertSame( 0, (int) SmtpRepository::findById( $id )['daily_sent'] );

        SmtpRepository::delete( $id );
        self::assertNull( SmtpRepository::findById( $id ) );
    }

    public function test_the_import_helpers_list_columns_and_empty_the_table(): void {
        $this->insertSmtpAccount();
        $columns = SmtpRepository::tableColumns();

        self::assertContains( 'host', $columns );
        self::assertContains( 'last_cycle_reset', $columns );

        SmtpRepository::truncate();
        self::assertSame( array(), SmtpRepository::all() );
    }

    public function test_only_enabled_accounts_above_the_global_amount_are_reported(): void {
        $this->insertSmtpAccount( array( 'name' => 'Big', 'send_bulk' => 20 ) );
        $this->insertSmtpAccount( array( 'name' => 'Off', 'send_bulk' => 50, 'enabled' => 0 ) );
        $this->insertSmtpAccount( array( 'name' => 'Small', 'send_bulk' => 5 ) );
        $this->insertSmtpAccount( array( 'name' => 'Global', 'send_bulk' => 0 ) );

        self::assertSame( array( 'Big' ), array_column( SmtpRepository::accountsExceedingBulk( 10 ), 'name' ) );
    }
}
