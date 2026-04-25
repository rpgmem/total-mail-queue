<?php

declare(strict_types=1);

namespace TMQ\Tests\Integration;

use Brain\Monkey\Functions;

/**
 * @covers ::wp_tmq_get_settings
 */
final class GetSettingsTest extends IntegrationTestCase {

    public function test_returns_defaults_when_no_option_saved(): void {
        Functions\when( 'get_option' )->justReturn( false );

        $opts = wp_tmq_get_settings();

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

        $opts = wp_tmq_get_settings();

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

        $opts = wp_tmq_get_settings();

        self::assertSame( 420, $opts['queue_interval'] );
    }

    public function test_queue_interval_in_seconds_passes_through_when_above_minimum(): void {
        Functions\when( 'get_option' )->justReturn( array(
            'queue_interval'      => '15',
            'queue_interval_unit' => 'seconds',
        ) );

        $opts = wp_tmq_get_settings();

        self::assertSame( 15, $opts['queue_interval'] );
    }

    public function test_queue_interval_in_seconds_is_clamped_to_minimum_of_10(): void {
        Functions\when( 'get_option' )->justReturn( array(
            'queue_interval'      => '5',
            'queue_interval_unit' => 'seconds',
        ) );

        $opts = wp_tmq_get_settings();

        self::assertSame( 10, $opts['queue_interval'] );
    }

    public function test_clear_queue_in_days_is_multiplied_by_24_hours(): void {
        Functions\when( 'get_option' )->justReturn( array( 'clear_queue' => '7' ) );

        $opts = wp_tmq_get_settings();

        self::assertSame( 168, $opts['clear_queue'] );
    }
}
