<?php

declare(strict_types=1);

namespace TMQ\Tests\Functional;

use TotalMailQueue\Admin\Pages\SourcesPage;
use TotalMailQueue\Database\Schema;
use TotalMailQueue\Sources\Repository as SourcesRepository;

/**
 * The per-source edit form: label, group, priority and preferred account
 * for every source, and the template override block that only the wp_core
 * templates get.
 *
 * @covers \TotalMailQueue\Admin\Pages\SourcesPage
 * @covers \TotalMailQueue\Admin\Pages\SourcesEditRenderer
 * @covers \TotalMailQueue\Admin\Pages\SourcesActions
 */
final class SourcesEditTest extends FunctionalTestCase {

    protected function setUp(): void {
        parent::setUp();
        wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
        global $wpdb;
        $wpdb->query( 'TRUNCATE TABLE `' . Schema::sourcesTable() . '`' );
    }

    protected function tearDown(): void {
        $_GET  = array();
        $_POST = array();
        parent::tearDown();
    }

    private function edit( int $id ): string {
        $_GET['page']          = 'wp_tmq_mail_queue-tab-sources';
        $_GET['source-action'] = 'edit';
        $_GET['source-id']     = (string) $id;
        ob_start();
        SourcesPage::render();
        return (string) ob_get_clean();
    }

    public function test_a_missing_source_says_so(): void {
        self::assertStringContainsString( 'Source not found.', $this->edit( 999 ) );
    }

    public function test_a_known_plugin_source_shows_its_translated_defaults_and_no_template_block(): void {
        $id = SourcesRepository::register( 'plugin:woocommerce', 'WooCommerce', 'Plugins' );

        $html = $this->edit( $id );

        self::assertStringContainsString( 'Edit source: plugin:woocommerce', $html );
        self::assertStringContainsString( 'Default: WooCommerce', $html );
        self::assertStringContainsString( 'Default: Plugins', $html );
        self::assertStringContainsString( 'name="group_override"', $html );
        self::assertStringContainsString( 'name="priority"', $html );
        self::assertStringContainsString( 'No SMTP accounts configured yet', $html );
        self::assertStringNotContainsString( 'name="subject_override"', $html );
    }

    public function test_an_unknown_source_falls_back_to_the_stored_label_or_asks_for_one(): void {
        $labelled = SourcesRepository::register( 'custom:thing', 'Custom thing', 'Custom group' );
        $html     = $this->edit( $labelled );
        self::assertStringContainsString( 'Default (stored fallback): Custom thing', $html );
        self::assertStringContainsString( 'Default (stored fallback): Custom group', $html );

        $bare = SourcesRepository::register( 'custom:bare', '', 'Other' );
        self::assertStringContainsString( 'No translated default available', $this->edit( $bare ) );
    }

    public function test_a_system_source_cannot_override_its_group(): void {
        $id = SourcesRepository::register( 'total_mail_queue:alert', 'Alert', 'Total Mail Queue' );

        $html = $this->edit( $id );

        self::assertStringContainsString( 'cannot be overridden', $html );
        self::assertStringNotContainsString( 'name="group_override"', $html );
    }

    public function test_the_preferred_account_select_lists_accounts_and_marks_the_pinned_one(): void {
        $first  = $this->insertSmtpAccount( array( 'name' => 'Primary' ) );
        $second = $this->insertSmtpAccount( array( 'name' => '' ) );
        $id     = SourcesRepository::register( 'plugin:woocommerce', 'WooCommerce', 'Plugins' );
        SourcesRepository::updatePreferredAccount( $id, $second );

        $html = $this->edit( $id );

        self::assertStringContainsString( '<option value="' . $first . '">Primary</option>', $html );
        self::assertStringContainsString( '<option value="' . $second . '" selected=\'selected\'>#' . $second . '</option>', $html, 'An unnamed account is listed by id.' );
    }

    public function test_a_core_template_shows_tokens_defaults_test_button_and_no_reset_while_untouched(): void {
        $id = SourcesRepository::register( 'wp_core:password_reset', 'Password reset', 'WordPress Core' );

        $html = $this->edit( $id );

        self::assertStringContainsString( 'name="subject_override"', $html );
        self::assertStringContainsString( 'Available tokens:', $html );
        self::assertStringContainsString( '<code>{', $html );
        self::assertStringContainsString( 'WP default: ', $html );
        self::assertStringContainsString( 'Show WP default body', $html );
        self::assertStringContainsString( 'id="tmq-template-test-send"', $html );
        self::assertStringNotContainsString( 'Reset to WP default', $html );
    }

    public function test_an_overridden_core_template_offers_a_reset_that_clears_it(): void {
        $id = SourcesRepository::register( 'wp_core:password_reset', 'Password reset', 'WordPress Core' );
        SourcesRepository::updateTemplateOverrides( $id, 'Custom <subject>', 'Custom body', true );

        $html = $this->edit( $id );

        self::assertStringContainsString( 'value="Custom &lt;subject&gt;"', $html );
        self::assertStringContainsString( '>Custom body</textarea>', $html );
        self::assertStringContainsString( 'Reset to WP default', $html );

        $_GET = array(
            'page'          => 'wp_tmq_mail_queue-tab-sources',
            'source-action' => 'reset_template',
            'source-id'     => (string) $id,
            '_wpnonce'      => wp_create_nonce( 'wp_tmq_source_reset_template_' . $id ),
        );
        ob_start();
        SourcesPage::render();
        ob_end_clean();

        $row = SourcesRepository::findById( $id );
        self::assertSame( '', (string) $row['subject_override'] );
        self::assertSame( '', (string) $row['body_override'] );
        self::assertEmpty( $row['skip_template_wrap'] );
    }

    public function test_a_view_only_user_gets_the_list_instead_of_the_form(): void {
        $id = SourcesRepository::register( 'plugin:woocommerce', 'WooCommerce', 'Plugins' );
        wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

        $html = $this->edit( $id );

        self::assertStringNotContainsString( 'Edit source:', $html );
    }
}
