<?php

declare(strict_types=1);

namespace TMQ\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The admin stylesheet's colour contract.
 *
 * - Every colour is a `--tmq-*` token: a colour literal outside the two token
 *   blocks is a colour the dark theme cannot reach.
 * - The dark block redefines colour tokens only, and only tokens the light
 *   block declares — dark is light overridden, never a second set.
 * - Every text/background pair the sheet paints measures at least 4.5:1 (WCAG
 *   AA for body text) in both themes.
 *
 * Reads the sheet as text; no WordPress needed.
 *
 * @coversNothing
 */
final class ColorTokensTest extends TestCase {

    private const SHEET = __DIR__ . '/../../assets/css/admin.css';

    /**
     * Text token => background token, for every pair a rule paints together.
     */
    private const PAIRS = array(
        array( '--tmq-text', '--tmq-bg' ),
        array( '--tmq-text', '--tmq-bg-alt' ),
        array( '--tmq-text', '--tmq-bg-card' ),
        array( '--tmq-text', '--tmq-bg-input' ),
        array( '--tmq-text-muted', '--tmq-bg-card' ),
        array( '--tmq-text-muted', '--tmq-bg-alt' ),
        array( '--tmq-primary', '--tmq-bg-card' ),
        array( '--tmq-primary', '--tmq-primary-light' ),
        array( '--tmq-text-on-primary', '--tmq-primary' ),
        array( '--tmq-text-on-primary', '--tmq-primary-hover' ),
        array( '--tmq-danger-text', '--tmq-bg-card' ),
        array( '--tmq-text-on-signal', '--tmq-success' ),
        array( '--tmq-text-on-signal', '--tmq-danger' ),
        array( '--tmq-success-text', '--tmq-success-bg' ),
        array( '--tmq-warning-text', '--tmq-warning-bg' ),
        array( '--tmq-danger-badge-text', '--tmq-danger-bg' ),
        array( '--tmq-info-text', '--tmq-info-bg' ),
        array( '--tmq-neutral-text', '--tmq-neutral-bg' ),
        array( '--tmq-inverse-text', '--tmq-inverse-bg' ),
        array( '--tmq-code-block-text', '--tmq-code-block-bg' ),
    );

    /**
     * Properties whose value is (or carries) a colour.
     */
    private const COLOR_PROPERTIES = '/^(color|background(-color)?|border(-(top|right|bottom|left))?(-color)?|outline(-color)?|box-shadow|fill|stroke)$/';

    private static function css(): string {
        return (string) preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( self::SHEET ) );
    }

    /**
     * Declarations of one token block, `name => value`.
     *
     * @return array<string,string>
     */
    private static function block( string $selector ): array {
        if ( ! preg_match( '/(?:^|\})\s*' . preg_quote( $selector, '/' ) . '\s*\{([^}]*)\}/', self::css(), $m ) ) {
            return array();
        }
        preg_match_all( '/(--[\w-]+|color-scheme)\s*:\s*([^;]+);/', $m[1], $decls, PREG_SET_ORDER );
        $out = array();
        foreach ( $decls as $d ) {
            $out[ $d[1] ] = trim( $d[2] );
        }
        return $out;
    }

    /**
     * @return array<string,string> The light tokens merged with the dark ones.
     */
    private static function palette( string $theme ): array {
        $light = self::block( ':root' );
        return 'dark' === $theme ? array_merge( $light, self::block( ':root.tmq-dark-mode' ) ) : $light;
    }

    private static function isColor( string $value ): bool {
        return 1 === preg_match( '/^(#[0-9a-f]{3,8}|rgba?\(.*\))$/i', $value );
    }

    /**
     * @return array{0:float,1:float,2:float}
     */
    private static function rgb( string $hex ): array {
        $hex = ltrim( $hex, '#' );
        if ( 3 === strlen( $hex ) ) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        return array( hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
    }

    private static function luminance( string $hex ): float {
        $lin = array_map(
            static function ( $c ): float {
                $c /= 255;
                return $c <= 0.03928 ? $c / 12.92 : ( ( $c + 0.055 ) / 1.055 ) ** 2.4;
            },
            self::rgb( $hex )
        );
        return 0.2126 * $lin[0] + 0.7152 * $lin[1] + 0.0722 * $lin[2];
    }

    private static function contrast( string $a, string $b ): float {
        $la = self::luminance( $a );
        $lb = self::luminance( $b );
        return ( max( $la, $lb ) + 0.05 ) / ( min( $la, $lb ) + 0.05 );
    }

    public function test_no_colour_literal_outside_the_token_blocks(): void {
        $css = (string) preg_replace( '/(?:^|(?<=\}))\s*:root(\.tmq-dark-mode)?\s*\{[^}]*\}/', '', self::css() );

        preg_match_all( '/([\w-]+)\s*:\s*([^;{}]+)[;}]/', $css, $decls, PREG_SET_ORDER );
        $literals = array();
        foreach ( $decls as $d ) {
            if ( ! preg_match( self::COLOR_PROPERTIES, $d[1] ) ) {
                continue;
            }
            $value = (string) preg_replace( '/var\([^)]*\)/', '', $d[2] );
            if ( preg_match( '/#[0-9a-f]{3,8}\b|\b(rgba?|hsla?)\(|\b(white|black|red|green|blue|orange|yellow|purple|gray|grey|silver|navy|maroon)\b/i', $value ) ) {
                $literals[] = trim( $d[0] );
            }
        }

        self::assertGreaterThan( 50, count( $decls ), 'Reading the declarations collapsed.' );
        self::assertSame( array(), $literals, 'Read a --tmq-* token instead.' );
    }

    public function test_the_dark_block_redefines_existing_colour_tokens_only(): void {
        $light = self::block( ':root' );
        $dark  = self::block( ':root.tmq-dark-mode' );

        self::assertNotEmpty( $light );
        self::assertNotEmpty( $dark );
        unset( $dark['color-scheme'] );

        self::assertSame( array(), array_values( array_diff( array_keys( $dark ), array_keys( $light ) ) ), 'A dark token the light theme does not declare.' );
        foreach ( $dark as $name => $value ) {
            self::assertTrue(
                self::isColor( $value ) || 0 === strpos( $value, 'url(' ),
                "{$name} is not a colour; the dark block overrides colours only."
            );
        }
    }

    public function test_every_light_colour_token_is_redefined_for_dark(): void {
        $light = array_filter( self::block( ':root' ), array( self::class, 'isColor' ) );
        $dark  = self::block( ':root.tmq-dark-mode' );

        self::assertSame( array(), array_values( array_diff( array_keys( $light ), array_keys( $dark ) ) ), 'A colour the dark theme would inherit from the light one.' );
    }

    /**
     * @return array<string,array{0:string}>
     */
    public function themes(): array {
        return array(
            'light' => array( 'light' ),
            'dark'  => array( 'dark' ),
        );
    }

    /**
     * @dataProvider themes
     */
    public function test_every_painted_pair_meets_aa( string $theme ): void {
        $palette = self::palette( $theme );
        $failing = array();

        foreach ( self::PAIRS as $pair ) {
            list( $fg, $bg ) = $pair;
            self::assertArrayHasKey( $fg, $palette, "{$fg} is not declared." );
            self::assertArrayHasKey( $bg, $palette, "{$bg} is not declared." );
            $ratio = self::contrast( $palette[ $fg ], $palette[ $bg ] );
            if ( $ratio < 4.5 ) {
                $failing[] = sprintf( '%s on %s: %.2f:1', $fg, $bg, $ratio );
            }
        }

        self::assertSame( array(), $failing );
    }
}
