<?php

declare(strict_types=1);

namespace TMQ\Tests\Functional;

use TotalMailQueue\Admin\Pages\SourcesPage;
use TotalMailQueue\Admin\Tables\SourcesTable;
use TotalMailQueue\Cron\AlertSender;
use TotalMailQueue\Database\Schema;
use TotalMailQueue\Sources\KnownSources;
use TotalMailQueue\Sources\Repository as SourcesRepository;

/**
 * 2.4.1 — translatable + editable source labels, plus group filter.
 *
 * @covers \TotalMailQueue\Sources\Repository::updateOverrides
 * @covers \TotalMailQueue\Sources\Repository::distinctGroups
 * @covers \TotalMailQueue\Sources\KnownSources::displayLabel
 * @covers \TotalMailQueue\Sources\KnownSources::displayGroup
 * @covers \TotalMailQueue\Admin\Pages\SourcesPage
 * @covers \TotalMailQueue\Admin\Tables\SourcesTable
 */
final class SourcesOverridesTest extends FunctionalTestCase {

    protected function setUp(): void {
        parent::setUp();
        $admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
        wp_set_current_user( $admin );

        global $wpdb;
        $wpdb->query( "TRUNCATE TABLE `{$this->sourcesTable()}`" );
    }

    public function test_schema_install_creates_the_two_override_columns(): void {
        global $wpdb;
        $columns = $wpdb->get_col( "DESCRIBE `{$this->sourcesTable()}`", 0 );
        self::assertContains( 'label_override', $columns, 'Migrator must add the label_override column.' );
        self::assertContains( 'group_override', $columns, 'Migrator must add the group_override column.' );
    }

    public function test_update_overrides_persists_both_columns(): void {
        $id = SourcesRepository::register( 'plugin:woocommerce', 'WooCommerce', 'Plugins' );

        SourcesRepository::updateOverrides( $id, 'Loja Online', 'VIP' );

        $row = SourcesRepository::findById( $id );
        self::assertNotNull( $row );
        self::assertSame( 'Loja Online', $row['label_override'] );
        self::assertSame( 'VIP', $row['group_override'] );
    }

    public function test_update_overrides_drops_group_override_for_system_sources(): void {
        $id = SourcesRepository::register( AlertSender::SOURCE_KEY, 'Alert', 'Total Mail Queue' );

        SourcesRepository::updateOverrides( $id, 'My alert', 'Custom Group' );

        $row = SourcesRepository::findById( $id );
        self::assertNotNull( $row );
        self::assertSame( 'My alert', $row['label_override'], 'Label override is allowed even on system sources.' );
        self::assertSame( '', $row['group_override'], 'Group override must be dropped on system sources to keep the always-on classification.' );
    }

    public function test_display_label_priority_override_then_translated_then_raw(): void {
        $id = SourcesRepository::register( 'wp_core:password_reset', 'Password reset', 'WordPress Core' );

        // No override → prefers translatedLabel().
        $row = SourcesRepository::findById( $id );
        self::assertNotNull( $row );
        self::assertSame( 'Password reset', KnownSources::displayLabel( $row ), 'Without override the translated default wins.' );

        // With override → wins over translation.
        SourcesRepository::updateOverrides( $id, 'Recuperar senha', '' );
        $row = SourcesRepository::findById( $id );
        self::assertNotNull( $row );
        self::assertSame( 'Recuperar senha', KnownSources::displayLabel( $row ), 'Override wins over translation.' );
    }

    public function test_display_group_falls_back_to_translated_for_unseeded_keys(): void {
        // A backtrace-detected plugin source — not in the seeded catalog,
        // but the prefix-based group resolution still gives us "Plugins".
        $id  = SourcesRepository::register( 'plugin:akismet', 'Plugin: akismet', 'Plugins' );
        $row = SourcesRepository::findById( $id );
        self::assertNotNull( $row );
        self::assertSame( 'Plugins', KnownSources::displayGroup( $row ) );
    }

