<?php

declare(strict_types=1);

namespace TMQ\Tests\Integration;

use TotalMailQueue\Sources\Repository;

/**
 * @covers \TotalMailQueue\Sources\Repository
 */
final class SourcesRepositoryTest extends IntegrationTestCase {

    public function test_find_by_key_returns_null_when_no_row_matches(): void {
        $this->wpdb->will_return( 'get_row', null );

        self::assertNull( Repository::findByKey( 'wp_core:password_reset' ) );

        $get_row = $this->wpdb->call( 'get_row' );
        self::assertNotNull( $get_row );
        self::assertStringContainsString( '`wp_total_mail_queue_sources`', $get_row['args'][0] );
        self::assertStringContainsString( '`source_key` = wp_core:password_reset', $get_row['args'][0] );
    }

    public function test_find_by_key_returns_row_as_array(): void {
        $row = array(
            'id'           => '7',
            'source_key'   => 'wp_core:password_reset',
            'label'        => 'Password reset',
            'group_label'  => 'WordPress Core',
            'enabled'      => '1',
            'total_count'  => '12',
        );
        $this->wpdb->will_return( 'get_row', $row );

        self::assertSame( $row, Repository::findByKey( 'wp_core:password_reset' ) );
    }

    public function test_register_inserts_a_brand_new_row_and_returns_insert_id(): void {
        // findByKey() returns null first, then insert() is recorded.
        $this->wpdb->will_return( 'get_row', null );
        $this->wpdb->will_return( 'insert', 1 );

        $id = Repository::register( 'wp_core:password_reset', 'Password reset', 'WordPress Core' );

        self::assertSame( 1, $id );
        $insert = $this->wpdb->call( 'insert' );
        self::assertNotNull( $insert );
        self::assertSame( 'wp_total_mail_queue_sources', $insert['args'][0] );
        $payload = $insert['args'][1];
        self::assertSame( 'wp_core:password_reset', $payload['source_key'] );
        self::assertSame( 'Password reset', $payload['label'] );
        self::assertSame( 'WordPress Core', $payload['group_label'] );
        self::assertSame( 1, $payload['enabled'] );
        self::assertSame( 0, $payload['total_count'] );
        self::assertArrayHasKey( 'detected_at', $payload );
        self::assertArrayHasKey( 'last_seen_at', $payload );
    }

    public function test_register_does_not_insert_when_key_already_exists(): void {
        $this->wpdb->will_return( 'get_row', array(
            'id'         => '42',
            'source_key' => 'wp_core:password_reset',
            'enabled'    => '1',
        ) );

        $id = Repository::register( 'wp_core:password_reset', 'Password reset', 'WordPress Core' );

        self::assertSame( 42, $id );
        self::assertNull( $this->wpdb->call( 'insert' ), 'register() must not insert when the key already exists.' );
    }

    public function test_mark_seen_updates_count_and_last_seen_at(): void {
        Repository::markSeen( 7 );

        $query = $this->wpdb->call( 'query' );
        self::assertNotNull( $query );
        self::assertStringContainsString( '`wp_total_mail_queue_sources`', $query['args'][0] );
        self::assertStringContainsString( '`total_count` = `total_count` + 1', $query['args'][0] );
        self::assertStringContainsString( '`id` = 7', $query['args'][0] );
    }

    public function test_mark_seen_is_a_no_op_for_non_positive_ids(): void {
        Repository::markSeen( 0 );
        Repository::markSeen( -3 );

        self::assertNull( $this->wpdb->call( 'query' ) );
        self::assertNull( $this->wpdb->call( 'prepare' ) );
    }

    public function test_is_enabled_defaults_to_true_for_unknown_keys(): void {
        $this->wpdb->will_return( 'get_row', null );

        self::assertTrue( Repository::isEnabled( 'plugin:never_seen' ) );
    }

    public function test_is_enabled_defaults_to_true_for_empty_key_without_db_hit(): void {
        self::assertTrue( Repository::isEnabled( '' ) );
        self::assertNull( $this->wpdb->call( 'get_row' ), 'Empty key must short-circuit before any DB hit.' );
    }

    public function test_is_enabled_returns_true_for_enabled_row(): void {
        $this->wpdb->will_return( 'get_row', array( 'id' => '5', 'enabled' => '1' ) );
        self::assertTrue( Repository::isEnabled( 'wp_core:password_reset' ) );
    }

