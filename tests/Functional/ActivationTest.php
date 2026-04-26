<?php

declare(strict_types=1);

namespace TMQ\Tests\Functional;

/**
 * Verifies that \TotalMailQueue\Database\Migrator::install() (run via the activation hook)
 * creates both plugin tables with the columns and indexes the rest of the
 * code relies on.
 *
 * @covers \TotalMailQueue\Database\Migrator::install
 */
final class ActivationTest extends FunctionalTestCase {

    public function test_activation_creates_main_queue_table(): void {
        global $wpdb;

        $table = $this->queueTable();
        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

        self::assertSame( $table, $exists, 'Activation must create the queue table.' );
    }

    public function test_activation_creates_smtp_accounts_table(): void {
        global $wpdb;

        $table = $this->smtpTable();
        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

        self::assertSame( $table, $exists, 'Activation must create the SMTP accounts table.' );
    }

    public function test_queue_table_has_required_columns(): void {
        global $wpdb;
        $table = $this->queueTable();
        $columns = $wpdb->get_col( "SHOW COLUMNS FROM `$table`" );

        $required = array( 'id', 'timestamp', 'status', 'recipient', 'subject', 'message', 'headers', 'attachments', 'info', 'retry_count', 'smtp_account_id' );
        foreach ( $required as $col ) {
            self::assertContains( $col, $columns, "Queue table is missing the `$col` column." );
        }
    }

    public function test_queue_table_has_status_indexes(): void {
        global $wpdb;
        $table = $this->queueTable();
        $indexes = $wpdb->get_col( "SHOW INDEXES FROM `$table`", 2 ); // column 2 = Key_name

        self::assertContains( 'idx_status_retry', $indexes, 'Activation must create idx_status_retry on the queue table.' );
        self::assertContains( 'idx_status_timestamp', $indexes, 'Activation must create idx_status_timestamp on the queue table.' );
    }

    public function test_smtp_table_has_required_columns(): void {
        global $wpdb;
        $table = $this->smtpTable();
        $columns = $wpdb->get_col( "SHOW COLUMNS FROM `$table`" );

        $required = array(
            'id', 'name', 'host', 'port', 'encryption', 'auth', 'username', 'password',
            'from_email', 'from_name', 'priority', 'daily_limit', 'monthly_limit',
            'daily_sent', 'monthly_sent', 'enabled', 'send_interval', 'send_bulk',
            'cycle_sent', 'last_sent_at',
        );
        foreach ( $required as $col ) {
            self::assertContains( $col, $columns, "SMTP table is missing the `$col` column." );
        }
    }

    public function test_activation_is_idempotent(): void {
        // Running the activation twice must not corrupt the schema.
        \TotalMailQueue\Database\Migrator::install();
        \TotalMailQueue\Database\Migrator::install();

        global $wpdb;
        $table  = $this->queueTable();
        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        self::assertSame( $table, $exists );
    }
}