    public function test_distinct_groups_unions_label_and_override(): void {
        SourcesRepository::register( 'plugin:woocommerce', 'WooCommerce', 'Plugins' );
        $id_b = SourcesRepository::register( 'plugin:akismet', 'Akismet', 'Plugins' );
        SourcesRepository::updateOverrides( $id_b, '', 'VIP' );

        $groups = SourcesRepository::distinctGroups();

        self::assertContains( 'Plugins', $groups );
        self::assertContains( 'VIP', $groups, 'Override values must show up in the dropdown.' );
    }

    public function test_table_filters_by_group_against_label_or_override(): void {
        SourcesRepository::register( 'plugin:woocommerce', 'WooCommerce', 'Plugins' );
        $id_b = SourcesRepository::register( 'plugin:akismet', 'Akismet', 'Plugins' );
        SourcesRepository::updateOverrides( $id_b, '', 'VIP' );

        $_REQUEST['group_filter'] = 'VIP';
        $table = new SourcesTable();
        $table->prepare_items();

        $keys = array_map( static fn( array $r ): string => (string) $r['source_key'], $table->items );
        self::assertSame( array( 'plugin:akismet' ), $keys, 'Filter must match against the override too.' );
        unset( $_REQUEST['group_filter'] );
    }

    public function test_admin_page_save_posts_persist_overrides(): void {
        $id = SourcesRepository::register( 'wp_core:new_user', 'New user — welcome', 'WordPress Core' );

        $_POST['page']                     = 'wp_tmq_mail_queue-tab-sources';
        $_POST['source_id']                = (string) $id;
        $_POST['label_override']           = 'Boas-vindas';
        $_POST['group_override']           = 'Núcleo';
        $_POST['wp_tmq_source_edit_save']  = '1';
        $_POST['wp_tmq_source_edit_nonce'] = wp_create_nonce( 'wp_tmq_source_edit_' . $id );

        ob_start();
        SourcesPage::render();
        ob_end_clean();

        $row = SourcesRepository::findById( $id );
        self::assertNotNull( $row );
        self::assertSame( 'Boas-vindas', $row['label_override'] );
        self::assertSame( 'Núcleo', $row['group_override'] );

        unset( $_POST['page'], $_POST['source_id'], $_POST['label_override'], $_POST['group_override'], $_POST['wp_tmq_source_edit_save'], $_POST['wp_tmq_source_edit_nonce'] );
    }

    public function test_admin_page_reset_action_clears_overrides(): void {
        $id = SourcesRepository::register( 'plugin:woocommerce', 'WooCommerce', 'Plugins' );
        SourcesRepository::updateOverrides( $id, 'Loja', 'VIP' );

        $_GET['page']          = 'wp_tmq_mail_queue-tab-sources';
        $_GET['source-action'] = 'reset';
        $_GET['source-id']     = (string) $id;
        $_GET['_wpnonce']      = wp_create_nonce( 'wp_tmq_source_reset_' . $id );

        ob_start();
        SourcesPage::render();
        ob_end_clean();

        $row = SourcesRepository::findById( $id );
        self::assertNotNull( $row );
        self::assertSame( '', $row['label_override'], 'Reset clears the label override.' );
        self::assertSame( '', $row['group_override'], 'Reset clears the group override.' );

        unset( $_GET['page'], $_GET['source-action'], $_GET['source-id'], $_GET['_wpnonce'] );
    }

    public function test_block_mode_renders_a_warning_notice_above_the_table(): void {
        $this->setPluginOptions( array( 'enabled' => '2' ) );

        ob_start();
        SourcesPage::render();
        $html = (string) ob_get_clean();

        self::assertStringContainsString( 'Block mode is active', $html );
    }

    public function test_disabled_mode_renders_a_warning_notice_above_the_table(): void {
        $this->setPluginOptions( array( 'enabled' => '0' ) );

        ob_start();
        SourcesPage::render();
        $html = (string) ob_get_clean();

        self::assertStringContainsString( 'currently disabled', $html );
    }

    public function test_queue_mode_does_not_render_the_warning_notice(): void {
        $this->setPluginOptions( array( 'enabled' => '1' ) );

        ob_start();
        SourcesPage::render();
        $html = (string) ob_get_clean();

        self::assertStringNotContainsString( 'Block mode is active', $html );
        self::assertStringNotContainsString( 'currently disabled', $html );
    }

    private function sourcesTable(): string {
        return Schema::sourcesTable();
    }
}
