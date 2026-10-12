<?php

declare(strict_types=1);

namespace TMQ\Tests\Functional;

use TotalMailQueue\Cron\AutoBlock;
use TotalMailQueue\Cron\BatchProcessor;
use TotalMailQueue\Cron\Scheduler;
use TotalMailQueue\Queue\MailInterceptor;
use TotalMailQueue\Queue\QueueRepository;
use TotalMailQueue\Settings\Options;
use TotalMailQueue\Settings\Sanitizer;
use TotalMailQueue\Sources\Detector;

/**
 * Block on alert, and password resets that Block mode does not hold (#84).
 *
 * @covers \TotalMailQueue\Cron\AutoBlock
 * @covers \TotalMailQueue\Cron\AlertSender
 * @covers \TotalMailQueue\Cron\BatchProcessor
 * @covers \TotalMailQueue\Queue\MailInterceptor
 * @covers \TotalMailQueue\Cron\CronLock
 * @covers \TotalMailQueue\Queue\MailSucceededHandler
 */
final class AutoBlockTest extends FunctionalTestCase {

    protected function setUp(): void {
        parent::setUp();
        reset_phpmailer_instance();
        delete_option( AutoBlock::OPTION );
        AutoBlock::register();
    }

    protected function tearDown(): void {
        remove_all_filters( 'pre_wp_mail' );
        remove_all_actions( 'update_option_' . Options::OPTION_NAME );
        wp_clear_scheduled_hook( Scheduler::HOOK );
        parent::tearDown();
    }

    public function test_the_alert_switches_to_block_mode_and_sends_nothing_else(): void {
        $this->fillQueue( array( 'auto_block' => '1' ) );

        BatchProcessor::run();

        self::assertSame( '2', (string) Options::get()['enabled'], 'The alert must switch the plugin to Block mode.' );
        self::assertNotNull( AutoBlock::activeSince() );
        self::assertSame( 3, QueueRepository::pendingCount(), 'Nothing in the batch may go out once blocked.' );
        self::assertFalse( wp_next_scheduled( Scheduler::HOOK ), 'The worker must stop.' );

        $alert = $this->alertRow();
        self::assertStringContainsString( 'Block mode', (string) $alert['message'] );
        self::assertStringContainsString( 'Alert', (string) tests_retrieve_phpmailer_instance()->get_sent()->subject, 'The alert itself still goes out.' );
    }

    public function test_without_the_setting_the_alert_only_warns(): void {
        $this->fillQueue( array( 'auto_block' => '0' ) );

        BatchProcessor::run();

        self::assertSame( '1', (string) Options::get()['enabled'] );
        self::assertNull( AutoBlock::activeSince() );
        self::assertSame( 2, QueueRepository::pendingCount(), 'The batch of one is sent as usual.' );
        self::assertStringNotContainsString( 'Block mode', (string) $this->alertRow()['message'] );
    }

    public function test_below_the_threshold_nothing_is_blocked(): void {
        $this->fillQueue( array( 'auto_block' => '1', 'email_amount' => '10' ) );

        BatchProcessor::run();

        self::assertSame( '1', (string) Options::get()['enabled'] );
        self::assertNull( $this->alertRow() );
    }

    public function test_an_alert_already_sent_in_the_window_does_not_block_again(): void {
        $this->fillQueue( array( 'auto_block' => '1' ) );
        $this->insertQueueItem( array( 'status' => 'alert', 'subject' => 'Earlier alert' ) );

        BatchProcessor::run();

        self::assertSame( '1', (string) Options::get()['enabled'], 'No new alert, so no new block.' );
    }

    public function test_leaving_block_mode_clears_the_marker(): void {
        $this->fillQueue( array( 'auto_block' => '1' ) );
        BatchProcessor::run();
        self::assertNotNull( AutoBlock::activeSince() );

        $this->setPluginOptions( array( 'enabled' => '1' ) );

        self::assertNull( AutoBlock::activeSince() );
    }

