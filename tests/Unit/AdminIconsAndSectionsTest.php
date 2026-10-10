<?php

declare(strict_types=1);

namespace TMQ\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TotalMailQueue\Admin\Icons;
use TotalMailQueue\Admin\PluginPage;
use TotalMailQueue\Admin\SettingsApi;

/**
 * The icon set and the grouped Settings tab.
 *
 * @covers \TotalMailQueue\Admin\Icons
 * @covers \TotalMailQueue\Admin\SettingsApi::sections
 */
final class AdminIconsAndSectionsTest extends TestCase {

    public function test_every_tab_has_an_icon_that_exists(): void {
        foreach ( array_keys( PluginPage::tabs() ) as $slug ) {
            self::assertArrayHasKey( $slug, Icons::TABS, "{$slug} has no icon." );
            self::assertArrayHasKey( Icons::TABS[ $slug ], Icons::PATHS, "{$slug}'s icon is not drawn." );
        }
        // Cron Information only appears while WP-Cron is disabled, but it is
        // still a tab and still needs a drawing.
        self::assertArrayHasKey( Icons::TABS[ PluginPage::TAB_CRON_INFO ], Icons::PATHS );
    }

    public function test_drawings_use_a_closed_vocabulary(): void {
        foreach ( Icons::PATHS as $name => $body ) {
            preg_match_all( '/<([a-z]+)\b/', $body, $tags );
            self::assertNotEmpty( $tags[1], $name );
            self::assertSame( array(), array_values( array_diff( $tags[1], array( 'path', 'circle', 'rect', 'line', 'polyline' ) ) ), "{$name} uses an element outside the set." );
            self::assertStringNotContainsString( 'style', $body, $name );
            self::assertStringNotContainsString( 'fill="#', $body, "{$name} carries a colour of its own." );
        }
    }

    public function test_the_tag_is_decorative_and_unknown_names_print_nothing(): void {
        self::assertSame( '<span class="tmq-icon tmq-icon-log" aria-hidden="true"></span>', Icons::tag( 'log' ) );
        self::assertSame( '', Icons::tag( 'no-such-icon' ) );
    }

    public function test_the_stylesheet_masks_every_icon_in_current_colour(): void {
        $css = Icons::stylesheet();

        self::assertStringContainsString( 'background-color:currentColor', $css );
        foreach ( array_keys( Icons::PATHS ) as $name ) {
            self::assertStringContainsString( '.tmq-icon-' . $name . '{--tmq-icon:url("data:image/svg+xml,', $css );
        }
        self::assertStringNotContainsString( '<svg', $css, 'The SVG must be URL-encoded inside url().' );
    }

    public function test_settings_sections_keep_every_field_once_and_draw_an_icon(): void {
        $fields = array();
        foreach ( SettingsApi::sections() as $id => $section ) {
            self::assertArrayHasKey( $section['icon'], Icons::PATHS, "{$id} names an icon that is not drawn." );
            self::assertNotEmpty( $section['fields'], $id );
            foreach ( $section['fields'] as $field_id => $spec ) {
                self::assertTrue( method_exists( SettingsApi::class, $spec[1] ), "{$field_id} renders through a missing method." );
                $fields[] = $field_id;
            }
        }

        $expected = array(
            'wp_tmq_status', 'wp_tmq_queue', 'wp_tmq_send_method', 'wp_tmq_retry', 'wp_tmq_default_sender',
            'wp_tmq_log', 'wp_tmq_alert_status', 'wp_tmq_sensitivity',
            'wp_tmq_smtp_timeout', 'wp_tmq_smtp_debug', 'wp_tmq_cron_lock_ttl', 'wp_tmq_dark_mode',
        );
        sort( $expected );
        sort( $fields );
        self::assertSame( $expected, $fields, 'A field was lost or duplicated by the grouping.' );
    }
}
