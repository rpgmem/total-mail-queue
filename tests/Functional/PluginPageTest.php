<?php

declare(strict_types=1);

namespace TMQ\Tests\Functional;

use TotalMailQueue\Admin\Access;
use TotalMailQueue\Admin\PluginPage;
use TotalMailQueue\Admin\SettingsApi;

/**
 * The page router: which tab each slug opens, what the tab column shows the
 * current user, the Settings tab's administrator-only half, and the
 * Retention tab's flash notice and test-mail link.
 *
 * @covers \TotalMailQueue\Admin\PluginPage
 */
final class PluginPageTest extends FunctionalTestCase {

    protected function setUp(): void {
        parent::setUp();
        wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
        global $wp_settings_sections, $wp_settings_fields;
        $wp_settings_sections = array();
        $wp_settings_fields   = array();
        SettingsApi::init();
    }

    protected function tearDown(): void {
        $_GET  = array();
        $_POST = array();
        Access::revokeAll();
        parent::tearDown();
    }

    private function open( string $page, array $query = array() ): string {
        $_GET = array_merge( array( 'page' => $page ), $query );
        ob_start();
        PluginPage::render();
        return (string) ob_get_clean();
    }

    public function test_the_settings_tab_shows_the_form_access_and_export_import_to_an_administrator(): void {
        $html = $this->open( PluginPage::TAB_SETTINGS );

        self::assertStringContainsString( 'id="tmq-top"', $html );
        self::assertStringContainsString( 'tmq-logo--dark', $html );
        self::assertStringContainsString( 'action="options.php"', $html );
        self::assertStringContainsString( 'tmq-access-card', $html );
        self::assertStringContainsString( 'name="wp_tmq_export"', $html );
        self::assertStringContainsString( 'name="wp_tmq_import_file"', $html );
        self::assertMatchesRegularExpression( '/page=wp_tmq_mail_queue"[^>]*is-active[^>]*aria-current="page"/', $html );
    }

    public function test_a_manager_role_gets_the_settings_form_but_not_the_administrator_half(): void {
        Access::setRole( 'editor', Access::MANAGE, true );
        wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

        $html = $this->open( PluginPage::TAB_SETTINGS );

        self::assertStringContainsString( 'action="options.php"', $html );
        self::assertStringNotContainsString( 'tmq-access-card', $html );
        self::assertStringNotContainsString( 'wp_tmq_export', $html );
        self::assertStringNotContainsString( 'page=' . PluginPage::TAB_SMTP . '"', $html, 'SMTP stays with administrators, so its tab is not listed.' );
    }

    public function test_a_user_without_access_gets_nothing(): void {
        wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

        self::assertSame( '', $this->open( PluginPage::TAB_LOG ) );
    }

    public function test_an_import_with_a_forged_nonce_stops_before_any_output(): void {
        $_POST = array(
            'wp_tmq_import'       => '1',
            'wp_tmq_import_nonce' => 'forged',
        );

        $this->expectException( \WPDieException::class );
        $_GET = array( 'page' => PluginPage::TAB_SETTINGS );
        PluginPage::render();
    }

    public function test_the_log_tab_lists_sent_mail(): void {
        $this->insertQueueItem( array( 'status' => 'sent', 'subject' => 'Delivered subject' ) );

        $html = $this->open( PluginPage::TAB_LOG );

        self::assertStringContainsString( 'tmq-table-scroll', $html );
        self::assertStringContainsString( 'Delivered subject', $html );
    }

    public function test_the_retention_tab_reports_a_resend_and_ignores_a_zero_count(): void {
        $this->setPluginOptions( array( 'enabled' => '1' ) );

        self::assertStringContainsString( '3 email(s) have been added back', $this->open( PluginPage::TAB_QUEUE, array( 'resent' => '3' ) ) );
        self::assertStringNotContainsString( 'added back', $this->open( PluginPage::TAB_QUEUE, array( 'resent' => '0' ) ) );
    }

    public function test_the_test_mail_link_queues_a_message_only_with_its_nonce(): void {
        $this->setPluginOptions( array( 'enabled' => '1', 'email' => 'alerts@example.test' ) );

        $this->open( PluginPage::TAB_QUEUE, array( 'addtestmail' => '1', '_wpnonce' => 'forged' ) );
        self::assertStringNotContainsString( 'Testmail #', $this->open( PluginPage::TAB_QUEUE ) );

        $html = $this->open(
            PluginPage::TAB_QUEUE,
            array(
                'addtestmail' => '1',
                '_wpnonce'    => wp_create_nonce( 'wp_tmq_addtestmail' ),
            )
        );
        self::assertStringContainsString( 'Testmail #', $html );
    }

    public function test_every_other_tab_routes_to_its_renderer(): void {
        self::assertStringContainsString( 'No SMTP accounts configured yet.', $this->open( PluginPage::TAB_SMTP ) );
        self::assertStringContainsString( 'is tagged with a source', $this->open( PluginPage::TAB_SOURCES ) );
        self::assertStringContainsString( 'tmq-templates-form', $this->open( PluginPage::TAB_TEMPLATES ) );
        self::assertStringContainsString( 'tmq-docs', $this->open( PluginPage::TAB_DOCS ) );
        self::assertStringContainsString( 'Queue worker', $this->open( PluginPage::TAB_CRON_INFO ) );
    }

    public function test_the_old_faq_slug_opens_the_documentation_and_marks_its_tab(): void {
        $html = $this->open( PluginPage::TAB_FAQ );

        self::assertMatchesRegularExpression( '/page=' . PluginPage::TAB_DOCS . '"[^>]*is-active/', $html );
    }
}
