<?php

declare(strict_types=1);

namespace TMQ\Tests\Functional;

use TotalMailQueue\Admin\SettingsApi;

/**
 * Every field of the Settings tab rendered through the Settings API, and the
 * warnings each field adds for the values that need one.
 *
 * @covers \TotalMailQueue\Admin\SettingsApi
 */
final class SettingsFieldsTest extends FunctionalTestCase {

    protected function setUp(): void {
        parent::setUp();
        wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
        global $wp_settings_sections, $wp_settings_fields;
        $wp_settings_sections = array();
        $wp_settings_fields   = array();
        SettingsApi::init();
    }

    private function page(): string {
        ob_start();
        do_settings_sections( SettingsApi::PAGE );
        return (string) ob_get_clean();
    }

    public function test_every_section_is_a_card_and_every_field_renders(): void {
        $html = $this->page();

        self::assertSame( count( SettingsApi::sections() ), substr_count( $html, 'tmq-box tmq-settings-card' ) );
        foreach ( array( 'enabled', 'alert_enabled', 'auto_block', 'block_password_reset', 'queue_amount', 'queue_interval_unit', 'clear_queue', 'log_max_records', 'max_retries', 'smtp_timeout', 'smtp_debug', 'cron_lock_ttl', 'send_method', 'email', 'email_amount', 'from_email', 'from_name', 'dark_mode' ) as $key ) {
            self::assertStringContainsString( 'name="wp_tmq_settings[' . $key . ']"', $html, $key );
        }
    }

    public function test_the_mode_select_warns_when_disabled_or_blocking_and_not_when_queueing(): void {
        $this->setPluginOptions( array( 'enabled' => '0' ) );
        self::assertStringContainsString( 'currently disabled and has no effect', $this->field( 'renderStatus' ) );

        $this->setPluginOptions( array( 'enabled' => '2' ) );
        self::assertStringContainsString( 'All outgoing emails are being blocked!', $this->field( 'renderStatus' ) );

        $this->setPluginOptions( array( 'enabled' => '1' ) );
        self::assertStringNotContainsString( 'tmq-warning', $this->field( 'renderStatus' ) );
    }

    public function test_the_interval_shows_in_the_unit_it_was_set_in(): void {
        $this->setPluginOptions( array( 'queue_interval' => 5, 'queue_interval_unit' => 'minutes' ) );
        self::assertStringContainsString( 'name="wp_tmq_settings[queue_interval]" type="number" required min="1" value="5"', $this->field( 'renderQueue' ) );

        $this->setPluginOptions( array( 'queue_interval' => 45, 'queue_interval_unit' => 'seconds' ) );
        $html = $this->field( 'renderQueue' );
        self::assertStringContainsString( 'value="45"', $html );
        self::assertStringContainsString( 'value="seconds" selected', $html );
    }

    public function test_an_account_sending_more_per_cycle_than_the_global_limit_is_named(): void {
        $this->setPluginOptions( array( 'queue_amount' => 10 ) );
        $this->insertSmtpAccount( array( 'name' => 'Greedy', 'send_bulk' => 50 ) );
        $this->insertSmtpAccount( array( 'name' => 'Modest', 'send_bulk' => 5 ) );

        $html = $this->field( 'renderQueue' );

        self::assertStringContainsString( 'higher than the global limit of 10', $html );
        self::assertStringContainsString( '<strong>Greedy</strong> (50)', $html );
        self::assertStringNotContainsString( 'Modest', $html );
    }

    public function test_log_retention_is_shown_in_days(): void {
        $this->setPluginOptions( array( 'clear_queue' => 7 ) );

        self::assertStringContainsString( 'name="wp_tmq_settings[clear_queue]" type="number" required min="1" value="7"', $this->field( 'renderLog' ) );
    }

    public function test_smtp_only_explains_that_mail_waits_for_an_account(): void {
        $this->setPluginOptions( array( 'send_method' => 'smtp' ) );
        self::assertStringContainsString( 'until an SMTP account with available limits is found', $this->field( 'renderSendMethod' ) );

        $this->setPluginOptions( array( 'send_method' => 'php' ) );
        self::assertStringNotContainsString( 'available limits', $this->field( 'renderSendMethod' ) );
    }

    public function test_toggles_reflect_the_stored_value_and_autosave(): void {
        $this->setPluginOptions( array( 'smtp_debug' => '1', 'auto_block' => '0' ) );

        $debug = $this->field( 'renderSmtpDebug' );
        self::assertStringContainsString( 'checked', $debug );
        self::assertStringContainsString( 'settings:smtp_debug', $debug );
        self::assertStringNotContainsString( 'checked', $this->field( 'renderAutoBlock' ) );
    }

    public function test_the_dark_mode_select_offers_every_mode(): void {
        $this->setPluginOptions( array( 'dark_mode' => 'auto' ) );

        $html = $this->field( 'renderDarkMode' );

        self::assertStringContainsString( 'value="off"', $html );
        self::assertStringContainsString( 'value="on"', $html );
        self::assertStringContainsString( 'value="auto" selected', $html );
    }

    private function field( string $method ): string {
        ob_start();
        SettingsApi::$method();
        return (string) ob_get_clean();
    }
}
