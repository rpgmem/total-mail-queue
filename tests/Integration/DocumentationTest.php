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
 * No covers annotation: the topics are partials under src/admin/docs/, which no such annotation can name; without one the test counts what it renders.
 */
final class DocumentationTest extends IntegrationTestCase {

    private const DIR = __DIR__ . '/../../src/admin/docs/';

    /**
     * Every TAB_* slug except the documentation itself and its FAQ alias, in
     * declaration order, which is the menu's. Read from the class so a new tab
     * cannot be left out.
     *
     * @return list<string>
     */
    private static function documentedTabs(): array {
        $constants = ( new \ReflectionClass( PluginPage::class ) )->getConstants();
        $tabs      = array();
        foreach ( $constants as $name => $value ) {
            if ( 0 === strpos( $name, 'TAB_' ) && ! in_array( $value, array( PluginPage::TAB_DOCS, PluginPage::TAB_FAQ ), true ) ) {
                $tabs[] = $value;
            }
        }
        return $tabs;
    }

    public function test_every_tab_is_documented_by_exactly_one_group(): void {
        $tabs   = self::documentedTabs();
        $claims = array();
        foreach ( Documentation::groups() as $group ) {
            self::assertArrayHasKey( $group['icon'], Icons::PATHS, $group['title'] );
            self::assertNotSame( '', $group['title'] );
            self::assertNotEmpty( $group['topics'], $group['title'] . ' has no topic.' );
            foreach ( $group['tabs'] as $tab ) {
                $claims[] = $tab;
            }
        }

        self::assertContains( PluginPage::TAB_CRON_INFO, $tabs, 'Reading the tab constants collapsed.' );
        self::assertEqualsCanonicalizing( $tabs, $claims, 'A tab has no group, or two groups claim it.' );
    }

    public function test_groups_follow_the_menu_order(): void {
        $order    = array_flip( self::documentedTabs() );
        $sequence = array();
        foreach ( Documentation::groups() as $group ) {
            foreach ( $group['tabs'] as $tab ) {
                $sequence[] = $order[ $tab ];
            }
        }
        $sorted = $sequence;
        sort( $sorted );

        self::assertGreaterThan( 1, count( $sequence ) );
        self::assertSame( $sorted, $sequence, 'The documentation groups are out of the menu order.' );
    }

    public function test_a_tab_group_is_titled_with_the_tab_label(): void {
        $labels = PluginPage::tabs();
        foreach ( Documentation::groups() as $group ) {
            if ( array() === $group['tabs'] || ! isset( $labels[ $group['tabs'][0] ] ) ) {
                continue;
            }
            self::assertStringContainsString( $labels[ $group['tabs'][0] ], $group['title'] );
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
        Documentation::render( 'https://example.test/banner.png', dirname( __DIR__, 2 ) . '/total-mail-queue.php' );
        $html = (string) ob_get_clean();

        self::assertStringContainsString( 'id="' . Documentation::INDEX_ANCHOR . '"', $html );
        foreach ( Documentation::topics() as $topic ) {
            self::assertStringContainsString( 'href="#' . $topic['anchor'] . '" data-topic="' . $topic['anchor'] . '"', $html, 'Missing from the index.' );
            self::assertStringContainsString( 'id="' . $topic['anchor'] . '"', $html, 'Missing topic card.' );
        }
        self::assertStringContainsString( 'class="tmq-docs-credits"', $html );
        self::assertSame( count( Documentation::topics() ), substr_count( $html, 'href="#' . Documentation::INDEX_ANCHOR . '"' ), 'Every topic ends with a link back to the index.' );
    }

    public function test_credits_name_the_author_and_link_the_repository_from_the_plugin_header(): void {
        $html = Documentation::credits( dirname( __DIR__, 2 ) . '/total-mail-queue.php' );

        self::assertStringContainsString( '>Alex Meusburger</a>', $html );
        self::assertStringContainsString( 'href="https://github.com/rpgmem"', $html );
        self::assertStringContainsString( 'href="https://github.com/rpgmem/total-mail-queue"', $html );
        self::assertStringContainsString( '>github.com/rpgmem/total-mail-queue</a>', $html );
    }
}
