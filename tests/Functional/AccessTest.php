<?php

declare(strict_types=1);

namespace TMQ\Tests\Functional;

use TotalMailQueue\Admin\Access;
use TotalMailQueue\Admin\PluginPage;
use TotalMailQueue\Admin\Tables\LogTable;
use TotalMailQueue\Admin\SettingsApi;
use TotalMailQueue\Rest\MessageController;
use TotalMailQueue\Sources\Repository as SourcesRepository;
use WP_REST_Request;

/**
 * Which roles view and which manage the plugin (#91).
 *
 * @covers \TotalMailQueue\Admin\Access
 * @covers \TotalMailQueue\Admin\PluginPage
 * @covers \TotalMailQueue\Admin\Tables\LogTable
 * @covers \TotalMailQueue\Rest\MessageController
 */
final class AccessTest extends FunctionalTestCase {

    protected function setUp(): void {
        parent::setUp();
        Access::register();
        Access::revokeAll();
    }

    protected function tearDown(): void {
        Access::revokeAll();
        unset( $_GET['page'], $_POST['_wpnonce'], $_REQUEST['action'], $_REQUEST['id'] );
        parent::tearDown();
    }

    public function test_administrators_hold_both_capabilities_without_a_role_change(): void {
        $this->actAs( 'administrator' );

        self::assertTrue( Access::canView() );
        self::assertTrue( Access::canManage() );
        self::assertFalse( get_role( 'administrator' )->has_cap( Access::MANAGE ), 'Nothing is written to the administrator role.' );
    }

    public function test_other_roles_have_no_access_until_granted(): void {
        $this->actAs( 'editor' );

        self::assertFalse( Access::canView() );
        self::assertSame( array(), PluginPage::visibleTabs() );
    }

    public function test_view_opens_the_read_only_tabs(): void {
        Access::setRole( 'editor', Access::VIEW, true );
        $this->actAs( 'editor' );

        self::assertTrue( Access::canView() );
        self::assertFalse( Access::canManage() );
        // Cron Information exists only while WP-Cron is disabled; it is a view tab too.
        $tabs = array_values( array_diff( array_keys( PluginPage::visibleTabs() ), array( PluginPage::TAB_CRON_INFO ) ) );
        self::assertSame( array( PluginPage::TAB_LOG, PluginPage::TAB_QUEUE, PluginPage::TAB_SOURCES, PluginPage::TAB_DOCS ), $tabs );
    }

    public function test_manage_includes_view_and_opens_settings_and_templates_but_not_smtp(): void {
        Access::setRole( 'editor', Access::MANAGE, true );
        $this->actAs( 'editor' );

        self::assertTrue( Access::canView(), 'Manage implies view.' );
        $tabs = array_keys( PluginPage::visibleTabs() );
        self::assertContains( PluginPage::TAB_SETTINGS, $tabs );
        self::assertContains( PluginPage::TAB_TEMPLATES, $tabs );
        self::assertNotContains( PluginPage::TAB_SMTP, $tabs, 'SMTP accounts stay with administrators.' );
    }

    public function test_a_tab_the_user_may_not_open_renders_nothing(): void {
        Access::setRole( 'editor', Access::VIEW, true );
        $this->actAs( 'editor' );

        self::assertSame( '', $this->renderTab( PluginPage::TAB_SETTINGS ) );
        self::assertSame( '', $this->renderTab( PluginPage::TAB_SMTP ) );
        self::assertStringContainsString( 'tmq-tabs__panel', $this->renderTab( PluginPage::TAB_LOG ) );
    }

    public function test_the_settings_form_saves_with_the_manage_capability(): void {
        self::assertSame( Access::MANAGE, apply_filters( 'option_page_capability_' . SettingsApi::GROUP, 'manage_options' ) );
    }

    public function test_a_viewer_sees_no_bulk_actions_and_cannot_run_them(): void {
        Access::setRole( 'editor', Access::VIEW, true );
        $this->actAs( 'editor' );
        $id = $this->insertQueueItem( array( 'status' => 'sent' ) );

        $table = new LogTable( LogTable::MODE_LOG, array( 'plural' => 'tmq_items' ) );
        self::assertSame( array(), $table->get_bulk_actions() );
        self::assertArrayNotHasKey( 'cb', $table->get_columns() );

        $_POST['_wpnonce']  = wp_create_nonce( 'bulk-tmq_items' );
        $_REQUEST['action'] = 'delete';
        $_REQUEST['id']     = array( (string) $id );
        $table->process_bulk_action();

        self::assertNotNull( \TotalMailQueue\Queue\QueueRepository::findById( $id ), 'A viewer cannot delete.' );
    }

