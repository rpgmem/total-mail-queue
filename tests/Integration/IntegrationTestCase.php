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

    /** @var array<string,mixed> */
    protected array $options;

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        $this->wpdb    = new MockWpdb();
        $this->options = $this->defaultOptions();
        $GLOBALS['wpdb'] = $this->wpdb;

        // Stub get_option so the namespaced Settings\Options::get() (which
        // every queue/cron/smtp class reads) sees the test's option values.
        \Brain\Monkey\Functions\when( 'get_option' )->alias( function ( $key, $default = false ) {
            if ( 'wp_tmq_settings' === $key ) {
                return $this->options;
            }
            return $default;
        } );

        // Reset the in-process tracker between tests.
        \TotalMailQueue\Queue\Tracker::reset();
    }

    protected function tearDown(): void {
        \TotalMailQueue\Queue\Tracker::reset();
        Monkey\tearDown();
        unset( $GLOBALS['wpdb'] );
        parent::tearDown();
    }

    /**
     * The user-facing (pre-conversion) settings as a fresh install would store
     * them. {@see \TotalMailQueue\Settings\Options::get()} converts queue_interval
     * (minutes → seconds) and clear_queue (days → hours) on the way out.
     */
    protected function defaultOptions(): array {
        return array(
            'enabled'             => '0',
            'alert_enabled'       => '0',
            'email'               => 'admin@example.test',
            'email_amount'        => '10',
            'queue_amount'        => '1',
            'queue_interval'      => '5',
            'queue_interval_unit' => 'minutes',
            'clear_queue'         => '14',
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
