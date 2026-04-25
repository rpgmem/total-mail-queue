<?php

declare(strict_types=1);

namespace TMQ\Tests\Integration;

use Brain\Monkey\Functions;

/**
 * @covers ::wp_tmq_sanitize_settings
 */
final class SanitizeSettingsTest extends IntegrationTestCase {

    protected function setUp(): void {
        parent::setUp();
        Functions\when( 'sanitize_text_field' )->returnArg();
    }

    public function test_returns_empty_array_when_input_is_not_an_array(): void {
        self::assertSame( array(), wp_tmq_sanitize_settings( 'malicious string' ) );
        self::assertSame( array(), wp_tmq_sanitize_settings( null ) );
        self::assertSame( array(), wp_tmq_sanitize_settings( 42 ) );
    }

    public function test_keeps_whitelisted_keys(): void {
        $input = array(
            'enabled'       => '1',
            'email'         => 'me@example.test',
            'send_method'   => 'smtp',
            'max_retries'   => '5',
            'cron_lock_ttl' => '600',
            'smtp_timeout'  => '45',
        );

        self::assertSame( $input, wp_tmq_sanitize_settings( $input ) );
    }

    public function test_strips_unknown_keys_to_block_settings_injection(): void {
        // tableName/smtpTableName must never be settable from a settings POST,
        // since they are used to interpolate SQL queries elsewhere.
        $input = array(
            'enabled'       => '1',
            'tableName'     => 'wp_users; DROP TABLE',
            'smtpTableName' => 'evil',
            'triggercount'  => 999,
        );

        $sanitized = wp_tmq_sanitize_settings( $input );

        self::assertSame( array( 'enabled' => '1' ), $sanitized );
        self::assertArrayNotHasKey( 'tableName', $sanitized );
        self::assertArrayNotHasKey( 'smtpTableName', $sanitized );
        self::assertArrayNotHasKey( 'triggercount', $sanitized );
    }

    public function test_each_kept_value_is_passed_through_sanitize_text_field(): void {
        $observed = array();
        Functions\when( 'sanitize_text_field' )->alias( static function ( $value ) use ( &$observed ) {
            $observed[] = $value;
            return strtoupper( (string) $value );
        } );

        $result = wp_tmq_sanitize_settings( array(
            'enabled' => 'yes',
            'email'   => 'me@example.test',
            'unknown' => 'should be skipped before sanitize is called',
        ) );

        // sanitize_text_field is invoked exactly for the whitelisted keys.
        self::assertSame( array( 'yes', 'me@example.test' ), $observed );
        // And its return value is what ends up in the sanitized output.
        self::assertSame( array( 'enabled' => 'YES', 'email' => 'ME@EXAMPLE.TEST' ), $result );
    }
}
