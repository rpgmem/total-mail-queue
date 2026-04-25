<?php

declare(strict_types=1);

namespace TMQ\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * @covers ::wp_tmq_render_html_for_display
 */
final class RenderHtmlForDisplayTest extends TestCase {

    public function test_passes_through_html_without_base64_inline_data(): void {
        $html = '<p>Hello <strong>world</strong></p>';

        self::assertSame( $html, wp_tmq_render_html_for_display( $html ) );
    }

    public function test_redacts_inline_base64_image_data_in_double_quoted_src(): void {
        $html = '<img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAA"/>';

        $rendered = wp_tmq_render_html_for_display( $html );

        self::assertStringNotContainsString( 'iVBORw0KGgo', $rendered );
        self::assertStringContainsString( ';base64, [...] "', $rendered );
    }

    public function test_redacts_inline_base64_image_data_in_single_quoted_src(): void {
        $html = "<img src='data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAA'/>";

        $rendered = wp_tmq_render_html_for_display( $html );

        self::assertStringNotContainsString( 'iVBORw0KGgo', $rendered );
        self::assertStringContainsString( ";base64, [...] '", $rendered );
    }

    public function test_redacts_multiple_inline_payloads_independently(): void {
        $html  = '<img src="data:image/png;base64,AAAA"/>';
        $html .= '<img src="data:image/jpeg;base64,BBBB"/>';

        $rendered = wp_tmq_render_html_for_display( $html );

        self::assertStringNotContainsString( 'AAAA', $rendered );
        self::assertStringNotContainsString( 'BBBB', $rendered );
        self::assertSame( 2, substr_count( $rendered, '[...]' ) );
    }
}
