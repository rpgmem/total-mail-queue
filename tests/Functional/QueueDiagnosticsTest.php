<?php

declare(strict_types=1);

namespace TMQ\Tests\Functional;

use TotalMailQueue\Admin\Pages\CronInfoPage;
use TotalMailQueue\Admin\QueueDiagnostics;
use TotalMailQueue\Settings\Options;

/**
 * The notices above the queue (mode, next run, last run, pre_wp_mail
 * conflicts) and the Cron Information tab's overdue-job check.
 *
 * @covers \TotalMailQueue\Admin\QueueDiagnostics
 * @covers \TotalMailQueue\Admin\Pages\CronInfoPage
 */
final class QueueDiagnosticsTest extends FunctionalTestCase {

    /**
     * Name of a function hooked on pre_wp_mail for the conflict check.
     */
    public const FOREIGN = 'tmq_tests_foreign_pre_wp_mail';

    protected function setUp(): void {
        parent::setUp();
        // Activation arms the worker; each test arms the event it needs.
        wp_clear_scheduled_hook( 'wp_tmq_mail_queue_hook' );
    }

    protected function tearDown(): void {
        remove_all_filters( 'pre_wp_mail' );
        wp_clear_scheduled_hook( 'wp_tmq_mail_queue_hook' );
        wp_clear_scheduled_hook( 'tmq_tests_stale_job' );
        parent::tearDown();
    }

    /**
     * Render a static renderer with the live options.
     *
     * @param callable $render Renderer taking the options array.
     */
    private function capture( callable $render ): string {
        ob_start();
        $render( Options::get() );
        return (string) ob_get_clean();
    }

    public function test_block_mode_says_nothing_leaves_the_server(): void {
        $this->setPluginOptions( array( 'enabled' => '2' ) );

        self::assertStringContainsString( 'Block Mode Active', $this->capture( array( QueueDiagnostics::class, 'renderModeNotice' ) ) );
    }

    public function test_disabled_mode_points_to_the_settings(): void {
        $this->setPluginOptions( array( 'enabled' => '0' ) );

        self::assertStringContainsString( 'The plugin is currently disabled.', $this->capture( array( QueueDiagnostics::class, 'renderModeNotice' ) ) );
    }

    public function test_queue_mode_shows_the_next_run_and_the_last_run_metrics(): void {
        $this->setPluginOptions( array( 'enabled' => '1' ) );
        // Saving the settings rearms the worker for now; replace it with a future run.
        wp_clear_scheduled_hook( 'wp_tmq_mail_queue_hook' );
        wp_schedule_single_event( time() + 300, 'wp_tmq_mail_queue_hook' );
        update_option(
            'wp_tmq_last_cron',
            array(
                'time'          => '2026-10-12 10:00:00',
                'result'        => 'ok',
                'queue_total'   => 12,
                'queue_batch'   => 5,
                'smtp_accounts' => 2,
                'send_method'   => 'smtp',
                'sent'          => 5,
                'errors'        => 0,
            )
        );

        $html = $this->capture( array( QueueDiagnostics::class, 'renderModeNotice' ) );

        self::assertStringContainsString( 'Next sending will be triggered in 5 minutes', $html );
        self::assertStringContainsString( 'notice-info', $html );
        self::assertStringContainsString( '12 total, 5 batch', $html );
        self::assertStringContainsString( '<strong>Send method:</strong> smtp', $html );
    }

    public function test_a_last_run_that_did_not_succeed_is_a_warning_with_only_what_it_recorded(): void {
        $this->setPluginOptions( array( 'enabled' => '1' ) );
        update_option( 'wp_tmq_last_cron', array( 'time' => '2026-10-12 10:00:00', 'result' => 'locked' ) );

        $html = $this->capture( array( QueueDiagnostics::class, 'renderModeNotice' ) );

        self::assertStringContainsString( 'notice-warning', $html );
        self::assertStringContainsString( 'locked', $html );
        self::assertStringNotContainsString( 'SMTP accounts:', $html );
        self::assertStringNotContainsString( 'Next sending', $html, 'No armed worker, no countdown.' );
    }

    public function test_foreign_pre_wp_mail_callbacks_are_named_and_our_own_is_not(): void {
        $this->setPluginOptions( array( 'enabled' => '1' ) );
        remove_all_filters( 'pre_wp_mail' );
        add_filter( 'pre_wp_mail', array( \TotalMailQueue\Queue\MailInterceptor::class, 'handle' ) );
        self::assertSame( '', $this->capture( array( QueueDiagnostics::class, 'renderConflictNotice' ) ) );

        add_filter( 'pre_wp_mail', self::FOREIGN );
        add_filter( 'pre_wp_mail', array( $this, 'tearDown' ) );
        add_filter( 'pre_wp_mail', static fn( $v ) => $v );

        $html = $this->capture( array( QueueDiagnostics::class, 'renderConflictNotice' ) );

        self::assertStringContainsString( 'conflicting email plugin detected', $html );
        self::assertStringContainsString( self::FOREIGN, $html );
        self::assertStringContainsString( self::class . '::tearDown', $html );
        self::assertStringContainsString( '(closure)', $html );
        self::assertStringNotContainsString( 'MailInterceptor', $html );
    }

    public function test_the_conflict_check_is_silent_outside_queue_mode_or_without_filters(): void {
        add_filter( 'pre_wp_mail', self::FOREIGN );
        $this->setPluginOptions( array( 'enabled' => '2' ) );
        self::assertSame( '', $this->capture( array( QueueDiagnostics::class, 'renderConflictNotice' ) ) );

        $this->setPluginOptions( array( 'enabled' => '1' ) );
        global $wp_filter;
        $saved = $wp_filter['pre_wp_mail'];
        unset( $wp_filter['pre_wp_mail'] );
        self::assertSame( '', $this->capture( array( QueueDiagnostics::class, 'renderConflictNotice' ) ) );
        $wp_filter['pre_wp_mail'] = $saved;
    }

    public function test_cron_info_explains_disabled_cron_and_flags_an_overdue_queue(): void {
        $html = $this->capture( array( CronInfoPage::class, 'render' ) );
        self::assertStringContainsString( '/wp-cron.php', $html );

        wp_schedule_single_event( time() - 3 * HOUR_IN_SECONDS, 'wp_tmq_mail_queue_hook' );
        $html = $this->capture( array( CronInfoPage::class, 'render' ) );

        self::assertStringContainsString( 'your WP Cron is not running', $html );
        self::assertStringContainsString( 'been able to be executed since 3 hours', $html );
    }

    public function test_cron_info_flags_other_overdue_jobs_without_blaming_the_queue(): void {
        wp_schedule_single_event( time() - 3 * HOUR_IN_SECONDS, 'tmq_tests_stale_job' );

        $html = $this->capture( array( CronInfoPage::class, 'render' ) );

        self::assertStringContainsString( 'your WP Cron is not running', $html );
        self::assertStringNotContainsString( 'The Queue hasn', $html );
    }
}
