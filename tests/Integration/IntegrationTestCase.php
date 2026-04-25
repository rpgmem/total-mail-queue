<?php

declare(strict_types=1);

namespace TMQ\Tests\Integration;

use Brain\Monkey;
use PHPUnit\Framework\TestCase;
use TMQ\Tests\Support\MockWpdb;

/**
 * Base class for integration tests.
 *
 * Brings up Brain Monkey (Mockery + Patchwork) so that WordPress functions
 * defined in tests/Stubs/wordpress-stubs.php can be re-stubbed per test, and
 * installs a fresh MockWpdb on $GLOBALS['wpdb'] for each test.
 */
abstract class IntegrationTestCase extends TestCase {

    protected MockWpdb $wpdb;

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        $this->wpdb = new MockWpdb();
        $GLOBALS['wpdb'] = $this->wpdb;

        // Reset module-level globals so each test starts clean.
        $GLOBALS['wp_tmq_mailid']   = 0;
        $GLOBALS['wp_tmq_options']  = $this->defaultOptions();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        unset( $GLOBALS['wpdb'], $GLOBALS['wp_tmq_options'], $GLOBALS['wp_tmq_mailid'] );
        parent::tearDown();
    }

    /**
     * Mirrors the values wp_tmq_get_settings() would produce on a fresh install.
     */
    protected function defaultOptions(): array {
        return array(
            'enabled'             => '0',
            'alert_enabled'       => '0',
            'email'               => 'admin@example.test',
            'email_amount'        => '10',
            'queue_amount'        => '1',
            'queue_interval'      => 300,
            'queue_interval_unit' => 'minutes',
            'clear_queue'         => 14 * 24,
            'log_max_records'     => '0',
            'send_method'         => 'auto',
            'max_retries'         => '3',
            'cron_lock_ttl'       => '300',
            'smtp_timeout'        => '30',
            'tableName'           => 'total_mail_queue',
            'smtpTableName'       => 'total_mail_queue_smtp',
            'triggercount'        => 0,
        );
    }
}
