<?php

declare(strict_types=1);

namespace TMQ\Tests\Functional;

use TotalMailQueue\Admin\Pages\SourcesPage;
use TotalMailQueue\Cron\AlertSender;
use TotalMailQueue\Database\Schema;
use TotalMailQueue\Queue\MailInterceptor;
use TotalMailQueue\Sources\Detector;
use TotalMailQueue\Sources\Repository as SourcesRepository;

/**
 * End-to-end checks for the S4 enforcement flip: a disabled source
 * causes wp_mail() to land as `blocked_by_source` instead of `queue`,
 * the alert source is hardcoded as un-toggleable, and the Instant
 * priority header doesn't bypass the block.
 *
 * @covers \TotalMailQueue\Queue\MailInterceptor::handle
 * @covers \TotalMailQueue\Sources\Repository::isSystem
 * @covers \TotalMailQueue\Sources\Repository::setEnabled
 * @covers \TotalMailQueue\Sources\Repository::setEnabledByGroup
 */
final class SourcesEnforcementTest extends FunctionalTestCase {

    protected function setUp(): void {
        parent::setUp();
        $this->setPluginOptions( array( 'enabled' => '1' ) );
        MailInterceptor::register();

        global $wpdb;
        $wpdb->query( "TRUNCATE TABLE `{$this->sourcesTable()}`" );
    }

    public function test_disabled_source_routes_message_to_blocked_by_source_status(): void {
        $id = SourcesRepository::register( 'plugin:woocommerce', 'WooCommerce', 'Plugins' );
        SourcesRepository::setEnabled( $id, false );
        Detector::setCurrent( 'plugin:woocommerce', 'WooCommerce', 'Plugins' );

        $sent = wp_mail( 'user@example.test', 'Subject', 'Body' );
        self::assertTrue( $sent, 'wp_mail() returns true so the caller cannot tell the message was blocked.' );

        global $wpdb;
        $row = $wpdb->get_row( "SELECT * FROM `{$this->queueTable()}` ORDER BY id DESC LIMIT 1", ARRAY_A );
        self::assertNotNull( $row );
        self::assertSame( 'blocked_by_source', $row['status'] );
        self::assertSame( 'plugin:woocommerce', $row['source_key'] );
    }

    public function test_enabled_source_still_queues_normally(): void {
        SourcesRepository::register( 'plugin:woocommerce', 'WooCommerce', 'Plugins' );
        Detector::setCurrent( 'plugin:woocommerce', 'WooCommerce', 'Plugins' );

        wp_mail( 'user@example.test', 'Subject', 'Body' );

        global $wpdb;
        $row = $wpdb->get_row( "SELECT * FROM `{$this->queueTable()}` ORDER BY id DESC LIMIT 1", ARRAY_A );
        self::assertNotNull( $row );
        self::assertSame( 'queue', $row['status'] );
    }

    public function test_instant_priority_does_not_bypass_a_disabled_source(): void {
        $id = SourcesRepository::register( 'plugin:bogus', 'Bogus', 'Plugins' );
        SourcesRepository::setEnabled( $id, false );
        Detector::setCurrent( 'plugin:bogus', 'Bogus', 'Plugins' );

        wp_mail( 'user@example.test', 'Subject', 'Body', array( 'X-Mail-Queue-Prio: Instant' ) );

        global $wpdb;
        $row = $wpdb->get_row( "SELECT * FROM `{$this->queueTable()}` ORDER BY id DESC LIMIT 1", ARRAY_A );
        self::assertNotNull( $row );
        self::assertSame( 'blocked_by_source', $row['status'], 'A third-party Instant header must not be allowed to bypass the per-source block.' );
    }

    public function test_repository_refuses_to_disable_a_system_source(): void {
        $id = SourcesRepository::register( AlertSender::SOURCE_KEY, 'Alert', 'Total Mail Queue' );

        SourcesRepository::setEnabled( $id, false );

        self::assertTrue( SourcesRepository::isEnabled( AlertSender::SOURCE_KEY ) );
        self::assertTrue( SourcesRepository::isSystem( AlertSender::SOURCE_KEY ) );
    }

    public function test_group_disable_skips_system_sources_inside_the_group(): void {
        $alert  = SourcesRepository::register( AlertSender::SOURCE_KEY, 'Alert', 'Total Mail Queue' );
        $other  = SourcesRepository::register( 'total_mail_queue:test', 'Test', 'Total Mail Queue' );
        // For this test, register a non-system row in the same group to confirm only it flips.
        // (In practice the catalog won't put non-system rows in the system group; this is purely
        // a regression guard for the LIKE-based exclusion.)

        $updated = SourcesRepository::setEnabledByGroup( 'Total Mail Queue', false );

        self::assertSame( 0, $updated, 'Every member of the system group is a system source, so nothing flips.' );
        self::assertTrue( SourcesRepository::isEnabled( AlertSender::SOURCE_KEY ) );
        self::assertTrue( SourcesRepository::isEnabled( 'total_mail_queue:test' ) );
        unset( $alert, $other );
    }

    public function test_per_row_admin_toggle_cannot_disable_a_system_source(): void {
        $admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
        wp_set_current_user( $admin );

        $id = SourcesRepository::register( AlertSender::SOURCE_KEY, 'Alert', 'Total Mail Queue' );

        $_GET['page']          = 'wp_tmq_mail_queue-tab-sources';
        $_GET['source-action'] = 'disable';
        $_GET['source-id']     = (string) $id;
        $_GET['_wpnonce']      = wp_create_nonce( 'wp_tmq_source_toggle_' . $id );

        ob_start();
        SourcesPage::render();
        ob_end_clean();

        self::assertTrue( SourcesRepository::isEnabled( AlertSender::SOURCE_KEY ), 'Even with a valid nonce, the system source must stay enabled.' );

        unset( $_GET['page'], $_GET['source-action'], $_GET['source-id'], $_GET['_wpnonce'] );
    }

    private function sourcesTable(): string {
        return Schema::sourcesTable();
    }
}