    public function test_block_mode_sends_a_password_reset_at_once(): void {
        $this->blockMode( array() );

        Detector::setCurrent( MailInterceptor::PASSWORD_RESET_SOURCE, 'Password reset', 'WordPress Core' );
        self::assertTrue( wp_mail( 'user@example.test', 'Password Reset', 'Reset link' ) );

        $row = $this->lastRow();
        self::assertSame( 'sent', $row['status'] );
        self::assertSame( 'Password Reset', tests_retrieve_phpmailer_instance()->get_sent()->subject );
    }

    public function test_block_mode_still_holds_every_other_email(): void {
        $this->blockMode( array() );

        wp_mail( 'user@example.test', 'Newsletter', 'Body' );

        self::assertSame( 'queue', $this->lastRow()['status'] );
        self::assertFalse( tests_retrieve_phpmailer_instance()->get_sent() );
    }

    public function test_with_the_setting_off_block_mode_holds_password_resets_too(): void {
        $this->blockMode( array( 'block_password_reset' => '0' ) );

        Detector::setCurrent( MailInterceptor::PASSWORD_RESET_SOURCE, 'Password reset', 'WordPress Core' );
        wp_mail( 'user@example.test', 'Password Reset', 'Reset link' );

        self::assertSame( 'queue', $this->lastRow()['status'] );
    }

    public function test_a_disabled_password_reset_source_still_wins(): void {
        $this->blockMode( array() );
        global $wpdb;
        // The known-sources catalogue already seeds this row; disable it.
        $wpdb->update( \TotalMailQueue\Database\Schema::sourcesTable(), array( 'enabled' => 0 ), array( 'source_key' => MailInterceptor::PASSWORD_RESET_SOURCE ) );
        self::assertSame( 1, $wpdb->rows_affected, 'The seeded source must exist.' );

        Detector::setCurrent( MailInterceptor::PASSWORD_RESET_SOURCE, 'Password reset', 'WordPress Core' );
        wp_mail( 'user@example.test', 'Password Reset', 'Reset link' );

        self::assertSame( 'blocked_by_source', $this->lastRow()['status'] );
    }

    public function test_settings_default_and_save_both_toggles(): void {
        $defaults = Options::defaults();
        self::assertSame( '0', $defaults['auto_block'] );
        self::assertSame( '1', $defaults['block_password_reset'], 'Password resets go out in Block mode unless turned off.' );

        $saved = Sanitizer::sanitize( array( 'auto_block' => '1', 'block_password_reset' => '0' ) );
        self::assertSame( array( 'auto_block' => '1', 'block_password_reset' => '0' ), $saved );
    }

    /**
     * Queue mode with three pending rows and an alert threshold of one.
     *
     * @param array<string,string> $overrides Extra settings.
     */
    private function fillQueue( array $overrides ): void {
        $this->setPluginOptions(
            array_merge(
                array( 'enabled' => '1', 'alert_enabled' => '1', 'email' => 'admin@example.test', 'email_amount' => '1', 'queue_amount' => '1' ),
                $overrides
            )
        );
        for ( $i = 1; $i <= 3; $i++ ) {
            $this->insertQueueItem( array( 'subject' => 'Queued ' . $i ) );
        }
    }

    /**
     * Block mode with the interceptor hooked in this request.
     *
     * @param array<string,string> $overrides Extra settings.
     */
    private function blockMode( array $overrides ): void {
        $this->setPluginOptions( array_merge( array( 'enabled' => '2' ), $overrides ) );
        MailInterceptor::register();
    }

    /**
     * @return array<string,mixed>|null
     */
    private function alertRow(): ?array {
        global $wpdb;
        $row = $wpdb->get_row( "SELECT * FROM `{$this->queueTable()}` WHERE status = 'alert' ORDER BY id DESC LIMIT 1", ARRAY_A );
        return is_array( $row ) ? $row : null;
    }

    /**
     * @return array<string,mixed>
     */
    private function lastRow(): array {
        global $wpdb;
        return (array) $wpdb->get_row( "SELECT * FROM `{$this->queueTable()}` ORDER BY id DESC LIMIT 1", ARRAY_A );
    }
}