    public function test_is_enabled_returns_false_for_disabled_row(): void {
        $this->wpdb->will_return( 'get_row', array( 'id' => '5', 'enabled' => '0' ) );
        self::assertFalse( Repository::isEnabled( 'wp_core:password_reset' ) );
    }

    public function test_set_enabled_writes_the_expected_update(): void {
        Repository::setEnabled( 9, false );

        $update = $this->wpdb->call( 'update' );
        self::assertNotNull( $update );
        self::assertSame( 'wp_total_mail_queue_sources', $update['args'][0] );
        self::assertSame( array( 'enabled' => 0 ), $update['args'][1] );
        self::assertSame( array( 'id' => 9 ), $update['args'][2] );
    }

    public function test_set_enabled_is_a_no_op_for_non_positive_ids(): void {
        Repository::setEnabled( 0, true );
        self::assertNull( $this->wpdb->call( 'update' ) );
    }

    public function test_set_enabled_by_group_returns_affected_row_count(): void {
        // S4: setEnabledByGroup uses $wpdb->query() with a NOT LIKE clause
        // (so system sources can be excluded), not the high-level update().
        $this->wpdb->will_return( 'query', 3 );

        $count = Repository::setEnabledByGroup( 'WooCommerce', false );

        self::assertSame( 3, $count );
        $query = $this->wpdb->call( 'query' );
        self::assertNotNull( $query );
        self::assertStringContainsString( 'UPDATE `wp_total_mail_queue_sources`', $query['args'][0] );
        self::assertStringContainsString( '`enabled` = 0', $query['args'][0] );
        self::assertStringContainsString( "`group_label` = WooCommerce", $query['args'][0] );
    }

    public function test_all_returns_rows_ordered_by_group_then_key(): void {
        $rows = array(
            array( 'source_key' => 'wp_core:password_reset', 'group_label' => 'WordPress Core' ),
            array( 'source_key' => 'woocommerce:new_order', 'group_label' => 'WooCommerce' ),
        );
        $this->wpdb->will_return( 'get_results', $rows );

        self::assertSame( $rows, Repository::all() );
        $get = $this->wpdb->call( 'get_results' );
        self::assertNotNull( $get );
        self::assertStringContainsString( 'ORDER BY `group_label` ASC, `source_key` ASC', $get['args'][0] );
    }

    public function test_count_casts_to_int(): void {
        $this->wpdb->will_return( 'get_var', '13' );
        self::assertSame( 13, Repository::count() );
    }

    public function test_is_system_recognises_the_total_mail_queue_prefix(): void {
        self::assertTrue( Repository::isSystem( 'total_mail_queue:alert' ) );
        self::assertTrue( Repository::isSystem( 'total_mail_queue:anything' ) );
        self::assertFalse( Repository::isSystem( 'plugin:woocommerce' ) );
        self::assertFalse( Repository::isSystem( 'wp_core:password_reset' ) );
        self::assertFalse( Repository::isSystem( '' ) );
    }

    public function test_set_enabled_skips_system_sources_after_loading_the_row(): void {
        $this->wpdb->will_return( 'get_row', array(
            'id'         => '5',
            'source_key' => 'total_mail_queue:alert',
            'enabled'    => '1',
        ) );

        Repository::setEnabled( 5, false );

        self::assertNull( $this->wpdb->call( 'update' ), 'No UPDATE must be issued for a system source.' );
    }

    public function test_set_enabled_proceeds_for_non_system_sources(): void {
        $this->wpdb->will_return( 'get_row', array(
            'id'         => '7',
            'source_key' => 'plugin:woocommerce',
            'enabled'    => '1',
        ) );

        Repository::setEnabled( 7, false );

        $update = $this->wpdb->call( 'update' );
        self::assertNotNull( $update );
        self::assertSame( array( 'enabled' => 0 ), $update['args'][1] );
    }

    public function test_set_enabled_by_group_excludes_system_sources_in_the_query(): void {
        Repository::setEnabledByGroup( 'WordPress Core', false );

        $query = $this->wpdb->call( 'query' );
        self::assertNotNull( $query );
        self::assertStringContainsString( 'NOT LIKE', $query['args'][0] );
        // esc_like() escapes underscores in MySQL LIKE patterns: the rendered
        // pattern is `total\_mail\_queue:%`, which the engine treats as the
        // literal `total_mail_queue:` prefix followed by anything.
        self::assertStringContainsString( 'total\\_mail\\_queue:%', $query['args'][0] );
    }
}
