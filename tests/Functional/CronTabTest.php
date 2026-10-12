<?php

declare(strict_types=1);

namespace TMQ\Tests\Functional;

use TotalMailQueue\Admin\Access;
use TotalMailQueue\Admin\Pages\CronInfoPage;
use TotalMailQueue\Cron\Diagnostics;
use TotalMailQueue\Cron\Scheduler;
use TotalMailQueue\Settings\Options;

/**
 * The Cron tab: what it says about the worker in each state, WP-Cron's
 * trigger and overdue events, and the server cron line it builds.
 *
 * @covers \TotalMailQueue\Admin\Pages\CronInfoPage
 */
final class CronTabTest extends FunctionalTestCase {

    protected function setUp(): void {
        parent::setUp();
        wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
        $this->setPluginOptions( array( 'enabled' => '1', 'queue_interval' => 5, 'queue_interval_unit' => 'minutes' ) );
        wp_clear_scheduled_hook( Scheduler::HOOK );
        delete_option( Diagnostics::LAST_SEND_OPTION );
    }

    protected function tearDown(): void {
        wp_clear_scheduled_hook( Scheduler::HOOK );
        wp_clear_scheduled_hook( 'tmq_tests_stale_job' );
        delete_option( Diagnostics::LAST_SEND_OPTION );
        Access::revokeAll();
        parent::tearDown();
    }

    private function worker(): string {
        ob_start();
        CronInfoPage::renderWorker( Options::get() );
        return (string) ob_get_clean();
    }

    private function wpCron( bool $disabled ): string {
        ob_start();
        CronInfoPage::renderWpCron( Options::get(), $disabled );
        return (string) ob_get_clean();
    }

    public function test_the_worker_state_follows_the_mode_the_queue_and_the_schedule(): void {
        $now = 1_000_000;

        self::assertSame( 'off', CronInfoPage::workerState( '2', 5, null, $now, 300 ) );
        self::assertSame( 'idle', CronInfoPage::workerState( '1', 0, null, $now, 300 ) );
        self::assertSame( 'stalled', CronInfoPage::workerState( '1', 3, null, $now, 300 ) );
        self::assertSame( 'active', CronInfoPage::workerState( '1', 3, $now + 300, $now, 300 ) );
        self::assertSame( 'active', CronInfoPage::workerState( '1', 3, $now - 300, $now, 300 ), 'A little late is still running.' );
        self::assertSame( 'overdue', CronInfoPage::workerState( '1', 3, $now - 601, $now, 300 ) );
        self::assertSame( 'overdue', CronInfoPage::workerState( '1', 3, $now - 601, $now, 10 ), 'Short intervals get a ten-minute grace.' );
        self::assertSame( 'active', CronInfoPage::workerState( '1', 3, $now - 599, $now, 10 ) );
        self::assertSame( 'deferred', CronInfoPage::workerState( '1', 3, $now + 361, $now, 300 ) );
    }

    public function test_an_idle_worker_with_no_history_says_so(): void {
        $html = $this->worker();

        self::assertStringContainsString( 'Asleep: the queue is empty.', $html );
        self::assertStringContainsString( 'Not scheduled', $html );
        self::assertStringContainsString( 'Every 5 minutes', $html );
        self::assertSame( 2, substr_count( $html, 'Never' ), 'No last run and no last send.' );
    }

    public function test_a_running_worker_shows_its_next_and_last_run(): void {
        $this->insertQueueItem();
        wp_schedule_single_event( time() + 120, Scheduler::HOOK );
        update_option(
            Diagnostics::OPTION_NAME,
            array(
                'time'   => current_time( 'mysql', false ),
                'result' => 'ok',
                'sent'   => 4,
                'errors' => 1,
            )
        );
        update_option( Diagnostics::LAST_SEND_OPTION, current_time( 'mysql', false ) );

        $html = $this->worker();

        self::assertStringContainsString( '<span class="tmq-ok">Running at the configured interval.</span>', $html );
        self::assertMatchesRegularExpression( '/<th scope="row">Next run<\/th><td>in 2 minutes \(/', $html );
        self::assertStringContainsString( '— ok (4 sent, 1 errors)', $html );
        self::assertMatchesRegularExpression( '/<th scope="row">Last email sent<\/th><td>1 second ago \(/', $html );
        self::assertMatchesRegularExpression( '/Waiting in the queue<\/th><td>1</', $html );
    }

    public function test_a_stalled_overdue_or_stopped_worker_is_named(): void {
        $this->insertQueueItem();
        self::assertStringContainsString( 'class="tmq-warning">Stalled', $this->worker() );

        wp_schedule_single_event( time() - HOUR_IN_SECONDS, Scheduler::HOOK );
        self::assertStringContainsString( 'class="tmq-warning">Overdue', $this->worker() );

        $this->setPluginOptions( array( 'enabled' => '2' ) );
        self::assertStringContainsString( '<td>Not running: the plugin is not in Queue mode', $this->worker() );
    }

    public function test_a_last_run_without_counts_shows_its_result_alone(): void {
        update_option( Diagnostics::OPTION_NAME, array( 'time' => current_time( 'mysql', false ), 'result' => 'skipped: duplicate trigger' ) );

        $html = $this->worker();

        self::assertStringContainsString( '— skipped: duplicate trigger</td>', $html );
        self::assertStringNotContainsString( 'sent,', $html );
    }

