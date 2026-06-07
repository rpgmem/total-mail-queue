<?php

declare(strict_types=1);

namespace TMQ\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TotalMailQueue\Admin\Notices;

/**
 * @covers \TotalMailQueue\Admin\Notices::isWorkerStalled
 */
final class WorkerStalledTest extends TestCase {

    private const NOW       = 1_700_000_000;
    private const INTERVAL  = 600; // 10 minutes.
    private const TWO_HOURS = 2 * HOUR_IN_SECONDS;

    public function test_no_warning_when_queue_is_empty(): void {
        self::assertFalse(
            Notices::isWorkerStalled( self::NOW, 0, null, self::INTERVAL, self::NOW - 99999 )
        );
    }

    public function test_no_warning_when_nothing_has_ever_been_sent(): void {
        // last_send = 0 (brand-new setup): no delivery history to judge against.
        self::assertFalse(
            Notices::isWorkerStalled( self::NOW, 5, null, self::INTERVAL, 0 )
        );
    }

    public function test_no_warning_when_a_send_happened_within_the_window(): void {
        // Sent 30 minutes ago — well inside the 2-hour window — even with a
        // long-overdue event, delivery is clearly still progressing.
        self::assertFalse(
            Notices::isWorkerStalled( self::NOW, 5, self::NOW - DAY_IN_SECONDS, self::INTERVAL, self::NOW - ( 30 * 60 ) )
        );
    }

    public function test_no_warning_during_intentional_future_deferral(): void {
        // Quiet for 3 hours (every account capped), but the next run is parked
        // in the future on purpose — the worker is still set to act.
        $next = self::NOW + ( 45 * 60 );
        self::assertFalse(
            Notices::isWorkerStalled( self::NOW, 5, $next, self::INTERVAL, self::NOW - ( 3 * HOUR_IN_SECONDS ) )
        );
    }

    public function test_no_warning_when_a_freshly_armed_event_has_not_run_yet(): void {
        // The dominant false positive: idle for hours (last send old), then
        // mail arrives and an immediate event is armed at ~now. The event is
        // not overdue, so the queue is about to process — no warning.
        self::assertFalse(
            Notices::isWorkerStalled( self::NOW, 8, self::NOW, self::INTERVAL, self::NOW - ( 5 * HOUR_IN_SECONDS ) )
        );
    }

    public function test_warns_when_quiet_for_over_two_hours_with_an_overdue_event(): void {
        // Mail waiting, nothing sent for 3 hours, and the scheduled event is
        // well in the past — WP-Cron isn't firing it.
        $overdue = self::NOW - ( 40 * 60 );
        self::assertTrue(
            Notices::isWorkerStalled( self::NOW, 3, $overdue, self::INTERVAL, self::NOW - ( 3 * HOUR_IN_SECONDS ) )
        );
    }

    public function test_warns_when_quiet_for_over_two_hours_and_no_event_is_armed(): void {
        self::assertTrue(
            Notices::isWorkerStalled( self::NOW, 3, null, self::INTERVAL, self::NOW - ( self::TWO_HOURS + 60 ) )
        );
    }

    public function test_overdue_grace_tolerates_normal_cron_tick_spacing(): void {
        // Quiet > 2h, but the event is only a couple of minutes past due — within
        // the grace for an external cron's tick spacing, so not yet a stall.
        $slightly_late = self::NOW - 120;
        self::assertFalse(
            Notices::isWorkerStalled( self::NOW, 3, $slightly_late, self::INTERVAL, self::NOW - ( 3 * HOUR_IN_SECONDS ) )
        );
    }
}
