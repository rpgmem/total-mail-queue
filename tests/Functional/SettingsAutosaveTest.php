<?php

declare(strict_types=1);

namespace TMQ\Tests\Functional;

use TotalMailQueue\Admin\SettingsAutosave;
use TotalMailQueue\Cron\AutoBlock;
use TotalMailQueue\Settings\Options;
use TotalMailQueue\Templates\Options as TemplateOptions;
use WP_Ajax_UnitTestCase;
use WPAjaxDieContinueException;
use WPAjaxDieStopException;

/**
 * Toggles that save on flip (#89).
 *
 * @covers \TotalMailQueue\Admin\SettingsAutosave
 * @covers \TotalMailQueue\Admin\SettingsApi
 */
final class SettingsAutosaveTest extends WP_Ajax_UnitTestCase {

    protected function setUp(): void {
        parent::setUp();
        \TotalMailQueue\Database\Migrator::install();
        delete_option( Options::OPTION_NAME );
        delete_option( TemplateOptions::OPTION_NAME );
        SettingsAutosave::register();
        \TotalMailQueue\Admin\Access::register();
    }

    protected function tearDown(): void {
        unset( $_POST['nonce'], $_POST['key'], $_POST['value'] );
        parent::tearDown();
    }

    public function test_a_toggle_saves_its_key_and_keeps_the_rest_of_the_option(): void {
        update_option( Options::OPTION_NAME, array( 'enabled' => '1', 'email' => 'ops@example.test', 'smtp_debug' => '0' ) );
        $this->asAdmin();

        $payload = $this->post( 'settings:smtp_debug', '1' );

        self::assertTrue( $payload['success'] );
        $stored = get_option( Options::OPTION_NAME );
        self::assertSame( '1', $stored['smtp_debug'] );
        self::assertSame( 'ops@example.test', $stored['email'], 'Autosave merges; it never resets the other settings.' );
        self::assertSame( '1', $stored['enabled'] );
    }

    public function test_a_toggle_can_switch_a_default_on_setting_off(): void {
        $this->asAdmin();

        $this->post( 'settings:block_password_reset', '0' );

        self::assertSame( '0', (string) Options::get()['block_password_reset'] );
    }

    public function test_a_template_toggle_merges_into_the_template_options(): void {
        TemplateOptions::update( array_merge( TemplateOptions::get(), array( 'header_bg' => '#123456' ) ) );
        $this->asAdmin();

        $this->post( 'template:enabled', '0' );

        $stored = TemplateOptions::get();
        self::assertFalse( $stored['enabled'] );
        self::assertSame( '#123456', $stored['header_bg'], 'A partial update must not reset the other template settings.' );
    }

    public function test_the_settings_update_hooks_still_run(): void {
        update_option( Options::OPTION_NAME, array( 'enabled' => '2' ) );
        update_option( AutoBlock::OPTION, time(), false );
        AutoBlock::register();
        update_option( Options::OPTION_NAME, array( 'enabled' => '1' ) );
        self::assertNull( AutoBlock::activeSince(), 'Sanity: leaving Block clears the marker.' );

        update_option( Options::OPTION_NAME, array( 'enabled' => '2' ) );
        update_option( AutoBlock::OPTION, time(), false );
        $this->asAdmin();
        $this->post( 'settings:alert_enabled', '1' );

        self::assertNotNull( AutoBlock::activeSince(), 'Still in Block mode, so the marker stays.' );
        self::assertSame( '1', (string) Options::get()['alert_enabled'] );
    }

    public function test_an_unknown_key_is_refused(): void {
        $this->asAdmin();

        $payload = $this->post( 'settings:tableName', '1' );

        self::assertFalse( $payload['success'] );
        self::assertFalse( get_option( Options::OPTION_NAME ), 'Nothing may be written.' );
    }

    public function test_a_missing_nonce_is_refused(): void {
        $this->asAdmin();

        $payload = $this->post( 'settings:smtp_debug', '1', 'not-a-nonce' );

        self::assertFalse( $payload['success'] );
        self::assertFalse( get_option( Options::OPTION_NAME ) );
    }

    public function test_a_user_who_cannot_manage_is_refused(): void {
        wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

        $payload = $this->post( 'settings:smtp_debug', '1' );

        self::assertFalse( $payload['success'] );
        self::assertFalse( get_option( Options::OPTION_NAME ) );
    }

    public function test_any_value_other_than_one_saves_off(): void {
        update_option( Options::OPTION_NAME, array( 'smtp_debug' => '1' ) );
        $this->asAdmin();

        $this->post( 'settings:smtp_debug', 'yes' );

        self::assertSame( '0', get_option( Options::OPTION_NAME )['smtp_debug'] );
    }

    public function test_an_administrator_grants_a_role_access(): void {
        $this->asAdmin();

        $payload = $this->post( 'access:editor:view', '1' );

        self::assertTrue( $payload['success'] );
        self::assertTrue( \TotalMailQueue\Admin\Access::roleHas( 'editor', \TotalMailQueue\Admin\Access::VIEW ) );

        $this->post( 'access:editor:view', '0' );
        self::assertFalse( \TotalMailQueue\Admin\Access::roleHas( 'editor', \TotalMailQueue\Admin\Access::VIEW ) );
    }

    public function test_a_manager_cannot_change_who_has_access(): void {
        \TotalMailQueue\Admin\Access::setRole( 'editor', \TotalMailQueue\Admin\Access::MANAGE, true );
        wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

        $payload = $this->post( 'access:author:manage', '1' );

        self::assertFalse( $payload['success'] );
        self::assertFalse( \TotalMailQueue\Admin\Access::roleHas( 'author', \TotalMailQueue\Admin\Access::MANAGE ) );
        \TotalMailQueue\Admin\Access::revokeAll();
    }

    public function test_a_manager_saves_settings_toggles(): void {
        \TotalMailQueue\Admin\Access::setRole( 'editor', \TotalMailQueue\Admin\Access::MANAGE, true );
        wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

        $payload = $this->post( 'settings:alert_enabled', '1' );

        self::assertTrue( $payload['success'] );
        \TotalMailQueue\Admin\Access::revokeAll();
    }

    public function test_the_administrator_role_cannot_be_changed(): void {
        $this->asAdmin();

        $payload = $this->post( 'access:administrator:view', '0' );

        self::assertFalse( $payload['success'] );
    }

    private function asAdmin(): void {
        wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
    }

    /**
     * Post one toggle flip and decode the JSON reply.
     *
     * @return array{success:bool,data:mixed}
     */
    private function post( string $key, string $value, ?string $nonce = null ): array {
        $_POST['nonce'] = $nonce ?? wp_create_nonce( SettingsAutosave::NONCE );
        $_POST['key']   = $key;
        $_POST['value'] = $value;
        $this->_last_response = '';
        try {
            $this->_handleAjax( SettingsAutosave::ACTION );
        } catch ( WPAjaxDieContinueException $e ) {
            // wp_send_json_* ends through wp_die().
        } catch ( WPAjaxDieStopException $e ) {
            // Same, on the forced-termination paths.
        }
        $decoded = json_decode( $this->_last_response, true );
        self::assertIsArray( $decoded, 'Expected JSON, got: ' . $this->_last_response );
        return $decoded;
    }
}
