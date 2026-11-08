<?php

declare(strict_types=1);

namespace TMQ\Tests\Functional;

use TotalMailQueue\Cli\Command;

require_once dirname( __DIR__ ) . '/Support/WpCliStub.php';

/**
 * `wp mail-queue status|send` (#184), through a stand-in for WP-CLI's
 * output API.
 *
 * @covers \TotalMailQueue\Cli\Command
 */
final class CliCommandTest extends FunctionalTestCase {

    protected function setUp(): void {
        parent::setUp();
        reset_phpmailer_instance();
        \WP_CLI::$out = array();
        \TotalMailQueue\Cron\BatchProcessor::reset();
    }

    public function test_status_reports_the_queue(): void {
        $this->setPluginOptions( array( 'enabled' => '1', 'queue_amount' => 7, 'send_method' => 'auto' ) );
        $this->insertQueueItem();
        $this->insertQueueItem();
        wp_clear_scheduled_hook( \TotalMailQueue\Cron\Scheduler::HOOK );

        $data = Command::statusData( time() );

        self::assertSame( 'queue', $data['mode'] );
        self::assertSame( 'auto', $data['send_method'] );
        self::assertSame( 2, $data['pending'] );
        self::assertSame( 7, $data['batch_size'] );
        self::assertSame( 'stalled', $data['worker'], 'Mail waiting and no worker event scheduled.' );
        self::assertNull( $data['last_send'] );
    }

    public function test_status_prints_one_aligned_line_per_value_or_json(): void {
        $this->setPluginOptions( array( 'enabled' => '0' ) );

        ( new Command() )->status( array(), array() );
        self::assertSame( 'line', \WP_CLI::$out[0][0] );
        self::assertMatchesRegularExpression( '/^mode\s+disabled$/', \WP_CLI::$out[0][1] );
        self::assertCount( count( Command::statusData( time() ) ), \WP_CLI::$out );

        \WP_CLI::$out = array();
        ( new Command() )->status( array(), array( 'format' => 'json' ) );
        self::assertSame( 'disabled', json_decode( \WP_CLI::$out[0][1], true )['mode'] );
    }

    public function test_send_runs_one_batch_and_reports_it(): void {
        $this->setPluginOptions( array( 'enabled' => '1', 'queue_amount' => 2 ) );
        for ( $i = 0; $i < 3; $i++ ) {
            $this->insertQueueItem();
        }

        ( new Command() )->send( array(), array() );

        self::assertSame( array( 'success', 'Sent 2, failed 0, still waiting 1.' ), \WP_CLI::$out[0] );
    }

    public function test_send_warns_when_nothing_can_go_out(): void {
        $this->setPluginOptions( array( 'enabled' => '2' ) );
        $this->insertQueueItem();

        ( new Command() )->send( array(), array() );

        self::assertSame( 'warning', \WP_CLI::$out[0][0] );
        self::assertStringContainsString( 'Nothing sent: plugin not in queue mode', \WP_CLI::$out[0][1] );
    }

    public function test_the_command_registers_only_under_wp_cli(): void {
        \WP_CLI::$commands = array();
        Command::register();
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            self::assertArrayHasKey( 'mail-queue', \WP_CLI::$commands );
        } else {
            self::assertSame( array(), \WP_CLI::$commands );
        }
    }
}
