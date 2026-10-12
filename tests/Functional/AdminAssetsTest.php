<?php

declare(strict_types=1);

namespace TMQ\Tests\Functional;

use TotalMailQueue\Admin\Assets;
use TotalMailQueue\Admin\PluginPage;
use TotalMailQueue\Admin\PluginRowLinks;

/**
 * Which stylesheets and scripts load on which screen, the configuration the
 * admin script receives, and the quick links on the Plugins screen.
 *
 * @covers \TotalMailQueue\Admin\Assets
 * @covers \TotalMailQueue\Admin\PluginRowLinks
 */
final class AdminAssetsTest extends FunctionalTestCase {

    protected function setUp(): void {
        parent::setUp();
        wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
        $GLOBALS['wp_scripts'] = new \WP_Scripts();
        $GLOBALS['wp_styles']  = new \WP_Styles();
    }

    protected function tearDown(): void {
        $_GET = array();
        remove_action( 'admin_footer', array( Assets::class, 'printBackToTop' ) );
        set_current_screen( 'front' );
        parent::tearDown();
    }

    private function enqueueOn( string $screen, string $page = '' ): void {
        set_current_screen( $screen );
        if ( '' !== $page ) {
            $_GET['page'] = $page;
        }
        Assets::enqueue();
    }

    public function test_nothing_loads_outside_the_plugin_screens(): void {
        $this->enqueueOn( 'dashboard' );

        self::assertFalse( wp_style_is( 'wp_tmq_style', 'enqueued' ) );
        self::assertFalse( wp_script_is( 'wp_tmq_script', 'enqueued' ) );
    }

    public function test_a_plugin_screen_gets_the_base_bundle_and_its_configuration(): void {
        $this->setPluginOptions( array( 'dark_mode' => 'on' ) );
        $this->enqueueOn( 'toplevel_page_wp_tmq_mail_queue', 'wp_tmq_mail_queue' );

        self::assertTrue( wp_style_is( 'wp_tmq_style', 'enqueued' ) );
        self::assertTrue( wp_script_is( 'wp_tmq_script', 'enqueued' ) );
        self::assertTrue( wp_script_is( 'wp_tmq_autosave', 'enqueued' ) );
        self::assertFalse( wp_script_is( 'wp_tmq_docs', 'enqueued' ) );
        self::assertFalse( wp_script_is( 'wp_tmq_templates', 'enqueued' ) );
        self::assertSame( 10, has_action( 'admin_footer', array( Assets::class, 'printBackToTop' ) ) );

        $config = implode( '', wp_scripts()->get_data( 'wp_tmq_script', 'before' ) );
        self::assertStringContainsString( 'tmq.restNonce = "', $config );
        self::assertStringContainsString( 'tmq.testSmtpNonce = "', $config );
        self::assertStringContainsString( 'tmq.autosave = {', $config );

        $theme = implode( '', wp_scripts()->get_data( 'wp_tmq_theme', 'after' ) );
        self::assertStringContainsString( '})("on");', $theme );
    }

    public function test_the_documentation_screens_add_the_search_script(): void {
        $this->enqueueOn( 'total-mail-queue_page_' . PluginPage::TAB_DOCS, PluginPage::TAB_DOCS );

        self::assertTrue( wp_script_is( 'wp_tmq_docs', 'enqueued' ) );
    }

    public function test_the_templates_screen_adds_the_colour_picker_and_its_script(): void {
        $this->enqueueOn( 'total-mail-queue_page_wp_tmq_mail_queue-tab-templates', 'wp_tmq_mail_queue-tab-templates' );

        self::assertTrue( wp_script_is( 'wp_tmq_templates', 'enqueued' ) );
        self::assertTrue( wp_style_is( 'wp-color-picker', 'enqueued' ) );
    }

    public function test_an_unknown_dark_mode_value_is_treated_as_off(): void {
        self::assertStringEndsWith( '})("off");', Assets::darkModeScript( 'sepia' ) );
    }

    public function test_the_back_to_top_link_is_a_plain_anchor(): void {
        ob_start();
        Assets::printBackToTop();
        $html = (string) ob_get_clean();

        self::assertStringContainsString( 'href="#tmq-top"', $html );
        self::assertStringContainsString( 'screen-reader-text', $html );
    }

    public function test_register_hooks_the_enqueue(): void {
        Assets::register();

        self::assertSame( 10, has_action( 'admin_enqueue_scripts', array( Assets::class, 'enqueue' ) ) );
    }

    public function test_the_plugins_screen_row_keeps_its_links_and_gains_ours(): void {
        $links = PluginRowLinks::addLinks( array( '<a href="#">Deactivate</a>' ) );

        self::assertCount( 6, $links );
        self::assertSame( '<a href="#">Deactivate</a>', $links[0] );
        self::assertStringContainsString( 'page=wp_tmq_mail_queue-tab-smtp', $links[4] );

        PluginRowLinks::register();
        self::assertSame( 10, has_filter( 'plugin_action_links_' . plugin_basename( \TotalMailQueue\Plugin::container()->get( 'plugin.file' ) ), array( PluginRowLinks::class, 'addLinks' ) ) );
    }
}
