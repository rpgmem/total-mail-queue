<?php

declare(strict_types=1);

namespace TMQ\Tests\Functional;

use TotalMailQueue\Admin\Notices;
use TotalMailQueue\Cron\AutoBlock;
use TotalMailQueue\Cron\Diagnostics;
use TotalMailQueue\Cron\Scheduler;

/**
 * The global admin notices: last error, stalled worker, the MailPoet
 * conflict and the auto-block explanation, each for the user it is meant for.
 *
 * @covers \TotalMailQueue\Admin\Notices
 */
final class NoticesTest extends FunctionalTestCase {

    protected function setUp(): void {
        parent::setUp();
        wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
        $this->setPluginOptions( array( 'enabled' => '1' ) );
    }

    protected function tearDown(): void {
        delete_option( AutoBlock::OPTION );
        delete_option( Diagnostics::LAST_SEND_OPTION );
        update_option( 'active_plugins', array() );
        wp_clear_scheduled_hook( Scheduler::HOOK );
        set_current_screen( 'front' );
        parent::tearDown();
    }

    private function render(): string {
        ob_start();
        Notices::render();
        return (string) ob_get_clean();
    }

    public function test_nothing_is_said_while_the_plugin_is_disabled(): void {
        $this->setPluginOptions( array( 'enabled' => '0' ) );
        $this->insertQueueItem( array( 'status' => 'error', 'info' => 'Boom' ) );

        self::assertSame( '', $this->render() );
    }

    public function test_an_admin_sees_the_last_error_with_a_link_to_the_log(): void {
        $this->insertQueueItem( array( 'status' => 'sent' ) );
        $this->insertQueueItem( array( 'status' => 'error', 'info' => 'SMTP <refused>' ) );

        $html = $this->render();

        self::assertStringContainsString( 'problems sending e-mails', $html );
        self::assertStringContainsString( 'page=wp_tmq_mail_queue-tab-log', $html );
        self::assertStringContainsString( '<b>SMTP &lt;refused&gt;</b>', $html );
    }

    public function test_an_editor_is_told_to_contact_the_administrator(): void {
        wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
        $this->insertQueueItem( array( 'status' => 'error', 'info' => 'Boom' ) );

        $html = $this->render();

        self::assertStringContainsString( 'Please contact your Administrator.', $html );
        self::assertStringNotContainsString( 'tab-log', $html );
    }

    public function test_a_subscriber_sees_nothing(): void {
        wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
        $this->insertQueueItem( array( 'status' => 'error', 'info' => 'Boom' ) );

        self::assertSame( '', $this->render() );
    }

    public function test_a_last_send_that_succeeded_raises_nothing(): void {
        $this->insertQueueItem( array( 'status' => 'error' ) );
        $this->insertQueueItem( array( 'status' => 'sent' ) );

        self::assertSame( '', $this->render() );
    }

    public function test_mail_waiting_with_no_send_for_hours_and_no_worker_warns_of_a_stall(): void {
        $this->insertQueueItem( array( 'status' => 'queue' ) );
        update_option( Diagnostics::LAST_SEND_OPTION, wp_date( 'Y-m-d H:i:s', time() - 3 * HOUR_IN_SECONDS ) );
        wp_clear_scheduled_hook( Scheduler::HOOK );

        self::assertStringContainsString( 'the queue may not be processing', $this->render() );

        wp_schedule_single_event( time() + 60, Scheduler::HOOK );
        self::assertStringNotContainsString( 'may not be processing', $this->render(), 'A worker armed for the future is not a stall.' );
    }

    public function test_the_auto_block_notice_shows_only_in_block_mode(): void {
        update_option( AutoBlock::OPTION, time() - 60 );

        self::assertStringNotContainsString( 'stopped sending', $this->render() );

        $this->setPluginOptions( array( 'enabled' => '2' ) );
        $html = $this->render();
        self::assertStringContainsString( 'stopped sending on', $html );
        self::assertStringContainsString( 'page=wp_tmq_mail_queue-tab-queue', $html );
    }

    public function test_mailpoet_is_flagged_on_the_settings_screen_only(): void {
        update_option( 'active_plugins', array( 'mailpoet/mailpoet.php' ) );

        set_current_screen( 'dashboard' );
        self::assertStringNotContainsString( 'MailPoet', $this->render() );

        set_current_screen( 'toplevel_page_wp_tmq_mail_queue' );
        $html = $this->render();
        self::assertStringContainsString( 'MailPoet', $html );
        self::assertStringContainsString( 'page=wp_tmq_mail_queue-tab-docs#', $html );
    }

    public function test_register_hooks_admin_notices(): void {
        Notices::register();

        self::assertSame( 10, has_action( 'admin_notices', array( Notices::class, 'render' ) ) );
    }
}
