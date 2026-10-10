<?php

declare(strict_types=1);

namespace TMQ\Tests\Functional;

use TotalMailQueue\Admin\Access;
use TotalMailQueue\Templates\Engine;
use TotalMailQueue\Templates\Options as TemplateOptions;
use TotalMailQueue\Templates\Preview;
use WP_Ajax_UnitTestCase;
use WPAjaxDieContinueException;
use WPAjaxDieStopException;

/**
 * The live preview on the Templates tab.
 *
 * @covers \TotalMailQueue\Templates\Preview
 * @covers \TotalMailQueue\Templates\Engine::render
 */
final class TemplatePreviewTest extends WP_Ajax_UnitTestCase {

    protected function setUp(): void {
        parent::setUp();
        \TotalMailQueue\Database\Migrator::install();
        delete_option( TemplateOptions::OPTION_NAME );
        Preview::register();
        Access::register();
    }

    protected function tearDown(): void {
        unset( $_POST['nonce'], $_POST['tmq_template'] );
        Access::revokeAll();
        parent::tearDown();
    }

    public function test_the_preview_renders_unsaved_values_and_stores_nothing(): void {
        $this->asAdmin();

        $payload = $this->post(
            array(
                'header_bg'         => '#123abc',
                'footer_text'       => 'Footer for {site_title}',
                'footer_powered_by' => '1',
            )
        );

        self::assertTrue( $payload['success'] );
        $html = $payload['data']['html'];
        self::assertStringContainsString( '#123abc', $html, 'The posted header colour is used.' );
        self::assertStringContainsString( 'Footer for ' . get_bloginfo( 'name' ), $html, 'Footer tokens are resolved.' );
        self::assertStringContainsString( 'Sent via Total Mail Queue', $html );
        self::assertFalse( get_option( TemplateOptions::OPTION_NAME ), 'A preview never saves.' );
    }

    public function test_a_switched_off_toggle_is_absent_from_the_post_and_reads_as_off(): void {
        $this->asAdmin();

        $payload = $this->post( array( 'footer_text' => 'Only this' ) );

        self::assertStringNotContainsString( 'Sent via Total Mail Queue', $payload['data']['html'] );
    }

    public function test_invalid_values_fall_back_to_the_defaults(): void {
        $this->asAdmin();

        $payload = $this->post( array( 'header_bg' => 'red;background:url(x)' ) );

        self::assertStringContainsString( TemplateOptions::defaults()['header_bg'], $payload['data']['html'] );
        self::assertStringNotContainsString( 'url(x)', $payload['data']['html'] );
    }

    public function test_the_body_is_wrapped_even_when_the_engine_is_off(): void {
        TemplateOptions::update( array( 'enabled' => false ) );
        self::assertFalse( TemplateOptions::isEnabled(), 'Sanity: wrapping of real mail is off.' );
        $this->asAdmin();

        $payload = $this->post( array( 'header_bg' => '#0a0b0c' ) );

        self::assertStringContainsString( '#0a0b0c', $payload['data']['html'], 'The preview shows the template whether or not wrapping is on.' );
    }

    public function test_a_missing_nonce_is_refused(): void {
        $this->asAdmin();

        $payload = $this->post( array(), 'not-a-nonce' );

        self::assertFalse( $payload['success'] );
    }

    public function test_a_viewer_cannot_render_the_preview(): void {
        Access::setRole( 'editor', Access::VIEW, true );
        wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

        $payload = $this->post( array() );

        self::assertFalse( $payload['success'] );
    }

    public function test_a_manager_can_render_the_preview(): void {
        Access::setRole( 'editor', Access::MANAGE, true );
        wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

        $payload = $this->post( array() );

        self::assertTrue( $payload['success'] );
    }

    public function test_render_uses_the_options_it_is_given_not_the_stored_ones(): void {
        TemplateOptions::update( array( 'header_bg' => '#111111' ) );
        $options              = TemplateOptions::get();
        $options['header_bg'] = '#222222';

        $html = Engine::render( 'Hello', array( 'subject' => 'S' ), $options );

        self::assertStringContainsString( '#222222', $html );
        self::assertStringNotContainsString( '#111111', $html );
    }

    public function test_sending_still_wraps_with_the_stored_options(): void {
        TemplateOptions::update( array( 'header_bg' => '#333333' ) );

        $args = Engine::apply( array( 'to' => 'a@example.test', 'subject' => 'S', 'message' => 'Hello' ) );

        self::assertStringContainsString( '#333333', $args['message'] );
    }

    public function test_the_tab_paints_the_saved_template_into_a_sandboxed_frame(): void {
        $this->asAdmin();
        TemplateOptions::update( array( 'header_bg' => '#445566' ) );

        ob_start();
        \TotalMailQueue\Admin\Pages\TemplatesPage::render();
        $page = (string) ob_get_clean();

        self::assertMatchesRegularExpression( '/<iframe[^>]+sandbox=""/', $page, 'The email cannot run script or navigate.' );
        self::assertStringContainsString( '#445566', $page );
        // The email's own entities survive the attribute: `&quot;` in its
        // style attributes must reach the frame as `&quot;`, not as a raw
        // quote that ends the attribute early.
        self::assertStringContainsString( '&amp;quot;Segoe UI&amp;quot;', $page );
    }

    private function asAdmin(): void {
        wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
    }

    /**
     * Post the form fields and decode the JSON reply.
     *
     * @param array<string,string> $fields Template fields.
     * @return array{success:bool,data:mixed}
     */
    private function post( array $fields, ?string $nonce = null ): array {
        $_POST['nonce']        = $nonce ?? wp_create_nonce( Preview::NONCE );
        $_POST['tmq_template'] = $fields;
        $this->_last_response  = '';
        try {
            $this->_handleAjax( Preview::ACTION );
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
