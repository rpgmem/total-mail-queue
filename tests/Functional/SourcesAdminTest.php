<?php

declare(strict_types=1);

namespace TMQ\Tests\Functional;

use TotalMailQueue\Admin\Pages\SourcesPage;
use TotalMailQueue\Admin\Tables\LogTable;
use TotalMailQueue\Admin\Tables\SourcesTable;
use TotalMailQueue\Database\Schema;
use TotalMailQueue\Sources\Repository as SourcesRepository;

/**
 * @covers \TotalMailQueue\Admin\Pages\SourcesPage
 * @covers \TotalMailQueue\Admin\Tables\SourcesTable
 * @covers \TotalMailQueue\Admin\Tables\LogTable
 */
final class SourcesAdminTest extends FunctionalTestCase {

    protected function setUp(): void {
        parent::setUp();
        $admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
        wp_set_current_user( $admin );

        global $wpdb;
        $wpdb->query( "TRUNCATE TABLE `{$this->sourcesTable()}`" );
    }

    public function test_per_row_get_toggle_disables_an_enabled_source(): void {
        $id = SourcesRepository::register( 'plugin:woocommerce', 'WooCommerce', 'Plugins' );
        self::assertTrue( SourcesRepository::isEnabled( 'plugin:woocommerce' ) );

        $_GET['page']          = 'wp_tmq_mail_queue-tab-sources';
        $_GET['source-action'] = 'disable';
        $_GET['source-id']     = (string) $id;
        $_GET['_wpnonce']      = wp_create_nonce( 'wp_tmq_source_toggle_' . $id );

        ob_start();
        SourcesPage::render();
        ob_end_clean();

        self::assertFalse( SourcesRepository::isEnabled( 'plugin:woocommerce' ) );
    }

    public function test_per_row_toggle_rejects_a_missing_or_invalid_nonce(): void {
        $id = SourcesRepository::register( 'plugin:akismet', 'Akismet', 'Plugins' );

        $_GET['page']          = 'wp_tmq_mail_queue-tab-sources';
        $_GET['source-action'] = 'disable';
        $_GET['source-id']     = (string) $id;
        // No nonce on purpose.

        $this->expectException( \WPDieException::class );
        SourcesPage::render();
    }

    public function test_bulk_post_disables_every_selected_id(): void {
        $a = SourcesRepository::register( 'plugin:a', 'A', 'Plugins' );
        $b = SourcesRepository::register( 'plugin:b', 'B', 'Plugins' );
        $c = SourcesRepository::register( 'plugin:c', 'C', 'Plugins' );

        $_POST['page']                  = 'wp_tmq_mail_queue-tab-sources';
        $_POST['action']                = 'disable';
        $_POST['id']                    = array( (string) $a, (string) $c );
        $_POST['wp_tmq_sources_nonce']  = wp_create_nonce( 'wp_tmq_sources_bulk' );

        ob_start();
        SourcesPage::render();
        ob_end_clean();

        self::assertFalse( SourcesRepository::isEnabled( 'plugin:a' ) );
        self::assertTrue( SourcesRepository::isEnabled( 'plugin:b' ), 'Sources not in the bulk list must stay enabled.' );
        self::assertFalse( SourcesRepository::isEnabled( 'plugin:c' ) );
    }

    public function test_group_post_toggles_every_member_of_the_group(): void {
        SourcesRepository::register( 'wp_core:password_reset', 'Password reset', 'WordPress Core' );
        SourcesRepository::register( 'wp_core:new_user', 'New user', 'WordPress Core' );
        SourcesRepository::register( 'plugin:woocommerce', 'WooCommerce', 'Plugins' );

        $_POST['page']                            = 'wp_tmq_mail_queue-tab-sources';
        $_POST['wp_tmq_sources_group_action']     = 'disable';
        $_POST['group_label']                     = 'WordPress Core';
        $_POST['wp_tmq_sources_group_nonce']      = wp_create_nonce( 'wp_tmq_sources_group' );

        ob_start();
        SourcesPage::render();
        ob_end_clean();

        self::assertFalse( SourcesRepository::isEnabled( 'wp_core:password_reset' ) );
        self::assertFalse( SourcesRepository::isEnabled( 'wp_core:new_user' ) );
        self::assertTrue( SourcesRepository::isEnabled( 'plugin:woocommerce' ), 'Sources outside the group must stay enabled.' );
    }

    public function test_log_table_filters_rows_by_source_when_query_param_present(): void {
        $this->insertQueueItem( array( 'status' => 'sent', 'source_key' => 'plugin:woocommerce', 'subject' => 'wc-1' ) );
        $this->insertQueueItem( array( 'status' => 'sent', 'source_key' => 'plugin:woocommerce', 'subject' => 'wc-2' ) );
        $this->insertQueueItem( array( 'status' => 'sent', 'source_key' => 'wp_core:password_reset', 'subject' => 'pr-1' ) );

        $_GET['page']           = 'wp_tmq_mail_queue-tab-log';
        $_REQUEST['source_filter'] = 'plugin:woocommerce';

        $table = new LogTable();
        $table->prepare_items();
        $items = $table->items;

        self::assertCount( 2, $items );
        $subjects = array_column( $items, 'subject' );
        sort( $subjects );
        self::assertSame( array( 'wc-1', 'wc-2' ), $subjects );
    }

    public function test_sources_table_lists_every_registered_row_ordered_by_group_then_key(): void {
        SourcesRepository::register( 'plugin:b', 'B', 'Plugins' );
        SourcesRepository::register( 'wp_core:x', 'X', 'WordPress Core' );
        SourcesRepository::register( 'plugin:a', 'A', 'Plugins' );

        $table = new SourcesTable();
        $table->prepare_items();

        $keys = array_map( static fn( array $row ): string => (string) $row['source_key'], $table->items );
        self::assertSame( array( 'plugin:a', 'plugin:b', 'wp_core:x' ), $keys );
    }

    private function sourcesTable(): string {
        return Schema::sourcesTable();
    }

    protected function tearDown(): void {
        unset(
            $_GET['page'], $_GET['source-action'], $_GET['source-id'], $_GET['_wpnonce'],
            $_REQUEST['source_filter'],
            $_POST['page'], $_POST['action'], $_POST['id'], $_POST['wp_tmq_sources_nonce'],
            $_POST['wp_tmq_sources_group_action'], $_POST['group_label'], $_POST['wp_tmq_sources_group_nonce']
        );
        parent::tearDown();
    }
}