    public function test_the_wp_cron_card_explains_the_trigger_either_way(): void {
        self::assertStringContainsString( 'WP-Cron runs when someone visits the site.', $this->wpCron( false ) );
        self::assertStringContainsString( "define( 'DISABLE_WP_CRON', true );", $this->wpCron( false ) );

        $disabled = $this->wpCron( true );
        self::assertStringContainsString( 'The visit trigger is off', $disabled );
        self::assertStringNotContainsString( 'someone visits', $disabled );
    }

    public function test_overdue_events_are_counted_and_the_queue_worker_is_named(): void {
        self::assertStringContainsString( 'No scheduled event is overdue.', $this->wpCron( true ) );

        wp_schedule_single_event( time() - 3 * HOUR_IN_SECONDS, 'tmq_tests_stale_job' );
        $html = $this->wpCron( true );
        self::assertStringContainsString( 'scheduled event', $html );
        self::assertStringContainsString( 'overdue: WP-Cron does not seem to be running.', $html );
        self::assertStringNotContainsString( 'The queue worker has been waiting', $html );

        wp_schedule_single_event( time() - 2 * HOUR_IN_SECONDS, Scheduler::HOOK );
        self::assertStringContainsString( 'The queue worker has been waiting to run for 2 hours.', $this->wpCron( true ) );
    }

    public function test_overdue_events_keep_the_earliest_time_per_hook(): void {
        $now  = time();
        $cron = _get_cron_array();
        $cron[ $now - 7200 ]['tmq_tests_stale_job'] = array( 'a' => array( 'schedule' => false, 'args' => array() ) );
        $cron[ $now - 3600 ]['tmq_tests_stale_job'] = array( 'b' => array( 'schedule' => false, 'args' => array( 1 ) ) );
        $cron[ $now - 10 ]['tmq_tests_recent']      = array( 'c' => array( 'schedule' => false, 'args' => array() ) );
        _set_cron_array( $cron );

        $late = CronInfoPage::overdueEvents( 300, $now );

        self::assertSame( $now - 7200, $late['tmq_tests_stale_job'] );
        self::assertArrayNotHasKey( 'tmq_tests_recent', $late, 'Within one interval is not overdue.' );
        wp_clear_scheduled_hook( 'tmq_tests_recent' );
    }

    public function test_server_lines_quote_the_path_and_the_url(): void {
        self::assertSame(
            "*/5 * * * * cd '/srv/my site/it'\\''s' && wp cron event run --due-now --quiet",
            CronInfoPage::serverLine( 'wpcli', 5, "/srv/my site/it's/", 'https://example.test/wp-cron.php?doing_wp_cron' )
        );
        self::assertSame(
            "* * * * * wget -q -O - 'https://example.test/wp-cron.php?doing_wp_cron' >/dev/null 2>&1",
            CronInfoPage::serverLine( 'wget', 1, '/srv', 'https://example.test/wp-cron.php?doing_wp_cron' )
        );
        self::assertSame(
            "*/15 * * * * curl -s 'https://example.test/wp-cron.php?doing_wp_cron' >/dev/null 2>&1",
            CronInfoPage::serverLine( 'curl', 15, '/srv', 'https://example.test/wp-cron.php?doing_wp_cron' )
        );
        self::assertSame( "''", CronInfoPage::shellQuote( '' ) );
    }

    public function test_the_server_card_prints_every_line_and_defaults_to_the_interval(): void {
        ob_start();
        CronInfoPage::renderServerCron( 10 * MINUTE_IN_SECONDS );
        $html = (string) ob_get_clean();

        self::assertStringContainsString( '<option value="10" selected', $html );
        self::assertStringContainsString( 'value="*/10 * * * * cd ', $html, 'The field starts on WP-CLI at the default frequency.' );
        preg_match( '/data-tmq-cron-lines="([^"]+)"/', $html, $m );
        $lines = json_decode( html_entity_decode( $m[1], ENT_QUOTES ), true );
        self::assertCount( count( CronInfoPage::methods() ) * count( CronInfoPage::FREQUENCIES ), $lines );
        self::assertStringContainsString( site_url( 'wp-cron.php?doing_wp_cron' ), $lines['curl:2'] );
        self::assertStringContainsString( 'data-tmq-copy="#tmq-cron-line"', $html );

        ob_start();
        CronInfoPage::renderServerCron( 30 );
        self::assertStringContainsString( '<option value="1" selected', (string) ob_get_clean(), 'An interval under a minute still gets every minute.' );
    }

    public function test_only_administrators_see_the_server_card(): void {
        ob_start();
        CronInfoPage::render();
        $admin = (string) ob_get_clean();
        self::assertStringContainsString( 'Queue worker', $admin );
        self::assertStringContainsString( 'tmq-server-cron', $admin );

        Access::setRole( 'editor', Access::MANAGE, true );
        wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
        ob_start();
        CronInfoPage::render();
        $manager = (string) ob_get_clean();
        self::assertStringContainsString( 'Queue worker', $manager );
        self::assertStringNotContainsString( 'tmq-server-cron', $manager );
        self::assertStringNotContainsString( ABSPATH, $manager );
    }
}
