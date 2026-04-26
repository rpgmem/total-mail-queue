<?php

declare(strict_types=1);

namespace TMQ\Tests\Functional;

/**
 * Exercises \TotalMailQueue\Cron\BatchProcessor::run() with a real database.
 *
 * The plugin clears every pre_wp_mail filter before calling wp_mail() in cron,
 * which means a test-only short-circuit filter is unreliable. Instead we lean
 * on wp-phpunit's MockPHPMailer (auto-installed by the test bootstrap) which
 * intercepts at the PHPMailer level — so wp_mail() returns true without
 * making any network calls, and we can introspect the captured messages.
 *
 * @covers \TotalMailQueue\Cron\BatchProcessor::run
 */
final class CronFlowTest extends FunctionalTestCase {

    protected function setUp(): void {
        parent::setUp();
        reset_phpmailer_instance();
    }

    public function test_cron_processes_queued_emails_and_marks_them_sent(): void {
        $this->setPluginOptions( array( 'enabled' => '1', 'queue_amount' => 5 ) );

        $ids = array(
            $this->insertQueueItem( array( 'subject' => 'a' ) ),
            $this->insertQueueItem( array( 'subject' => 'b' ) ),
            $this->insertQueueItem( array( 'subject' => 'c' ) ),
        );

        \TotalMailQueue\Cron\BatchProcessor::run();

        global $wpdb;
        foreach ( $ids as $id ) {
            $status = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM `{$this->queueTable()}` WHERE id = %d", $id ) );
            self::assertSame( 'sent', $status );
        }

        $mailer = tests_retrieve_phpmailer_instance();
        self::assertCount( 3, $mailer->mock_sent, 'PHPMailer must have been invoked exactly three times.' );
    }

    public function test_cron_respects_queue_amount_limit_per_batch(): void {
        $this->setPluginOptions( array( 'enabled' => '1', 'queue_amount' => 2 ) );

        for ( $i = 0; $i < 5; $i++ ) {
            $this->insertQueueItem( array( 'subject' => "msg-$i" ) );
        }

        \TotalMailQueue\Cron\BatchProcessor::run();

        $mailer = tests_retrieve_phpmailer_instance();
        self::assertCount( 2, $mailer->mock_sent, 'A single cron run must send at most queue_amount emails.' );

        global $wpdb;
        $remaining = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$this->queueTable()}` WHERE status = 'queue'" );
        self::assertSame( 3, $remaining );
    }

    public function test_high_priority_emails_are_sent_before_normal_queue(): void {
        $this->setPluginOptions( array( 'enabled' => '1', 'queue_amount' => 1 ) );

        $normal = $this->insertQueueItem( array( 'status' => 'queue', 'subject' => 'normal' ) );
        $high   = $this->insertQueueItem( array( 'status' => 'high',  'subject' => 'urgent' ) );

        \TotalMailQueue\Cron\BatchProcessor::run();

        global $wpdb;
        $high_status   = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM `{$this->queueTable()}` WHERE id = %d", $high ) );
        $normal_status = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM `{$this->queueTable()}` WHERE id = %d", $normal ) );

        self::assertSame( 'sent', $high_status, 'High-priority email must drain first.' );
        self::assertSame( 'queue', $normal_status, 'Normal email must remain queued.' );
    }

    public function test_cron_skips_when_plugin_disabled(): void {
        $this->setPluginOptions( array( 'enabled' => '0', 'queue_amount' => 5 ) );
        $this->insertQueueItem( array( 'subject' => 'should-stay' ) );

        \TotalMailQueue\Cron\BatchProcessor::run();

        $mailer = tests_retrieve_phpmailer_instance();
        self::assertEmpty( $mailer->mock_sent );
        global $wpdb;
        $status = $wpdb->get_var( "SELECT status FROM `{$this->queueTable()}` LIMIT 1" );
        self::assertSame( 'queue', $status );
    }

    public function test_cron_skips_when_in_block_mode(): void {
        // Block mode (enabled=2) retains emails; cron must NOT drain them.
        $this->setPluginOptions( array( 'enabled' => '2', 'queue_amount' => 5 ) );
        $this->insertQueueItem( array( 'subject' => 'blocked' ) );

        \TotalMailQueue\Cron\BatchProcessor::run();

        $mailer = tests_retrieve_phpmailer_instance();
        self::assertEmpty( $mailer->mock_sent );
        global $wpdb;
        $status = $wpdb->get_var( "SELECT status FROM `{$this->queueTable()}` LIMIT 1" );
        self::assertSame( 'queue', $status );
    }

    public function test_smtp_only_mode_holds_emails_when_no_account_available(): void {
        $this->setPluginOptions( array( 'enabled' => '1', 'queue_amount' => 5, 'send_method' => 'smtp' ) );
        $this->insertQueueItem( array( 'subject' => 'waiting-for-smtp' ) );

        \TotalMailQueue\Cron\BatchProcessor::run();

        global $wpdb;
        $row = $wpdb->get_row( "SELECT status, info FROM `{$this->queueTable()}` LIMIT 1", ARRAY_A );
        self::assertSame( 'queue', $row['status'], 'In smtp-only mode without SMTP accounts, emails must remain queued.' );
        self::assertStringContainsString( 'no SMTP account available', (string) $row['info'] );
        $mailer = tests_retrieve_phpmailer_instance();
        self::assertEmpty( $mailer->mock_sent, 'wp_mail must not be invoked when smtp-only mode finds no usable account.' );
    }

    public function test_cron_writes_diagnostics_to_wp_tmq_last_cron(): void {
        $this->setPluginOptions( array( 'enabled' => '1', 'queue_amount' => 2 ) );
        $this->insertQueueItem();
        $this->insertQueueItem();

        \TotalMailQueue\Cron\BatchProcessor::run();

        $diag = get_option( 'wp_tmq_last_cron' );
        self::assertIsArray( $diag );
        self::assertSame( 'ok', $diag['result'] );
        self::assertSame( 2, $diag['queue_batch'] );
        self::assertSame( 2, $diag['sent'] );
    }
}
