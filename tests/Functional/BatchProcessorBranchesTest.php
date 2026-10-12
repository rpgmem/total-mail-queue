<?php

declare(strict_types=1);

namespace TMQ\Tests\Functional;

use TotalMailQueue\Cron\BatchProcessor;
use TotalMailQueue\Cron\CronLock;
use TotalMailQueue\Smtp\Repository as SmtpRepository;
use TotalMailQueue\Sources\Repository as SourcesRepository;
use TotalMailQueue\Support\Encryption;
use TotalMailQueue\Support\Serializer;

/**
 * The worker's less travelled paths: a second trigger in one request, a
 * lock another batch holds or takes over, a row without a recipient, a send
 * through an SMTP account or a replayed third-party configuration, a send
 * that fails without an exception, and a source that skips the template.
 *
 * @covers \TotalMailQueue\Cron\BatchProcessor
 * @covers \TotalMailQueue\Cron\CronLock
 * @covers \TotalMailQueue\Smtp\Configurator
 */
final class BatchProcessorBranchesTest extends FunctionalTestCase {

    protected function setUp(): void {
        parent::setUp();
        reset_phpmailer_instance();
        delete_option( CronLock::OPTION );
        $this->setPluginOptions( array( 'enabled' => '1', 'queue_amount' => 5 ) );
    }

    protected function tearDown(): void {
        remove_all_actions( 'phpmailer_init' );
        delete_option( CronLock::OPTION );
        parent::tearDown();
    }

    private function result(): string {
        return (string) get_option( 'wp_tmq_last_cron' )['result'];
    }

    private function status( int $id ): string {
        global $wpdb;
        return (string) $wpdb->get_var( $wpdb->prepare( "SELECT status FROM `{$this->queueTable()}` WHERE id = %d", $id ) );
    }

