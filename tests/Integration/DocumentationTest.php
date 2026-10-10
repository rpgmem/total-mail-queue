<?php

declare(strict_types=1);

namespace TMQ\Tests\Integration;

use Brain\Monkey\Functions;
use TotalMailQueue\Admin\Documentation;
use TotalMailQueue\Admin\Icons;
use TotalMailQueue\Admin\PluginPage;

/**
 * The Documentation tab: the topic list and the files it names agree, and the
 * page renders an index, every topic and a way back to the index.
 *
 * @covers \TotalMailQueue\Admin\Documentation
 */
final class DocumentationTest extends IntegrationTestCase {

    private const DIR = __DIR__ . '/../../src/admin/docs/';

    /**
     * The topic that documents each tab. Topics are named for what they
     * explain, not for the tab, so the map is written out rather than derived.
     */
    private const TAB_TOPICS = array(
        PluginPage::TAB_SETTINGS  => 'modes-and-queue',
        PluginPage::TAB_LOG       => 'log-and-retention',
        PluginPage::TAB_QUEUE     => 'log-and-retention',
        PluginPage::TAB_SMTP      => 'smtp-accounts',
        PluginPage::TAB_SOURCES   => 'sources',
        PluginPage::TAB_TEMPLATES => 'html-templates',
        PluginPage::TAB_CRON_INFO => 'troubleshooting',
    );

    /**
     * Every TAB_* slug except the documentation itself and its FAQ alias,
     * read from the class so a new tab cannot be left out of the map.
     */
    public function test_every_tab_is_documented_by_a_listed_topic(): void {
        $slugs = array_diff(
            array_values( array_filter( ( new \ReflectionClass( PluginPage::class ) )->getConstants(), static fn ( $name ) => 0 === strpos( $name, 'TAB_' ), ARRAY_FILTER_USE_KEY ) ),
            array( PluginPage::TAB_DOCS, PluginPage::TAB_FAQ )
        );
        $anchors = array_column( Documentation::topics(), 'anchor' );

        self::assertContains( PluginPage::TAB_CRON_INFO, $slugs, 'Reading the tab constants collapsed.' );
        self::assertEqualsCanonicalizing( array_values( $slugs ), array_keys( self::TAB_TOPICS ), 'A tab has no topic mapped to it.' );
        foreach ( self::TAB_TOPICS as $tab => $anchor ) {
            self::assertContains( $anchor, $anchors, $tab . ' maps to a topic that is not listed.' );
        }
    }

    public function test_notices_send_readers_to_the_troubleshooting_topic(): void {
        self::assertContains( Documentation::TROUBLESHOOTING_ANCHOR, array_column( Documentation::topics(), 'anchor' ) );
        self::assertStringNotContainsString( 'tab-faq', (string) file_get_contents( __DIR__ . '/../../src/admin/notices.php' ) );
        self::assertStringNotContainsString( 'tab-faq', (string) file_get_contents( __DIR__ . '/../../src/admin/plugin-row-links.php' ) );
    }

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
        Functions\when( 'wp_nonce_url' )->returnArg();

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
