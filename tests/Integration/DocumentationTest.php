<?php

declare(strict_types=1);

namespace TMQ\Tests\Integration;

use Brain\Monkey\Functions;
use TotalMailQueue\Admin\Documentation;
use TotalMailQueue\Admin\Icons;

/**
 * The Documentation tab: the topic list and the files it names agree, and the
 * page renders an index, every topic and a way back to the index.
 *
 * @covers \TotalMailQueue\Admin\Documentation
 */
final class DocumentationTest extends IntegrationTestCase {

    private const DIR = __DIR__ . '/../../src/admin/docs/';

    public function test_every_topic_names_an_existing_file_a_drawn_icon_and_a_unique_anchor(): void {
        $anchors = array();
        foreach ( Documentation::topics() as $topic ) {
            self::assertFileExists( self::DIR . $topic['file'] );
            self::assertArrayHasKey( $topic['icon'], Icons::PATHS, $topic['anchor'] );
            self::assertMatchesRegularExpression( '/^[a-z][a-z0-9-]*$/', $topic['anchor'] );
            $anchors[] = $topic['anchor'];
        }
        self::assertSame( $anchors, array_unique( $anchors ), 'Two topics share an anchor.' );
        self::assertNotContains( Documentation::INDEX_ANCHOR, $anchors );
    }

    public function test_every_file_in_the_docs_folder_is_a_listed_topic(): void {
        $listed  = array_column( Documentation::topics(), 'file' );
        $on_disk = array_map( 'basename', (array) glob( self::DIR . '*.php' ) );
        sort( $listed );
        sort( $on_disk );

        self::assertNotEmpty( $on_disk, 'Reading the docs folder collapsed.' );
        self::assertSame( $on_disk, $listed, 'A topic file is never shown, or a listed one is missing.' );
    }

    public function test_the_page_has_an_index_every_topic_and_a_way_back(): void {
        Functions\when( 'esc_html_e' )->alias( static function ( $text ) { echo $text; } );
        Functions\when( 'wp_kses_post' )->returnArg();
        Functions\when( 'esc_attr__' )->returnArg();

        ob_start();
        Documentation::render( 'https://example.test/banner.png' );
        $html = (string) ob_get_clean();

        self::assertStringContainsString( 'id="' . Documentation::INDEX_ANCHOR . '"', $html );
        foreach ( Documentation::topics() as $topic ) {
            self::assertStringContainsString( '<a href="#' . $topic['anchor'] . '">', $html, 'Missing from the index.' );
            self::assertStringContainsString( 'id="' . $topic['anchor'] . '"', $html, 'Missing topic card.' );
        }
        self::assertSame( count( Documentation::topics() ), substr_count( $html, 'href="#' . Documentation::INDEX_ANCHOR . '"' ), 'Every topic ends with a link back to the index.' );
    }
}
