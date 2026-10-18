<?php

declare(strict_types=1);

namespace TMQ\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TotalMailQueue\Admin\Icons;
use TotalMailQueue\Admin\Menu;

/**
 * The CSS the PHP registries generate lives in files, not inline.
 *
 * The icon masks, the screenshot viewer's copy of its icons and the menu glyph
 * are produced by {@see Icons::stylesheet()}, {@see Icons::masks()} and
 * {@see Menu::menuStylesheet()}, which stay the single source; the files
 * carry a copy between markers so the browser can cache it and the build can
 * minify it. This test fails when a copy and its source disagree.
 *
 * After changing a drawing or the menu rules, regenerate the copies with
 * `TMQ_UPDATE_GENERATED_CSS=1 vendor/bin/phpunit --filter GeneratedCss`.
 *
 * @covers \TotalMailQueue\Admin\Icons
 * @covers \TotalMailQueue\Admin\Menu
 */
final class GeneratedCssTest extends TestCase {

    private const ROOT = __DIR__ . '/../..';

    /**
     * Each generated block: file, marker name, source.
     *
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function blocks(): array {
        return array(
            'icons'  => array( 'assets/css/admin.css', 'tmq-icons', Icons::stylesheet() ),
            'menu'   => array( 'assets/css/admin-chrome.css', 'tmq-menu', Menu::menuStylesheet() ),
            'viewer' => array( 'assets/css/screenshots.css', 'tmq-viewer-icons', Icons::masks( Icons::VIEWER ) ),
        );
    }

    /**
     * @dataProvider blocks
     */
    public function test_the_file_carries_what_the_source_generates( string $file, string $marker, string $css ): void {
        $path     = self::ROOT . '/' . $file;
        $contents = (string) file_get_contents( $path );
        $pattern  = '#(/\* ' . $marker . ':begin\b[^*]*\*/\n)(.*?)(\n/\* ' . $marker . ':end \*/)#s';

        self::assertSame( 1, preg_match( $pattern, $contents, $m ), "{$file} has no {$marker}:begin / {$marker}:end block." );

        if ( getenv( 'TMQ_UPDATE_GENERATED_CSS' ) ) {
            file_put_contents( $path, (string) preg_replace( $pattern, '${1}' . str_replace( array( '\\', '$' ), array( '\\\\', '\\$' ), $css ) . '${3}', $contents ) );
            $m[2] = $css;
        }

        self::assertSame( $css, $m[2], "{$file}: the {$marker} block is stale. Run TMQ_UPDATE_GENERATED_CSS=1 vendor/bin/phpunit --filter GeneratedCss." );
    }

    public function test_no_php_attaches_css_inline(): void {
        $found = array();
        $files = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( self::ROOT . '/src', \FilesystemIterator::SKIP_DOTS ) );
        foreach ( $files as $file ) {
            if ( 'php' !== $file->getExtension() ) {
                continue;
            }
            if ( str_contains( (string) file_get_contents( $file->getPathname() ), 'wp_add_inline_style' ) ) {
                $found[] = $file->getFilename();
            }
        }
        self::assertSame( array(), $found, 'CSS belongs in a file under assets/css, not in wp_add_inline_style().' );
        self::assertGreaterThan( 0, iterator_count( $files ), 'The scan read no file.' );
    }
}
