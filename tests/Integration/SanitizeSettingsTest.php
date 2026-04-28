<?php

declare(strict_types=1);

namespace TMQ\Tests\Integration;

use Brain\Monkey\Functions;

/**
 * @covers \TotalMailQueue\Settings\Sanitizer::sanitize
 */
final class SanitizeSettingsTest extends IntegrationTestCase {

    protected function setUp(): void {
        parent::setUp();
        Functions\when( 'sanitize_text_field' )->returnArg();
        // Match WP's contract: returns the address if it parses, else ''.
        Functions\when( 'sanitize_email' )->alias( static function ( $value ) {
            $value = (string) $value;
            return ( false !== filter_var( $value, FILTER_VALIDATE_EMAIL ) ) ? $value : '';
        } );
    }

    public function test_returns_empty_array_when_input_is_not_an_array(): void {
        self::assertSame( array(), \TotalMailQueue\Settings\Sanitizer::sanitize( 'malicious string' ) );
        self::assertSame( array(), \TotalMailQueue\Settings\Sanitizer::sanitize( null ) );
        self::assertSame( array(), \TotalMailQueue\Settings\Sanitizer::sanitize( 42 ) );
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

        self::assertSame( $input, \TotalMailQueue\Settings\Sanitizer::sanitize( $input ) );
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

        $sanitized = \TotalMailQueue\Settings\Sanitizer::sanitize( $input );

        self::assertSame( array( 'enabled' => '1' ), $sanitized );
        self::assertArrayNotHasKey( 'tableName', $sanitized );
        self::assertArrayNotHasKey( 'smtpTableName', $sanitized );
        self::assertArrayNotHasKey( 'triggercount', $sanitized );
    }

    public function test_text_keys_are_passed_through_sanitize_text_field(): void {
        $observed = array();
        Functions\when( 'sanitize_text_field' )->alias( static function ( $value ) use ( &$observed ) {
            $observed[] = $value;
            return strtoupper( (string) $value );
        } );

        $result = \TotalMailQueue\Settings\Sanitizer::sanitize( array(
            'enabled'   => 'yes',
            'from_name' => 'Site Robot',
            'unknown'   => 'should be skipped before sanitize is called',
        ) );

        // sanitize_text_field is invoked exactly for the whitelisted text keys.
        self::assertSame( array( 'yes', 'Site Robot' ), $observed );
        // And its return value is what ends up in the sanitized output.
        self::assertSame( array( 'enabled' => 'YES', 'from_name' => 'SITE ROBOT' ), $result );
    }

    public function test_email_keys_are_validated_with_sanitize_email(): void {
        $result = \TotalMailQueue\Settings\Sanitizer::sanitize( array(
            'email'      => 'alerts@example.test',
            'from_email' => 'no-reply@example.test',
        ) );

        self::assertSame(
            array(
                'email'      => 'alerts@example.test',
                'from_email' => 'no-reply@example.test',
            ),
            $result
        );
    }

    public function test_garbage_in_email_keys_collapses_to_empty_string(): void {
        // The real defect this guards against: previously sanitize_text_field
        // happily persisted "not an email" as a setting, causing wp_mail to
        // ship malformed From: headers downstream.
        $result = \TotalMailQueue\Settings\Sanitizer::sanitize( array(
            'email'      => 'not an email',
            'from_email' => '<garbage>',
        ) );

        self::assertSame(
            array(
                'email'      => '',
                'from_email' => '',
            ),
            $result
        );
    }
}
