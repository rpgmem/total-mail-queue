<?php

declare(strict_types=1);

namespace TMQ\Tests\Functional;

use TotalMailQueue\Cron\BatchProcessor;
use TotalMailQueue\Cron\Scheduler;
use TotalMailQueue\Settings\Options;

/**
 * The worker's schedule against the real cron table: arming on boot and
 * on enqueue, sleeping, deferring to a quota window, and what a finished
 * drain does with the cadence.
 *
 * @covers \TotalMailQueue\Cron\Scheduler
 */
final class SchedulerTest extends FunctionalTestCase {

    protected function setUp(): void {
        parent::setUp();
        $this->setPluginOptions( array( 'enabled' => '1', 'queue_interval' => 5, 'queue_interval_unit' => 'minutes' ) );
        wp_clear_scheduled_hook( Scheduler::HOOK );
    }

    protected function tearDown(): void {
        wp_clear_scheduled_hook( Scheduler::HOOK );
        parent::tearDown();
    }

    private function next(): ?int {
        $next = wp_next_scheduled( Scheduler::HOOK );
        return false === $next ? null : (int) $next;
    }

    public function test_register_wires_the_interval_the_worker_and_the_settings_listeners(): void {
        Scheduler::register();

        self::assertSame( 10, has_filter( 'cron_schedules', array( Scheduler::class, 'addInterval' ) ) );
        self::assertSame( 10, has_action( Scheduler::HOOK, array( BatchProcessor::class, 'run' ) ) );
        self::assertSame( 10, has_action( 'update_option_' . Options::OPTION_NAME, array( Scheduler::class, 'reschedule' ) ) );
        self::assertSame( 10, has_action( 'add_option_' . Options::OPTION_NAME, array( Scheduler::class, 'reschedule' ) ) );
        self::assertSame( 300, wp_get_schedules()[ Scheduler::INTERVAL_SLUG ]['interval'] );
    }

    public function test_sync_tears_the_worker_down_outside_queue_mode(): void {
        wp_schedule_event( time(), Scheduler::INTERVAL_SLUG, Scheduler::HOOK );
        update_option( Options::OPTION_NAME, array_merge( (array) get_option( Options::OPTION_NAME ), array( 'enabled' => '2' ) ) );

        Scheduler::syncSchedule();

        self::assertNull( $this->next() );
    }

    public function test_sync_leaves_an_armed_worker_alone_and_heals_a_lost_one_only_when_mail_waits(): void {
        $armed = time() + 1000;
        wp_schedule_single_event( $armed, Scheduler::HOOK );
        Scheduler::syncSchedule();
        self::assertSame( $armed, $this->next() );

        wp_clear_scheduled_hook( Scheduler::HOOK );
        Scheduler::syncSchedule();
        self::assertNull( $this->next(), 'An idle site with an empty queue stays asleep.' );

        $this->insertQueueItem();
        Scheduler::syncSchedule();
        self::assertNotNull( $this->next() );
    }

    public function test_an_enqueue_wakes_the_worker_once_and_only_in_queue_mode(): void {
        Scheduler::ensureScheduled();
        $first = $this->next();
        self::assertNotNull( $first );

        Scheduler::ensureScheduled();
        self::assertSame( $first, $this->next(), 'An armed worker keeps its rhythm.' );

        wp_clear_scheduled_hook( Scheduler::HOOK );
        update_option( Options::OPTION_NAME, array_merge( (array) get_option( Options::OPTION_NAME ), array( 'enabled' => '0' ) ) );
        Scheduler::ensureScheduled();
        self::assertNull( $this->next() );
    }

    public function test_a_deferral_never_lands_in_the_past_and_is_ignored_outside_queue_mode(): void {
        Scheduler::deferUntil( time() - 600 );
        self::assertGreaterThanOrEqual( time() + MINUTE_IN_SECONDS - 1, $this->next() );

        $later = time() + 3600;
        Scheduler::deferUntil( $later );
        self::assertSame( $later, $this->next() );

        update_option( Options::OPTION_NAME, array_merge( (array) get_option( Options::OPTION_NAME ), array( 'enabled' => '2' ) ) );
        wp_clear_scheduled_hook( Scheduler::HOOK );
        Scheduler::deferUntil( $later );
        self::assertNull( $this->next() );
    }

    public function test_a_drained_queue_puts_the_worker_to_sleep(): void {
        wp_schedule_event( time(), Scheduler::INTERVAL_SLUG, Scheduler::HOOK );

        Scheduler::adjustSchedule( Options::get() );

        self::assertNull( $this->next() );
    }

    public function test_mail_left_after_a_one_off_wake_restores_the_cadence(): void {
        $this->insertQueueItem();

        Scheduler::adjustSchedule( Options::get() );

        $next = $this->next();
        self::assertNotNull( $next );
        self::assertEqualsWithDelta( time() + 300, $next, 5 );
    }

    public function test_smtp_only_with_every_account_capped_defers_and_with_none_enabled_sleeps(): void {
        $this->setPluginOptions( array( 'send_method' => 'smtp' ) );
        wp_clear_scheduled_hook( Scheduler::HOOK );
        $this->insertQueueItem();
        $this->insertSmtpAccount( array( 'daily_limit' => 1, 'daily_sent' => 1 ) );

        // The daily cap frees at the next local midnight. Comparing against
        // "now + the cadence" failed in the last minutes before midnight, when
        // the quota window is closer than one cadence away.
        $before = self::nextLocalMidnight();
        Scheduler::adjustSchedule( Options::get() );
        self::assertContains( (int) $this->next(), array( $before, self::nextLocalMidnight() ), 'A capped account defers to its quota window.' );

        global $wpdb;
        $wpdb->query( "UPDATE `{$this->smtpTable()}` SET enabled = 0" );
        Scheduler::adjustSchedule( Options::get() );
        self::assertNull( $this->next(), 'Nothing enabled: nothing to wait for.' );
    }

    public function test_smtp_only_with_an_account_free_keeps_the_cadence(): void {
        $this->setPluginOptions( array( 'send_method' => 'smtp' ) );
        wp_clear_scheduled_hook( Scheduler::HOOK );
        $this->insertQueueItem();
        $this->insertSmtpAccount();

        Scheduler::adjustSchedule( Options::get() );

        self::assertEqualsWithDelta( time() + 300, (int) $this->next(), 5 );
    }
}
