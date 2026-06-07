<?php

declare(strict_types=1);

namespace TMQ\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TotalMailQueue\Cron\Scheduler;

/**
 * @covers \TotalMailQueue\Cron\Scheduler::nextState
 */
final class SchedulerStateTest extends TestCase {

    public function test_empty_queue_goes_idle_regardless_of_method(): void {
        self::assertSame( 'idle', Scheduler::nextState( 0, 'smtp', false, null ) );
        self::assertSame( 'idle', Scheduler::nextState( 0, 'auto', true, 123 ) );
        self::assertSame( 'idle', Scheduler::nextState( 0, 'php', false, null ) );
    }

    public function test_auto_and_php_keep_cadence_even_without_an_account(): void {
        // Both can fall back to the default transport, so account availability
        // never blocks the drain.
        self::assertSame( 'keep', Scheduler::nextState( 5, 'auto', false, 999 ) );
        self::assertSame( 'keep', Scheduler::nextState( 5, 'php', false, null ) );
    }

    public function test_smtp_keeps_cadence_when_an_account_is_available(): void {
        self::assertSame( 'keep', Scheduler::nextState( 5, 'smtp', true, null ) );
    }

    public function test_smtp_defers_when_capped_with_a_known_recovery_time(): void {
        self::assertSame( 'defer', Scheduler::nextState( 5, 'smtp', false, 1700000000 ) );
    }

    public function test_smtp_goes_idle_when_no_account_is_enabled(): void {
        // Nothing to wait for: re-armed when the admin enables an account.
        self::assertSame( 'idle', Scheduler::nextState( 5, 'smtp', false, null ) );
    }
}