    /**
     * @return array<string,mixed>
     */
    private function row( int $id ): array {
        global $wpdb;
        return (array) $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$this->queueTable()}` WHERE id = %d", $id ), ARRAY_A );
    }

    public function test_a_second_trigger_in_the_same_request_does_nothing(): void {
        BatchProcessor::run();
        $id = $this->insertQueueItem();

        BatchProcessor::run();

        self::assertSame( 'skipped: duplicate trigger', $this->result() );
        self::assertSame( 'queue', $this->status( $id ) );
    }

    public function test_a_live_lock_held_elsewhere_skips_the_batch(): void {
        update_option( CronLock::OPTION, 'other|' . ( time() + 600 ), false );
        $id = $this->insertQueueItem();

        BatchProcessor::run();

        self::assertSame( 'skipped: another batch is still running', $this->result() );
        self::assertSame( 'queue', $this->status( $id ) );
    }

    public function test_the_batch_stops_when_a_newer_one_takes_the_lock_mid_drain(): void {
        $first  = $this->insertQueueItem( array( 'subject' => 'first' ) );
        $second = $this->insertQueueItem( array( 'subject' => 'second' ) );
        // While the first row sends, another process claims the lock and our
        // window reaches its back half, so the next keepalive has to look.
        add_action(
            'phpmailer_init',
            static function (): void {
                update_option( CronLock::OPTION, 'newer|' . ( time() + 600 ), false );
                $expires = new \ReflectionProperty( CronLock::class, 'expires' );
                $expires->setAccessible( true );
                $expires->setValue( null, time() );
            }
        );

        BatchProcessor::run();

        self::assertSame( 'aborted: lock taken over by a newer batch', $this->result() );
        self::assertSame( 'sent', $this->status( $first ) );
        self::assertSame( 'queue', $this->status( $second ) );
        self::assertSame( 'newer|', substr( (string) get_option( CronLock::OPTION ), 0, 6 ), 'A lost lock is never released over the new holder.' );
    }

    public function test_a_row_without_a_recipient_goes_to_the_alert_address_marked_as_an_error(): void {
        $this->setPluginOptions( array( 'email' => 'alerts@example.test' ) );
        $this->insertQueueItem( array( 'recipient' => '', 'subject' => 'Orphan' ) );

        BatchProcessor::run();

        $sent = tests_retrieve_phpmailer_instance()->get_sent();
        self::assertSame( 'alerts@example.test', $sent->to[0][0] );
        self::assertSame( 'ERROR // Orphan', $sent->subject );
    }

    public function test_an_smtp_account_sends_the_row_and_counts_it(): void {
        $account = $this->insertSmtpAccount( array( 'host' => 'relay.example.test', 'port' => 2525, 'encryption' => 'ssl' ) );
        $id      = $this->insertQueueItem();

        BatchProcessor::run();

        $row = $this->row( $id );
        self::assertSame( 'sent', $row['status'] );
        self::assertSame( $account, (int) $row['smtp_account_id'] );
        self::assertSame( 1, (int) SmtpRepository::findById( $account )['daily_sent'] );

        $mailer = tests_retrieve_phpmailer_instance();
        self::assertSame( 'relay.example.test', $mailer->Host );
        self::assertSame( 2525, (int) $mailer->Port );
        self::assertSame( 'ssl', $mailer->SMTPSecure );
        self::assertSame( 'pass', $mailer->Password, 'The stored password is decrypted for the send.' );
        self::assertFalse( has_action( 'phpmailer_init' ), 'The per-row hook is removed after the send.' );
    }

    public function test_a_captured_configuration_is_replayed_in_automatic_mode(): void {
        $this->setPluginOptions( array( 'send_method' => 'auto', 'smtp_timeout' => 12 ) );
        $config = array(
            'Host'        => 'captured.example.test',
            'Password'    => Encryption::encrypt( 'captured-secret' ),
            'NotAMailerProperty' => 'ignored',
        );
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- the header format MailInterceptor writes.
        $header = 'X-TMQ-PHPMailer-Config: ' . base64_encode( Serializer::encode( $config ) );
        $this->insertQueueItem( array( 'headers' => Serializer::encode( array( $header, 'X-Kept: yes' ) ) ) );

        BatchProcessor::run();

        $mailer = tests_retrieve_phpmailer_instance();
        self::assertSame( 'captured.example.test', $mailer->Host );
        self::assertSame( 'captured-secret', $mailer->Password );
        self::assertSame( 12, (int) $mailer->Timeout );
        self::assertStringNotContainsString( 'X-TMQ-PHPMailer-Config', $mailer->get_sent()->header );
        self::assertStringContainsString( 'X-Kept: yes', $mailer->get_sent()->header );
    }

    public function test_a_send_that_fails_quietly_leaves_a_reason(): void {
        $id = $this->insertQueueItem();
        // A transport that answers false without throwing, so no
        // wp_mail_failed handler writes a reason first.
        $GLOBALS['phpmailer'] = new class() extends \MockPHPMailer {
            public function send() {
                return false;
            }
        };

        BatchProcessor::run();

        self::assertNotSame( 'sent', $this->status( $id ) );
        self::assertStringContainsString( 'wp_mail() returned false', (string) $this->row( $id )['info'] );
        self::assertSame( 1, (int) get_option( 'wp_tmq_last_cron' )['errors'] );
    }

    public function test_a_core_template_set_to_skip_the_wrapper_is_sent_raw(): void {
        $source = SourcesRepository::register( 'wp_core:password_reset', 'Password reset', 'WordPress Core' );
        SourcesRepository::updateTemplateOverrides( $source, '', '', true );
        $this->insertQueueItem( array( 'source_key' => 'wp_core:password_reset', 'message' => 'Raw body' ) );

        BatchProcessor::run();

        self::assertSame( 'Raw body', trim( tests_retrieve_phpmailer_instance()->get_sent()->body ) );
        self::assertFalse( has_filter( 'wp_tmq_template_skip', '__return_true' ), 'The skip is scoped to that one send.' );
    }
}
