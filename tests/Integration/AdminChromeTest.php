<?php

declare(strict_types=1);

namespace TMQ\Tests\Integration;

use Brain\Monkey\Functions;
use TotalMailQueue\Admin\Assets;
use TotalMailQueue\Admin\Menu;
use TotalMailQueue\Admin\PluginPage;

/**
 * The admin chrome: the tab list, the menu built from it, the sidebar glyph,
 * and the script that applies the Dark Mode setting.
 *
 * @covers \TotalMailQueue\Admin\Menu
 * @covers \TotalMailQueue\Admin\PluginPage
 * @covers \TotalMailQueue\Admin\Assets
 */
final class AdminChromeTest extends IntegrationTestCase {

    public function test_tabs_start_with_settings_and_end_with_the_documentation(): void {
        $slugs = array_keys( PluginPage::tabs() );

        self::assertSame( PluginPage::TAB_SETTINGS, $slugs[0] );
        self::assertSame( PluginPage::TAB_DOCS, end( $slugs ) );
        self::assertNotContains( PluginPage::TAB_FAQ, $slugs, 'The FAQ became the documentation.' );
        self::assertSame( Menu::SLUG, PluginPage::TAB_SETTINGS, 'The Settings tab is the top-level page.' );
    }

    public function test_every_tab_is_registered_as_a_submenu_of_the_one_menu(): void {
        $menus    = array();
        $submenus = array();
        Functions\when( 'add_menu_page' )->alias(
            static function ( $page_title, $menu_title, $cap, $slug, $cb, $icon ) use ( &$menus ) {
                $menus[] = array( $slug, $cap, $icon );
                return 'toplevel_page_' . $slug;
            }
        );
        Functions\when( 'add_submenu_page' )->alias(
            static function ( $parent, $page_title, $menu_title, $cap, $slug ) use ( &$submenus ) {
                $submenus[ $slug ] = array( $parent, $cap );
                return $slug;
            }
        );

        Menu::addMenuItems();

        self::assertSame( array( array( Menu::SLUG, \TotalMailQueue\Admin\Access::MANAGE, 'none' ) ), $menus );
        self::assertSame( array_merge( array_keys( PluginPage::tabs() ), array( PluginPage::TAB_FAQ ) ), array_keys( $submenus ), 'Every tab, plus the old FAQ slug kept as an alias.' );
        $expected = array(
            PluginPage::TAB_SETTINGS  => \TotalMailQueue\Admin\Access::MANAGE,
            PluginPage::TAB_TEMPLATES => \TotalMailQueue\Admin\Access::MANAGE,
            PluginPage::TAB_SMTP      => 'manage_options',
        );
        foreach ( $submenus as $slug => $spec ) {
            self::assertSame( array( Menu::SLUG, $expected[ $slug ] ?? \TotalMailQueue\Admin\Access::VIEW ), $spec, $slug );
        }
    }

    public function test_the_menu_stylesheet_draws_the_glyph_and_hides_the_submenu(): void {
        $css  = Menu::menuStylesheet();
        $item = '#adminmenu #toplevel_page_' . Menu::SLUG;

        self::assertStringContainsString( $item . ' div.wp-menu-image::before{', $css );
        self::assertStringContainsString( 'background-color:currentColor', $css );
        self::assertStringContainsString( 'mask:url("data:image/svg+xml;base64,', $css );
        self::assertStringContainsString( $item . ' .wp-submenu{display:none}', $css );
        self::assertStringContainsString( '@media (forced-colors:active)', $css );
    }

    public function test_the_chrome_sheet_loads_after_cores_admin_menu_sheet(): void {
        $enqueued = array();
        Functions\when( 'plugin_dir_url' )->justReturn( 'https://example.test/wp-content/plugins/total-mail-queue/' );
        Functions\when( 'wp_enqueue_style' )->alias(
            static function ( $handle, $src, $deps ) use ( &$enqueued ) {
                $enqueued[ $handle ] = array( $src, $deps );
                return true;
            }
        );

        Menu::enqueueChromeStyle();

        self::assertSame(
            array( 'wp_tmq_chrome' => array( 'https://example.test/wp-content/plugins/total-mail-queue/assets/css/admin-chrome.min.css', array( 'admin-menu' ) ) ),
            $enqueued
        );
    }

    /**
     * @dataProvider darkModes
     */
    public function test_the_dark_mode_script_carries_the_setting( string $stored, string $applied ): void {
        $script = Assets::darkModeScript( $stored );

        self::assertStringEndsWith( '})("' . $applied . '");', $script );
        self::assertStringContainsString( 'classList.toggle("tmq-dark-mode"', $script );
    }

    /**
     * @return array<string,array{0:string,1:string}>
     */
    public function darkModes(): array {
        return array(
            'off'           => array( 'off', 'off' ),
            'on'            => array( 'on', 'on' ),
            'auto'          => array( 'auto', 'auto' ),
            'unknown → off' => array( 'sepia', 'off' ),
            'quote → off'   => array( 'on");alert(1);("', 'off' ),
        );
    }

    public function test_only_auto_listens_to_the_operating_system(): void {
        $script = Assets::darkModeScript( 'auto' );

        self::assertStringContainsString( '(prefers-color-scheme: dark)', $script );
        self::assertStringContainsString( 'mode==="auto"&&query&&query.addEventListener', $script );
    }
}
