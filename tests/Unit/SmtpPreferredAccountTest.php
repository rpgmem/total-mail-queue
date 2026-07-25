<?php

declare(strict_types=1);

namespace TMQ\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TotalMailQueue\Smtp\Repository as SmtpRepository;

/**
 * Exercises {@see SmtpRepository::pickPreferredOrAvailable()} — the per-source
 * preferred-account selection with fallback to the normal rotation.
 *
 * @covers \TotalMailQueue\Smtp\Repository::pickPreferredOrAvailable
 */
final class SmtpPreferredAccountTest extends TestCase {

    /**
     * @return list<array<string,mixed>>
     */
    private function accounts(): array {
        return array(
            array( 'id' => 1, 'send_bulk' => 0, 'cycle_sent' => 0 ),
            array( 'id' => 2, 'send_bulk' => 5, 'cycle_sent' => 5 ), // exhausted
            array( 'id' => 3, 'send_bulk' => 0, 'cycle_sent' => 0 ),
        );
    }

    public function test_returns_the_preferred_account_when_it_has_capacity(): void {
        // Preferred #3 wins even though #1 is first in the rotation order.
        $picked = SmtpRepository::pickPreferredOrAvailable( $this->accounts(), 3 );
        self::assertSame( 3, $picked['id'] );
    }

    public function test_falls_back_to_rotation_when_no_preference(): void {
        $picked = SmtpRepository::pickPreferredOrAvailable( $this->accounts(), 0 );
        self::assertSame( 1, $picked['id'], 'preferred_id 0 → normal pick (first with capacity)' );
    }

    public function test_falls_back_when_preferred_is_absent_from_snapshot(): void {
        // #9 isn't in the available snapshot (e.g. disabled / over a persisted
        // limit) → normal rotation.
        $picked = SmtpRepository::pickPreferredOrAvailable( $this->accounts(), 9 );
        self::assertSame( 1, $picked['id'] );
    }

    public function test_falls_back_when_preferred_is_over_capacity(): void {
        // #2 is preferred but exhausted its cycle → normal rotation picks #1.
        $picked = SmtpRepository::pickPreferredOrAvailable( $this->accounts(), 2 );
        self::assertSame( 1, $picked['id'] );
    }

    public function test_returns_null_when_everything_is_exhausted(): void {
        $accounts = array(
            array( 'id' => 1, 'send_bulk' => 2, 'cycle_sent' => 2 ),
            array( 'id' => 2, 'send_bulk' => 3, 'cycle_sent' => 3 ),
        );
        self::assertNull( SmtpRepository::pickPreferredOrAvailable( $accounts, 2 ) );
    }

    public function test_matches_preferred_id_against_string_numeric_ids(): void {
        // wpdb returns INT columns as strings; the match must intval them.
        $accounts = array(
            array( 'id' => '4', 'send_bulk' => '0', 'cycle_sent' => '0' ),
            array( 'id' => '5', 'send_bulk' => '0', 'cycle_sent' => '0' ),
        );
        $picked = SmtpRepository::pickPreferredOrAvailable( $accounts, 5 );
        self::assertSame( '5', $picked['id'] );
    }
}
