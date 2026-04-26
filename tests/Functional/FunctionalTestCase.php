<?php

declare(strict_types=1);

namespace TMQ\Tests\Functional;

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

        // Each test case is its own scenario; reset the namespaced singletons
        // (BatchProcessor's invocation guard, Tracker's in-flight mail id).
        \TotalMailQueue\Cron\BatchProcessor::reset();
        \TotalMailQueue\Queue\Tracker::reset();

        global $wpdb, $wp_tmq_options;

        // Refresh schema in case a previous test dropped tables.
        \TotalMailQueue\Database\Migrator::install();

        // Reset options to a known baseline so tests can reason about state.
        delete_option( 'wp_tmq_settings' );
        delete_option( 'wp_tmq_last_cron' );
        $wp_tmq_options = \TotalMailQueue\Settings\Options::get();

        // Clear plugin tables.
        $main = $wpdb->prefix . $wp_tmq_options['tableName'];
        $smtp = $wpdb->prefix . $wp_tmq_options['smtpTableName'];
        $wpdb->query( "TRUNCATE TABLE `$main`" );
        $wpdb->query( "TRUNCATE TABLE `$smtp`" );

        $GLOBALS['wp_tmq_mailid'] = 0;
    }

    /**
     * Persist new plugin settings, then reload the in-memory cache.
     *
     * @param array<string,mixed> $overrides
     */
    protected function setPluginOptions( array $overrides ): void {
        global $wp_tmq_options;
        $current = get_option( 'wp_tmq_settings', array() );
        if ( ! is_array( $current ) ) {
            $current = array();
        }
        update_option( 'wp_tmq_settings', array_merge( $current, $overrides ) );
        $wp_tmq_options = \TotalMailQueue\Settings\Options::get();
    }

    protected function queueTable(): string {
        global $wpdb, $wp_tmq_options;
        return $wpdb->prefix . $wp_tmq_options['tableName'];
    }

    protected function smtpTable(): string {
        global $wpdb, $wp_tmq_options;
        return $wpdb->prefix . $wp_tmq_options['smtpTableName'];
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
            'password'      => \TotalMailQueue\Support\Encryption::encrypt( 'pass' ),
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
