<?php

declare(strict_types=1);

namespace TMQ\Tests\Functional;

use TotalMailQueue\Cron\BatchProcessor;
use TotalMailQueue\Queue\MailInterceptor;
use TotalMailQueue\Sources\Detector;

/**
 * Regression for the cron-context capture fix in 2.5.2.
 *
 * Pre-2.5.2 the interceptor short-circuited `register()` whenever
 * `wp_doing_cron()` returned true, on the assumption that the only cron
 * caller would be the plugin's own queue drainer. That assumption breaks
 * down for any plugin that defers user-creation / order-dispatch /
 * abandoned-cart emails to a custom WP-Cron event — those sends slipped
 * past the interceptor and never landed in the queue / log. Worse, in
 * block mode they were actually delivered (block mode silently failed
 * for cron-originated mail).
 *
 * The fix replaces the `wp_doing_cron()` guard with a precise
 * `BatchProcessor::isDraining()` check that's only true while our drain
 * loop is iterating queued rows.
 *
 * @covers \TotalMailQueue\Queue\MailInterceptor::register
 * @covers \TotalMailQueue\Queue\MailInterceptor::handle
 * @covers \TotalMailQueue\Cron\BatchProcessor::isDraining
 */
final class CronContextCaptureTest extends FunctionalTestCase {

	protected function setUp(): void {
		parent::setUp();
		Detector::reset();
		BatchProcessor::reset();
		$this->setPluginOptions( array( 'enabled' => '1' ) );
		// Strip any prior registration so each test re-registers in its
		// own scenario (Plugin::boot may have wired the filter at bootstrap).
		remove_filter( 'pre_wp_mail', array( MailInterceptor::class, 'handle' ), MailInterceptor::FILTER_PRIORITY );
	}

	public function test_register_no_longer_skips_in_cron_context(): void {
		add_filter( 'wp_doing_cron', '__return_true' );

		MailInterceptor::register();

		self::assertSame(
			MailInterceptor::FILTER_PRIORITY,
			has_filter( 'pre_wp_mail', array( MailInterceptor::class, 'handle' ) ),
			'MailInterceptor must register pre_wp_mail even when WP is in cron context — that is exactly when other plugins\' deferred sends fire.'
		);

		remove_filter( 'wp_doing_cron', '__return_true' );
	}

	public function test_register_still_skips_when_disabled(): void {
		$this->setPluginOptions( array( 'enabled' => '0' ) );

		MailInterceptor::register();

		self::assertFalse(
			has_filter( 'pre_wp_mail', array( MailInterceptor::class, 'handle' ) ),
			'Disabled mode (enabled=0) still bypasses the interceptor.'
		);
	}

	public function test_handle_short_circuits_when_drainer_is_active(): void {
		$reflection = new \ReflectionClass( BatchProcessor::class );
		$prop       = $reflection->getProperty( 'is_draining' );
		$prop->setAccessible( true );
		$prop->setValue( null, true );

		try {
			$result = MailInterceptor::handle(
				null,
				array(
					'to'          => 'a@example.test',
					'subject'     => 'X',
					'message'     => 'Y',
					'headers'     => '',
					'attachments' => array(),
				)
			);

			self::assertNull(
				$result,
				'handle() must return null while the BatchProcessor is draining — otherwise the drainer\'s own wp_mail() calls would be re-queued.'
			);

			global $wpdb;
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$this->queueTable()}`" );
			self::assertSame( 0, $count, 'No queue row inserted while draining.' );
		} finally {
			$prop->setValue( null, false );
		}
	}

	public function test_cron_originated_wp_mail_lands_in_queue(): void {
		add_filter( 'wp_doing_cron', '__return_true' );
		MailInterceptor::register();

		Detector::setCurrent( 'plugin:ffcertificate', 'FFCertificate', 'Plugins' );
		wp_mail( 'user@example.test', 'Welcome', 'You created a new account.' );

		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row( "SELECT * FROM `{$this->queueTable()}` ORDER BY id DESC LIMIT 1", ARRAY_A );
		self::assertNotNull( $row, 'Cron-originated wp_mail must land in the queue table.' );
		self::assertSame( 'plugin:ffcertificate', $row['source_key'] );
		self::assertSame( 'queue', $row['status'] );

		remove_filter( 'wp_doing_cron', '__return_true' );
	}

	public function test_block_mode_in_cron_context_retains_emails(): void {
		// Pre-fix bug: block mode + cron context = emails actually sent.
		// Post-fix: emails captured into the queue, never delivered.
		$this->setPluginOptions( array( 'enabled' => '2' ) );
		add_filter( 'wp_doing_cron', '__return_true' );
		MailInterceptor::register();

		Detector::setCurrent( 'plugin:ffcertificate', 'FFCertificate', 'Plugins' );
		wp_mail( 'user@example.test', 'Welcome', 'You created a new account.' );

		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$this->queueTable()}`" );
		self::assertSame( 1, $count, 'Block mode in cron must still capture the email — previously they leaked.' );

		remove_filter( 'wp_doing_cron', '__return_true' );
	}

	public function test_batch_processor_isDraining_default_is_false(): void {
		BatchProcessor::reset();
		self::assertFalse(
			BatchProcessor::isDraining(),
			'After reset() the drainer flag must be false — tests assume this baseline.'
		);
	}
}
