<?php

declare(strict_types=1);

namespace TMQ\Tests\Functional;

use TotalMailQueue\Admin\SiteHealth;
use TotalMailQueue\Cron\Diagnostics;
use TotalMailQueue\Cron\Scheduler;

/**
 * The queue in Tools → Site Health (#182): each test's good and bad state,
 * built from the same checks the plugin's own screens make.
 *
 * @covers \TotalMailQueue\Admin\SiteHealth
 */
final class SiteHealthTest extends FunctionalTestCase {

    protected function tearDown(): void {
        wp_clear_scheduled_hook( Scheduler::HOOK );
        delete_option( Diagnostics::LAST_SEND_OPTION );
        parent::tearDown();
    }

    public function test_the_four_tests_and_the_info_section_are_registered(): void {
        $tests = apply_filters( 'site_status_tests', array() );
        foreach ( array( 'tmq_worker', 'tmq_backlog', 'tmq_smtp_waiting', 'tmq_m365_auth' ) as $id ) {
            self::assertArrayHasKey( $id, $tests['direct'], $id );
            self::assertTrue( is_callable( $tests['direct'][ $id ]['test'] ), $id );
        }

        $this->setPluginOptions( array( 'enabled' => '1' ) );
        $this->insertQueueItem();
        $info = apply_filters( 'debug_information', array() );
        self::assertSame( 'Queue', $info['total-mail-queue']['fields']['mode']['value'] );
        self::assertSame( 1, $info['total-mail-queue']['fields']['pending']['value'] );
    }

    public function test_worker_is_good_with_nothing_waiting_and_critical_when_stalled(): void {
        $this->setPluginOptions( array( 'enabled' => '1' ) );
        self::assertSame( 'good', SiteHealth::testWorker()['status'] );

        // Mail waiting, the last send three hours ago, nothing scheduled.
        $this->insertQueueItem( array( 'status' => 'queue' ) );
        update_option( Diagnostics::LAST_SEND_OPTION, wp_date( 'Y-m-d H:i:s', time() - 3 * HOUR_IN_SECONDS ), false );
        wp_clear_scheduled_hook( Scheduler::HOOK );

        $result = SiteHealth::testWorker();
        self::assertSame( 'critical', $result['status'] );
        self::assertStringContainsString( 'wp-cron.php', $result['description'] );
    }

    public function test_backlog_is_recommended_above_the_alert_threshold(): void {
        $this->setPluginOptions( array( 'enabled' => '1', 'email_amount' => 2 ) );
        $this->insertQueueItem();
        $this->insertQueueItem();
        self::assertSame( 'good', SiteHealth::testBacklog()['status'] );

        $this->insertQueueItem();
        $result = SiteHealth::testBacklog();
        self::assertSame( 'recommended', $result['status'] );
        self::assertStringContainsString( '3 emails are waiting, above the alert threshold of 2', $result['description'] );
    }

    public function test_backlog_says_nothing_outside_queue_mode(): void {
        $this->setPluginOptions( array( 'enabled' => '2', 'email_amount' => 1 ) );
        $this->insertQueueItem();
        $this->insertQueueItem();
        self::assertSame( 'good', SiteHealth::testBacklog()['status'] );
    }

    public function test_smtp_waiting_only_matters_with_plugin_smtp_only(): void {
        $this->setPluginOptions( array( 'enabled' => '1', 'send_method' => 'auto' ) );
        $this->insertQueueItem();
        self::assertSame( 'good', SiteHealth::testSmtpWaiting()['status'] );

        $this->setPluginOptions( array( 'enabled' => '1', 'send_method' => 'smtp' ) );
        $result = SiteHealth::testSmtpWaiting();
        self::assertSame( 'recommended', $result['status'] );
        self::assertStringContainsString( 'no account is enabled', $result['description'] );

        $this->insertSmtpAccount();
        self::assertSame( 'good', SiteHealth::testSmtpWaiting()['status'] );
    }

    public function test_m365_flags_only_enabled_password_accounts_on_microsoft_hosts(): void {
        self::assertSame( 'good', SiteHealth::testM365Auth()['status'] );

        $this->insertSmtpAccount( array( 'name' => 'Other', 'host' => 'smtp.example.test' ) );
        $this->insertSmtpAccount( array( 'name' => 'Off', 'host' => 'smtp.office365.com', 'enabled' => 0 ) );
        $this->insertSmtpAccount( array( 'name' => 'No auth', 'host' => 'smtp.office365.com', 'auth' => 0 ) );
        self::assertSame( 'good', SiteHealth::testM365Auth()['status'] );

        $this->insertSmtpAccount( array( 'name' => 'School', 'host' => 'SMTP.Office365.com ' ) );
        $result = SiteHealth::testM365Auth();
        self::assertSame( 'recommended', $result['status'] );
        self::assertStringContainsString( 'Accounts affected: School.', $result['description'] );
        self::assertSame( array( 'School' ), SiteHealth::m365PasswordAccounts() );
    }

    public function test_results_carry_the_plugin_badge_and_a_link_to_its_tab(): void {
        $this->setPluginOptions( array( 'enabled' => '1', 'email_amount' => 1 ) );
        $this->insertQueueItem();
        $this->insertQueueItem();

        $result = SiteHealth::testBacklog();
        self::assertSame( 'Total Mail Queue', $result['badge']['label'] );
        self::assertSame( 'tmq_backlog', $result['test'] );
        self::assertStringContainsString( 'page=wp_tmq_mail_queue-tab-queue', $result['actions'] );
    }
}
