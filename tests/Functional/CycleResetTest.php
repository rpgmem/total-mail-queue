<?php

declare(strict_types=1);

namespace TMQ\Tests\Functional;

use TotalMailQueue\Smtp\Repository;

/**
 * Exercises the per-cycle quota rollover in \TotalMailQueue\Smtp\Repository.
 *
 * The cycle window for an interval account (send_interval > 0) must be measured
 * from the moment the cycle *began* (last_cycle_reset), not from the account's
 * last send (last_sent_at) — otherwise an account that keeps trickling out mail
 * slides its own window forward forever and the cycle never rolls over.
 *
 * @covers \TotalMailQueue\Smtp\Repository::resetCounters
 * @covers \TotalMailQueue\Smtp\Repository::incrementCounter
 */
final class CycleResetTest extends FunctionalTestCase {

    /**
     * A site-local "mysql" datetime offset from now by the given minutes.
     */
    private function minutesAgo( int $minutes ): string {
        return gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( $minutes * MINUTE_IN_SECONDS ) );
    }

    private function cycleSent( int $id ): int {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare( "SELECT cycle_sent FROM `{$this->smtpTable()}` WHERE id = %d", $id ) );
    }

    private function lastCycleReset( int $id ): string {
        global $wpdb;
        return (string) $wpdb->get_var( $wpdb->prepare( "SELECT last_cycle_reset FROM `{$this->smtpTable()}` WHERE id = %d", $id ) );
    }

    public function test_interval_cycle_resets_once_the_window_elapses_since_it_began(): void {
        $id = $this->insertSmtpAccount(
            array(
                'send_interval'    => 60,
                'send_bulk'        => 25,
                'cycle_sent'       => 25,
                'last_cycle_reset' => $this->minutesAgo( 90 ),
                'last_sent_at'     => $this->minutesAgo( 90 ),
            )
        );

        Repository::resetCounters();

        self::assertSame( 0, $this->cycleSent( $id ), 'A cycle that began more than send_interval minutes ago must roll over.' );
    }

    public function test_interval_cycle_holds_while_the_window_is_still_open(): void {
        $id = $this->insertSmtpAccount(
            array(
                'send_interval'    => 60,
                'send_bulk'        => 25,
                'cycle_sent'       => 25,
                'last_cycle_reset' => $this->minutesAgo( 30 ),
                'last_sent_at'     => $this->minutesAgo( 30 ),
            )
        );

        Repository::resetCounters();

        self::assertSame( 25, $this->cycleSent( $id ), 'A cycle still inside its window must not roll over early.' );
    }

    /**
     * The regression this fix targets: an account that kept sending right up to
     * the present (recent last_sent_at) but whose cycle began long ago must
     * still roll over. Anchoring on last_sent_at would wrongly hold it capped.
     */
    public function test_trickling_account_still_rolls_over_despite_a_recent_last_send(): void {
        $id = $this->insertSmtpAccount(
            array(
                'send_interval'    => 60,
                'send_bulk'        => 25,
                'cycle_sent'       => 25,
                'last_cycle_reset' => $this->minutesAgo( 90 ),
                'last_sent_at'     => $this->minutesAgo( 1 ),
            )
        );

        Repository::resetCounters();

        self::assertSame( 0, $this->cycleSent( $id ), 'A long-running cycle must roll over even when the account sent recently.' );
    }

    public function test_increment_stamps_cycle_start_only_on_the_first_send_of_a_cycle(): void {
        $id = $this->insertSmtpAccount(
            array(
                'send_interval'    => 60,
                'send_bulk'        => 25,
                'cycle_sent'       => 0,
                'last_cycle_reset' => '2000-01-01 00:00:00',
            )
        );

        Repository::incrementCounter( $id );
        $first_anchor = $this->lastCycleReset( $id );

        self::assertSame( 1, $this->cycleSent( $id ) );
        self::assertNotSame( '2000-01-01 00:00:00', $first_anchor, 'The first send of a cycle must stamp last_cycle_reset.' );

        Repository::incrementCounter( $id );

        self::assertSame( 2, $this->cycleSent( $id ) );
        self::assertSame( $first_anchor, $this->lastCycleReset( $id ), 'A mid-cycle send must not move the cycle start.' );
    }

    public function test_global_cycle_account_resets_every_run(): void {
        $id = $this->insertSmtpAccount(
            array(
                'send_interval' => 0,
                'send_bulk'     => 25,
                'cycle_sent'    => 25,
            )
        );

        Repository::resetCounters();

        self::assertSame( 0, $this->cycleSent( $id ), 'Global-cycle accounts (send_interval = 0) reset on every run.' );
    }
}
