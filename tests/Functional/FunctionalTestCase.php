<?php

declare(strict_types=1);

namespace TMQ\Tests\Functional;

use TotalMailQueue\Database\Migrator;
use TotalMailQueue\Database\Schema;
use TotalMailQueue\Support\Encryption;
use TotalMailQueue\Support\RuntimeState;
use WP_UnitTestCase;

/**
 * Base class for tests that exercise real WordPress + database integration.
 *
 * Each test starts with: plugin activated, tables present, options reset
 * to defaults, and queue/log/SMTP tables truncated.
 */
abstract class FunctionalTestCase extends WP_UnitTestCase {

    protected function setUp(): void {
        parent::setUp();

        // Each test case is its own scenario; clear every per-request static
        // (BatchProcessor's invocation guard, Detector's source marker,
        // Tracker's in-flight mail id, etc.) in one shot.
        RuntimeState::reset();

        // Refresh schema in case a previous test dropped tables.
        Migrator::install();

        // Reset options to a known baseline so tests can reason about state.
        delete_option( 'wp_tmq_settings' );
        delete_option( 'wp_tmq_last_cron' );

        // Clear plugin tables.
        global $wpdb;
        $wpdb->query( "TRUNCATE TABLE `{$this->queueTable()}`" );
        $wpdb->query( "TRUNCATE TABLE `{$this->smtpTable()}`" );
    }

    /**
     * Persist new plugin settings.
     *
     * @param array<string,mixed> $overrides
     */
    protected function setPluginOptions( array $overrides ): void {
        $current = get_option( 'wp_tmq_settings', array() );
        if ( ! is_array( $current ) ) {
            $current = array();
        }
        update_option( 'wp_tmq_settings', array_merge( $current, $overrides ) );
    }

    protected function queueTable(): string {
        return Schema::queueTable();
    }

    protected function smtpTable(): string {
        return Schema::smtpTable();
    }

    /**
     * Insert a row into the queue table and return its id.
     *
     * @param array<string,mixed> $data Overrides for the default record.
     */
    protected function insertQueueItem( array $data = array() ): int {
        global $wpdb;
        $defaults = array(
            'timestamp'   => current_time( 'mysql', false ),
            'recipient'   => wp_json_encode( 'user@example.test' ),
            'subject'     => 'Subject',
            'message'     => 'Body',
            'status'      => 'queue',
            'headers'     => '',
            'attachments' => '',
            'info'        => '',
            'retry_count' => 0,
        );
        $wpdb->insert( $this->queueTable(), array_merge( $defaults, $data ) );
        return (int) $wpdb->insert_id;
    }

    protected function insertSmtpAccount( array $data = array() ): int {
        global $wpdb;
        $defaults = array(
            'name'          => 'Test SMTP',
            'host'          => 'smtp.example.test',
            'port'          => 587,
            'encryption'    => 'tls',
            'auth'          => 1,
            'username'      => 'user',
            'password'      => Encryption::encrypt( 'pass' ),
            'from_email'    => 'noreply@example.test',
            'from_name'     => 'Example',
            'priority'      => 0,
            'daily_limit'   => 0,
            'monthly_limit' => 0,
            'enabled'       => 1,
            'send_interval' => 0,
            'send_bulk'     => 0,
        );
        $wpdb->insert( $this->smtpTable(), array_merge( $defaults, $data ) );
        return (int) $wpdb->insert_id;
    }
}