    public function test_a_viewer_cannot_change_a_source(): void {
        Access::setRole( 'editor', Access::VIEW, true );
        $this->actAs( 'editor' );
        $id = SourcesRepository::register( 'plugin:shop', 'Shop', 'Plugins' );

        $_GET['source-action'] = 'disable';
        $_GET['source-id']     = (string) $id;
        $_GET['_wpnonce']      = wp_create_nonce( 'wp_tmq_source_toggle_' . $id );
        \TotalMailQueue\Admin\Pages\SourcesActions::handle();
        unset( $_GET['source-action'], $_GET['source-id'], $_GET['_wpnonce'] );

        self::assertTrue( SourcesRepository::isEnabled( 'plugin:shop' ) );
    }

    public function test_a_manager_reads_bodies_except_the_one_time_link_emails(): void {
        Access::setRole( 'editor', Access::MANAGE, true );
        $this->actAs( 'editor' );
        $reset = $this->insertQueueItem( array( 'status' => 'sent', 'source_key' => 'wp_core:password_reset', 'message' => 'Reset link https://example.test/key' ) );
        $other = $this->insertQueueItem( array( 'status' => 'sent', 'source_key' => 'plugin:shop', 'message' => 'Your order' ) );

        $denied = MessageController::getMessage( $this->request( $reset ) );
        self::assertInstanceOf( \WP_Error::class, $denied );
        self::assertSame( 403, $denied->get_error_data()['status'] );
        self::assertStringContainsString( 'administrators only', $denied->get_error_message() );

        $allowed = MessageController::getMessage( $this->request( $other ) );
        self::assertIsArray( $allowed );
        self::assertStringContainsString( 'Your order', $allowed['data']['html'] );
    }

    public function test_the_log_shows_a_notice_in_place_of_a_restricted_body(): void {
        Access::setRole( 'editor', Access::MANAGE, true );
        $this->actAs( 'editor' );
        $this->insertQueueItem( array( 'status' => 'sent', 'source_key' => 'wp_core:new_user', 'message' => 'Set your password' ) );

        $html = $this->renderTab( PluginPage::TAB_LOG );

        self::assertStringContainsString( 'shown to administrators only', $html );
        self::assertStringNotContainsString( 'data-tmq-list-message-toggle', $html, 'No way to open the body.' );
    }

    public function test_administrators_read_every_body(): void {
        $this->actAs( 'administrator' );
        $reset = $this->insertQueueItem( array( 'status' => 'sent', 'source_key' => 'wp_core:password_reset', 'message' => 'Reset link' ) );

        self::assertTrue( MessageController::permissionCheck() );
        self::assertIsArray( MessageController::getMessage( $this->request( $reset ) ) );
    }

    public function test_a_viewer_cannot_reach_message_bodies(): void {
        Access::setRole( 'editor', Access::VIEW, true );
        $this->actAs( 'editor' );

        self::assertFalse( MessageController::permissionCheck() );
    }

    public function test_administrator_roles_cannot_be_granted_and_are_not_listed(): void {
        self::assertArrayNotHasKey( 'administrator', Access::grantableRoles() );
        self::assertArrayHasKey( 'editor', Access::grantableRoles() );
        self::assertFalse( Access::setRole( 'administrator', Access::VIEW, true ) );
        self::assertFalse( Access::setRole( 'no-such-role', Access::VIEW, true ) );
        self::assertFalse( Access::setRole( 'editor', 'edit_posts', true ), 'Only the plugin capabilities can be granted.' );
    }

    public function test_uninstall_revokes_the_capabilities_from_every_role(): void {
        Access::setRole( 'editor', Access::MANAGE, true );
        Access::setRole( 'author', Access::VIEW, true );

        Access::revokeAll();

        self::assertFalse( Access::roleHas( 'editor', Access::MANAGE ) );
        self::assertFalse( Access::roleHas( 'author', Access::VIEW ) );
    }

    private function actAs( string $role ): void {
        wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );
    }

    private function request( int $id ): WP_REST_Request {
        $request = new WP_REST_Request( 'GET', '/tmq/v1/message/' . $id );
        $request->set_url_params( array( 'id' => (string) $id ) );
        return $request;
    }

    private function renderTab( string $tab ): string {
        $_GET['page'] = $tab;
        set_current_screen( 'toplevel_page_wp_tmq_mail_queue' );
        ob_start();
        PluginPage::render();
        return (string) ob_get_clean();
    }
}
