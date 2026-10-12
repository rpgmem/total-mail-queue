<?php

declare(strict_types=1);

namespace TMQ\Tests\Integration;

use Brain\Monkey\Functions;

/**
 * @covers \TotalMailQueue\Settings\Options::get
 */
final class GetSettingsTest extends IntegrationTestCase {

    public function test_returns_defaults_when_no_option_saved(): void {
        Functions\when( 'get_option' )->justReturn( false );

        $opts = \TotalMailQueue\Settings\Options::get();

        self::assertSame( '0', $opts['enabled'] );
        self::assertSame( '0', $opts['alert_enabled'] );
        self::assertSame( 'auto', $opts['send_method'] );
        self::assertSame( 'total_mail_queue', $opts['tableName'] );
        self::assertSame( 'total_mail_queue_smtp', $opts['smtpTableName'] );
    }

    public function test_user_values_override_defaults(): void {
        Functions\when( 'get_option' )->justReturn( array(
            'enabled' => '1',
            'email'   => 'me@example.test',
        ) );

        $opts = \TotalMailQueue\Settings\Options::get();

        self::assertSame( '1', $opts['enabled'] );
        self::assertSame( 'me@example.test', $opts['email'] );
        // Other defaults must still be present.
        self::assertSame( 'auto', $opts['send_method'] );
    }

    public function test_queue_interval_in_minutes_is_multiplied_by_60(): void {
        Functions\when( 'get_option' )->justReturn( array(
            'queue_interval'      => '7',
            'queue_interval_unit' => 'minutes',
        ) );

        $opts = \TotalMailQueue\Settings\Options::get();

        self::assertSame( 420, $opts['queue_interval'] );
    }

    public function test_queue_interval_in_seconds_passes_through_when_above_minimum(): void {
        Functions\when( 'get_option' )->justReturn( array(
            'queue_interval'      => '15',
            'queue_interval_unit' => 'seconds',
        ) );

        $opts = \TotalMailQueue\Settings\Options::get();

        self::assertSame( 15, $opts['queue_interval'] );
    }

    public function test_queue_interval_in_seconds_is_clamped_to_minimum_of_10(): void {
        Functions\when( 'get_option' )->justReturn( array(
            'queue_interval'      => '5',
            'queue_interval_unit' => 'seconds',
        ) );

        $opts = \TotalMailQueue\Settings\Options::get();

        self::assertSame( 10, $opts['queue_interval'] );
    }

    public function test_clear_queue_in_days_is_multiplied_by_24_hours(): void {
        Functions\when( 'get_option' )->justReturn( array( 'clear_queue' => '7' ) );

        $opts = \TotalMailQueue\Settings\Options::get();

        self::assertSame( 168, $opts['clear_queue'] );
    }

    public function test_an_empty_stored_number_reads_as_its_default(): void {
        // Installs that saved a cleared field before the sanitizer refused it.
        Functions\when( 'get_option' )->justReturn( array(
            'clear_queue'  => '',
            'queue_amount' => '',
            'email_amount' => '',
        ) );

        $opts = \TotalMailQueue\Settings\Options::get();

        self::assertSame( 14 * 24, $opts['clear_queue'] );
        self::assertSame( '1', $opts['queue_amount'] );
        self::assertSame( '10', $opts['email_amount'] );
    }

    public function test_a_stored_number_below_its_floor_reads_as_the_floor(): void {
        Functions\when( 'get_option' )->justReturn( array(
            'clear_queue'   => '0',
            'cron_lock_ttl' => '5',
        ) );

        $opts = \TotalMailQueue\Settings\Options::get();

        self::assertSame( 24, $opts['clear_queue'] );
        self::assertSame( '30', $opts['cron_lock_ttl'] );
    }
}

