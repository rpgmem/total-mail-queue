<?php

declare(strict_types=1);

namespace TMQ\Tests\Functional;

use TotalMailQueue\Queue\MailInterceptor;
use TotalMailQueue\Sources\Detector;
use TotalMailQueue\Templates\Engine;

/**
 * End-to-end check that the template engine runs **before** the queue insert,
 * not at cron drain time. A wp_mail() call in queue mode must persist the
 * fully-wrapped HTML body in the queue row, so what the admin sees in the
 * queue inspector matches what the recipient receives.
 *
 * @covers \TotalMailQueue\Queue\MailInterceptor::handle
 * @covers \TotalMailQueue\Templates\Engine::apply
 */
final class TemplateInterceptionTest extends FunctionalTestCase {

	protected function setUp(): void {
		parent::setUp();
		Detector::reset();
		delete_option( Engine::OPTION_NAME );

		// Plugin::boot() already wired the engine's wp_mail filters with the
		// bootstrap defaults — strip them so each test re-registers under its
		// own scenario.
		remove_filter( 'wp_mail', array( Engine::class, 'apply' ), Engine::FILTER_PRIORITY );
		remove_filter( 'wp_mail_content_type', array( Engine::class, 'forceHtmlContentType' ), Engine::FILTER_PRIORITY );
		remove_filter( 'wp_mail_from', array( Engine::class, 'overrideFromEmail' ), Engine::FILTER_PRIORITY );
		remove_filter( 'wp_mail_from_name', array( Engine::class, 'overrideFromName' ), Engine::FILTER_PRIORITY );

		// Sources table accumulates across tests (FunctionalTestCase does not
		// truncate it); reset here so each test starts from a clean catalog.
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE `" . \TotalMailQueue\Database\Schema::sourcesTable() . "`" );
	}

	public function test_queue_mode_persists_html_wrapped_body(): void {
		$this->setPluginOptions( array( 'enabled' => '1' ) );
		Engine::register();
		MailInterceptor::register();

		wp_mail( 'user@example.test', 'Subject', 'Plain text body' );

		global $wpdb;
		$row = $wpdb->get_row( "SELECT * FROM `{$this->queueTable()}` ORDER BY id DESC LIMIT 1", ARRAY_A );
		self::assertNotNull( $row );
		self::assertSame( 'queue', $row['status'] );
		self::assertStringContainsString( '<!DOCTYPE html>', $row['message'], 'Queue row must already hold the wrapped HTML, not the raw plain-text body.' );
		self::assertStringContainsString( 'Plain text body', $row['message'] );
		self::assertStringContainsString( '</html>', $row['message'] );
	}

	public function test_block_mode_persists_raw_body_unwrapped(): void {
		// Block mode: nothing leaves anyway, wrapping would be wasted CPU.
		$this->setPluginOptions( array( 'enabled' => '2' ) );
		Engine::register();
		MailInterceptor::register();

		wp_mail( 'user@example.test', 'Subject', 'Plain text body' );

		global $wpdb;
		$row = $wpdb->get_row( "SELECT * FROM `{$this->queueTable()}` ORDER BY id DESC LIMIT 1", ARRAY_A );
		self::assertNotNull( $row );
		self::assertSame( 'Plain text body', $row['message'], 'Block mode must not waste cycles wrapping a row that will never send.' );
	}

	public function test_queue_mode_with_engine_disabled_persists_raw_body(): void {
		$this->setPluginOptions( array( 'enabled' => '1' ) );
		update_option( Engine::OPTION_NAME, array( 'enabled' => false ) );
		Engine::register();
		MailInterceptor::register();

		wp_mail( 'user@example.test', 'Subject', 'Plain text body' );

		global $wpdb;
		$row = $wpdb->get_row( "SELECT * FROM `{$this->queueTable()}` ORDER BY id DESC LIMIT 1", ARRAY_A );
		self::assertNotNull( $row );
		self::assertSame( 'Plain text body', $row['message'], 'When the templates toggle is off, the wrapper must not run.' );
	}

	public function test_blocked_by_source_does_not_get_wrapped(): void {
		// A row that will never send shouldn't waste cycles being wrapped.
		$this->setPluginOptions( array( 'enabled' => '1' ) );
		\TotalMailQueue\Sources\Repository::register( 'plugin:woocommerce', 'WooCommerce', 'Plugins' );
		$src_id = (int) \TotalMailQueue\Sources\Repository::findByKey( 'plugin:woocommerce' )['id'];
		\TotalMailQueue\Sources\Repository::setEnabled( $src_id, false );
		Detector::setCurrent( 'plugin:woocommerce', 'WooCommerce', 'Plugins' );
		Engine::register();
		MailInterceptor::register();

		wp_mail( 'user@example.test', 'Subject', 'Plain text body' );

		global $wpdb;
		$row = $wpdb->get_row( "SELECT * FROM `{$this->queueTable()}` ORDER BY id DESC LIMIT 1", ARRAY_A );
		self::assertNotNull( $row );
		self::assertSame( 'blocked_by_source', $row['status'] );
		self::assertSame( 'Plain text body', $row['message'], 'Blocked rows are never sent — wrapping them is dead work.' );
	}
}
