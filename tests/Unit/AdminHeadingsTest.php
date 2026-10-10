<?php

declare(strict_types=1);

namespace TMQ\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Every heading the plugin prints on its own screens carries an icon.
 *
 * Headings go through `Icons::heading()`, so a literal `<h2>`/`<h3>` in the
 * admin code is a heading that skipped it. That is how the Templates, SMTP,
 * Sources and Cron Information tabs shipped bare headings beside the iconed
 * Settings cards. The documentation partials are outside the scan: their
 * subheadings sit inside a topic card whose own title carries the icon.
 *
 * @coversNothing
 */
final class AdminHeadingsTest extends TestCase {

    private const DIR = __DIR__ . '/../../src/admin/';

    /**
     * Literal headings that are allowed, each with why.
     */
    private const ALLOWED = array(
        // The page title: the plugin's wordmark, not a section.
        'class="tmq-title"',
        // The Export / Import group label over two cards that carry their own icons.
        'class="tmq-section-title"',
    );

    /**
     * Files whose headings are WordPress chrome, not the plugin's screens.
     */
    private const SKIP_FILES = array(
        // Admin notices printed on every wp-admin screen, in core's notice markup.
        'notices.php',
    );

    public function test_no_admin_screen_prints_a_heading_without_an_icon(): void {
        $files = $this->files();
        $bare  = array();

        foreach ( $files as $file ) {
            $src = (string) file_get_contents( $file );
            if ( in_array( basename( $file ), self::SKIP_FILES, true ) ) {
                continue;
            }
            foreach ( explode( "\n", $src ) as $i => $line ) {
                if ( ! preg_match( '/<h[1-6]\b/', $line ) ) {
                    continue;
                }
                foreach ( self::ALLOWED as $allowed ) {
                    if ( false !== strpos( $line, $allowed ) ) {
                        continue 2;
                    }
                }
                $bare[] = substr( $file, strlen( self::DIR ) ) . ':' . ( $i + 1 );
            }
        }

        self::assertSame( array(), $bare, 'A heading without an icon: print it with Icons::heading().' );
    }

    /**
     * Every PHP file under src/admin except the documentation partials,
     * recounted with a second traversal so a collapsed scan fails.
     *
     * @return list<string>
     */
    private function files(): array {
        $files = array();
        $it    = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( self::DIR, \FilesystemIterator::SKIP_DOTS ) );
        foreach ( $it as $file ) {
            $path = (string) $file;
            if ( 'php' === $file->getExtension() && false === strpos( $path, '/docs/' ) ) {
                $files[] = $path;
            }
        }

        $recount = count( (array) glob( self::DIR . '*.php' ) ) + count( (array) glob( self::DIR . '*/*.php' ) ) - count( (array) glob( self::DIR . 'docs/*.php' ) );
        self::assertSame( $recount, count( $files ), 'The two traversals disagree: the scan is reading the wrong set.' );

        return $files;
    }
}
