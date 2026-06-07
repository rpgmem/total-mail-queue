<?php

declare(strict_types=1);

namespace TMQ\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TotalMailQueue\Admin\Notices;

/**
 * @covers \TotalMailQueue\Admin\Notices::isWorkerStalled
 */
final class WorkerStalledTest extends TestCase {

    private const NOW      = 1_700_000_000;
    private const INTERVAL = 600; // 10 minutes.

    public function test_no_warning_when_queue_is_empty(): void {
        self::assertFalse(
            Notices::isWorkerStalled( self::NOW, 0, null, self::INTERVAL, self::NOW - 99999 )
        );
    }

    public function test_no_warning_when_worker_has_never_run(): void {
        // last_run = 0 (fresh install): no evidence of a stall yet.
        self::assertFalse(
            Notices::isWorkerStalled( self::NOW, 5, null, self::INTERVAL, 0 )
        );
    }

    public function test_no_warning_when_worker_ran_recently(): void {
        self::assertFalse(
            Notices::isWorkerStalled( self::NOW, 5, self::NOW, self::INTERVAL, self::NOW - 60 )
        );
    }

    public function test_no_warning_during_intentional_future_deferral(): void {
        // Next run parked well into the future (e.g. every SMTP account capped),
        // even though the last run is ancient.
        $next = self::NOW + ( 45 * 60 );
        self::assertFalse(
            Notices::isWorkerStalled( self::NOW, 5, $next, self::INTERVAL, self::NOW - 99999 )
        );
    }

    public function test_warns_when_pending_and_worker_is_stale(): void {
        // 30 minutes since the last run, well past the 2x-interval / 15-min floor.
        self::assertTrue(
            Notices::isWorkerStalled( self::NOW, 3, self::NOW - ( 30 * 60 ), self::INTERVAL, self::NOW - ( 30 * 60 ) )
        );
    }

    public function test_warns_when_overdue_event_is_in_the_near_past(): void {
        // An overdue (past) scheduled event does not count as a deferral.
        self::assertTrue(
            Notices::isWorkerStalled( self::NOW, 1, self::NOW - 120, self::INTERVAL, self::NOW - ( 25 * 60 ) )
        );
    }

    public function test_threshold_floor_is_fifteen_minutes_for_short_intervals(): void {
        // 1-minute interval: 2x interval is tiny, so the 15-minute floor applies.
        $short = 60;
        self::assertFalse(
            Notices::isWorkerStalled( self::NOW, 2, self::NOW, $short, self::NOW - ( 10 * 60 ) ),
            '10 minutes stale must not warn under the 15-minute floor.'
        );
        self::assertTrue(
            Notices::isWorkerStalled( self::NOW, 2, self::NOW, $short, self::NOW - ( 20 * 60 ) ),
            '20 minutes stale must warn.'
        );
    }
}
