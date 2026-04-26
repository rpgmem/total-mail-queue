<?php

declare(strict_types=1);

namespace TMQ\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * @covers \TotalMailQueue\Smtp\Repository::pickAvailable
 * @covers \TotalMailQueue\Smtp\Repository::bumpMemoryCounter
 */
final class SmtpHelpersTest extends TestCase {

    public function test_pick_returns_null_when_list_is_empty(): void {
        self::assertNull( \TotalMailQueue\Smtp\Repository::pickAvailable( array() ) );
    }

    public function test_pick_returns_first_account_with_unlimited_bulk(): void {
        $accounts = array(
            array( 'id' => 7, 'send_bulk' => 0, 'cycle_sent' => 999 ),
            array( 'id' => 8, 'send_bulk' => 0, 'cycle_sent' => 0 ),
        );

        $picked = \TotalMailQueue\Smtp\Repository::pickAvailable( $accounts );

        self::assertSame( 7, $picked['id'], 'send_bulk=0 means unlimited, so the first one wins regardless of cycle_sent.' );
    }

    public function test_pick_skips_accounts_at_or_above_cycle_limit(): void {
        $accounts = array(
            array( 'id' => 1, 'send_bulk' => 5, 'cycle_sent' => 5 ),
            array( 'id' => 2, 'send_bulk' => 10, 'cycle_sent' => 9 ),
        );

        $picked = \TotalMailQueue\Smtp\Repository::pickAvailable( $accounts );

        self::assertSame( 2, $picked['id'] );
    }

    public function test_pick_returns_null_when_all_accounts_are_exhausted(): void {
        $accounts = array(
            array( 'id' => 1, 'send_bulk' => 5, 'cycle_sent' => 5 ),
            array( 'id' => 2, 'send_bulk' => 3, 'cycle_sent' => 3 ),
        );

        self::assertNull( \TotalMailQueue\Smtp\Repository::pickAvailable( $accounts ) );
    }

    public function test_pick_handles_string_numeric_values(): void {
        // wpdb returns strings for INT columns by default; the picker must intval them.
        $accounts = array(
            array( 'id' => '4', 'send_bulk' => '2', 'cycle_sent' => '2' ),
            array( 'id' => '5', 'send_bulk' => '2', 'cycle_sent' => '1' ),
        );

        $picked = \TotalMailQueue\Smtp\Repository::pickAvailable( $accounts );

        self::assertSame( '5', $picked['id'] );
    }

    public function test_update_memory_counter_increments_matching_account(): void {
        $accounts = array(
            array( 'id' => 1, 'send_bulk' => 10, 'cycle_sent' => 0 ),
            array( 'id' => 2, 'send_bulk' => 10, 'cycle_sent' => 4 ),
        );

        \TotalMailQueue\Smtp\Repository::bumpMemoryCounter( $accounts, 2 );

        self::assertSame( 0, $accounts[0]['cycle_sent'] );
        self::assertSame( 5, $accounts[1]['cycle_sent'] );
    }

    public function test_update_memory_counter_is_noop_when_id_not_found(): void {
        $accounts = array(
            array( 'id' => 1, 'send_bulk' => 10, 'cycle_sent' => 3 ),
        );
        $original = $accounts;

        \TotalMailQueue\Smtp\Repository::bumpMemoryCounter( $accounts, 99 );

        self::assertSame( $original, $accounts );
    }

    public function test_update_memory_counter_only_touches_first_match(): void {
        // Defensive behavior: ids should be unique, but if duplicates ever appear
        // the function should only increment the first one.
        $accounts = array(
            array( 'id' => 3, 'send_bulk' => 10, 'cycle_sent' => 1 ),
            array( 'id' => 3, 'send_bulk' => 10, 'cycle_sent' => 1 ),
        );

        \TotalMailQueue\Smtp\Repository::bumpMemoryCounter( $accounts, 3 );

        self::assertSame( 2, $accounts[0]['cycle_sent'] );
        self::assertSame( 1, $accounts[1]['cycle_sent'] );
    }
}
