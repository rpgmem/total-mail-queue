<?php

declare(strict_types=1);

namespace TMQ\Tests\Integration;

use Brain\Monkey\Functions;
use TotalMailQueue\Admin\Assets;
use TotalMailQueue\Admin\SettingsApi;

/**
 * With Free Form Certificate active, the plugin's screens follow its Dark
 * Mode setting and the local field is shown disabled, still posting the value
 * stored here (#164). Without it, the local setting decides, as before.
 *
 * FFC is detected by its `FFC_VERSION` constant, and a constant cannot be
 * undefined, so each test runs in its own process.
 *
 * @covers \TotalMailQueue\Admin\Assets
 * @covers \TotalMailQueue\Admin\SettingsApi
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class FfcDarkModeTest extends IntegrationTestCase {

    /** @var array<string,mixed>|false */
    private $ffc = false;

    protected function setUp(): void {
        parent::setUp();
        $this->options['dark_mode'] = 'auto';
        Functions\when( 'get_option' )->alias( function ( $key, $default = false ) {
            if ( 'wp_tmq_settings' === $key ) {
                return $this->options;
            }
            if ( 'ffc_settings' === $key ) {
                return $this->ffc;
            }
            return $default;
        } );
        Functions\when( 'selected' )->alias(
            static function ( $a, $b, bool $display = true ): string {
                return (string) $a === (string) $b ? ' selected="selected"' : '';
            }
        );
    }

    private function field(): string {
        ob_start();
        SettingsApi::renderDarkMode();
        return (string) ob_get_clean();
    }

    public function test_without_ffc_the_local_setting_decides_and_the_field_is_live(): void {
        self::assertFalse( Assets::ffcControlsDarkMode() );
        self::assertSame( 'auto', Assets::darkMode() );

        $html = $this->field();
        self::assertStringContainsString( '<select id="wp_tmq_dark_mode" name="wp_tmq_settings[dark_mode]">', $html );
        self::assertStringNotContainsString( 'disabled', $html );
        self::assertStringNotContainsString( 'type="hidden"', $html );
        self::assertStringNotContainsString( 'Free Form Certificate', $html );
    }

    public function test_with_ffc_its_setting_decides(): void {
        define( 'FFC_VERSION', '6.41.0' );
        $this->ffc = array( 'dark_mode' => 'on' );

        self::assertTrue( Assets::ffcControlsDarkMode() );
        self::assertSame( 'on', Assets::darkMode() );
    }

    public function test_with_ffc_an_unsaved_or_unknown_setting_reads_as_off(): void {
        define( 'FFC_VERSION', '6.41.0' );

        self::assertSame( 'off', Assets::darkMode(), 'FFC never saved: its own default.' );

        $this->ffc = array( 'dark_mode' => 'sepia' );
        self::assertSame( 'off', Assets::darkMode() );
    }

    public function test_with_ffc_the_field_is_disabled_with_the_note_and_keeps_the_local_value(): void {
        define( 'FFC_VERSION', '6.41.0' );
        $this->ffc = array( 'dark_mode' => 'on' );

        $html = $this->field();

        // The select shows the value in force, and posts nothing.
        self::assertStringContainsString( '<select id="wp_tmq_dark_mode" disabled', $html );
        self::assertSame( 1, preg_match( '/<select[^>]*>/', $html, $select ) );
        self::assertStringNotContainsString( 'name=', $select[0] );
        self::assertStringContainsString( 'value="on" selected', $html );
        // The stored local value travels in a hidden field, so Save keeps it.
        self::assertStringContainsString( '<input type="hidden" name="wp_tmq_settings[dark_mode]" value="auto" />', $html );
        self::assertStringContainsString( 'Controlled by Free Form Certificate', $html );
    }
}
