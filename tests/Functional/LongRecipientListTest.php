<?php

declare(strict_types=1);

namespace TMQ\Tests\Functional;

use TotalMailQueue\Cron\BatchProcessor;
use TotalMailQueue\Database\Migrator;
use TotalMailQueue\Queue\MailInterceptor;
use TotalMailQueue\Queue\QueueRepository;

/**
 * A recipient list longer than 255 characters is stored and sent whole (#82).
 *
 * @covers \TotalMailQueue\Database\Schema
 * @covers \TotalMailQueue\Queue\QueueRepository
 * @covers \TotalMailQueue\Queue\MailInterceptor
 * @covers \TotalMailQueue\Cron\BatchProcessor
 * @covers \TotalMailQueue\Cron\CronLock
 */
final class LongRecipientListTest extends FunctionalTestCase {

    protected function setUp(): void {
        parent::setUp();
        reset_phpmailer_instance();
        $this->setPluginOptions( array( 'enabled' => '1', 'queue_amount' => 1 ) );
        // Plugin::boot() ran before the option was set, so hook the interceptor here.
        MailInterceptor::register();
    }

    protected function tearDown(): void {
        remove_all_filters( 'pre_wp_mail' );
        parent::tearDown();
    }

    public function test_the_recipient_column_is_text(): void {
        self::assertSame( 'text', $this->recipientType() );
    }

    public function test_an_upgrade_widens_the_old_varchar_column_and_keeps_its_rows(): void {
        global $wpdb;
        $wpdb->query( "ALTER TABLE `{$this->queueTable()}` MODIFY `recipient` varchar(255) DEFAULT '' NOT NULL" );
        // A raw INSERT: $wpdb->insert() would cache the old column length for
        // the rest of the process and reject the long list in the next test.
        $wpdb->query( $wpdb->prepare( "INSERT INTO `{$this->queueTable()}` (`timestamp`, `recipient`, `subject`, `message`, `status`, `headers`, `attachments`, `error_log`) VALUES (NOW(), %s, 'S', 'M', 'sent', '', '', '')", wp_json_encode( 'kept@example.test' ) ) );
        $id = (int) $wpdb->insert_id;
        self::assertSame( 'varchar(255)', $this->recipientType(), 'The test must start from the old column.' );

        Migrator::install();

        self::assertSame( 'text', $this->recipientType() );
        self::assertSame( wp_json_encode( 'kept@example.test' ), QueueRepository::findById( $id )['recipient'] );
    }

    public function test_an_email_to_many_recipients_is_queued_and_sent_to_all_of_them(): void {
        $to = array();
        for ( $i = 1; $i <= 30; $i++ ) {
            $to[] = sprintf( 'recipient-number-%02d@example.test', $i );
        }
        self::assertGreaterThan( 255, strlen( (string) wp_json_encode( $to ) ), 'The list must not fit the old column.' );

        global $wpdb;
        self::assertTrue( wp_mail( $to, 'Subject', 'Body' ), (string) $wpdb->last_error );
        BatchProcessor::run();

        $row = $wpdb->get_row( "SELECT * FROM `{$this->queueTable()}` ORDER BY id DESC LIMIT 1", ARRAY_A );
        self::assertSame( 'sent', $row['status'] );
        $sent = array_map(
            static fn ( array $address ): string => $address[0],
            tests_retrieve_phpmailer_instance()->get_sent()->to
        );
        self::assertSame( $to, $sent, 'Every recipient must receive the email, not the alert address.' );
    }

    public function test_an_insert_without_a_recipient_still_works_in_strict_mode(): void {
        global $wpdb;
        $wpdb->query( "SET SESSION sql_mode = 'STRICT_ALL_TABLES'" );

        $id = QueueRepository::insert( array( 'timestamp' => current_time( 'mysql', false ), 'subject' => 'S', 'message' => 'M', 'status' => 'alert', 'headers' => '', 'attachments' => '' ) );

        self::assertGreaterThan( 0, $id, 'A TEXT column has no SQL default, so insert() must supply one.' );
    }

    private function recipientType(): string {
        global $wpdb;
        $column = $wpdb->get_row( "SHOW COLUMNS FROM `{$this->queueTable()}` LIKE 'recipient'", ARRAY_A );
        return strtolower( (string) $column['Type'] );
    }
}
