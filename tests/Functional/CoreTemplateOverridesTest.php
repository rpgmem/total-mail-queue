<?php

declare(strict_types=1);

namespace TMQ\Tests\Functional;

use TotalMailQueue\Cron\BatchProcessor;
use TotalMailQueue\Database\Migrator;
use TotalMailQueue\Database\Schema;
use TotalMailQueue\Queue\MailInterceptor;
use TotalMailQueue\Sources\CoreTemplates;
use TotalMailQueue\Sources\Detector;
use TotalMailQueue\Sources\Repository as SourcesRepository;
use TotalMailQueue\Sources\WpVersionNotice;
use TotalMailQueue\Admin\Pages\SourcesPage;

/**
 * v2.6.0 — per-source body & subject overrides for wp_core emails.
 *
 * Covers the critical paths end-to-end: CoreTemplates inventory,
 * Detector per-call context, Repository read/write, MailInterceptor
 * override pipeline, SourcesPage form save / reset, the Sender
 * migration, and the WP version-bump notice.
 *
 * @covers \TotalMailQueue\Sources\CoreTemplates
 * @covers \TotalMailQueue\Sources\Detector::setData
 * @covers \TotalMailQueue\Sources\Detector::consumeData
 * @covers \TotalMailQueue\Sources\Repository::updateTemplateOverrides
 * @covers \TotalMailQueue\Sources\Repository::clearTemplateOverrides
 * @covers \TotalMailQueue\Sources\WpVersionNotice
 * @covers \TotalMailQueue\Queue\MailInterceptor::handle
 * @covers \TotalMailQueue\Admin\Pages\SourcesPage
 * @covers \TotalMailQueue\Database\Migrator::install
 */
final class CoreTemplateOverridesTest extends FunctionalTestCase {

	protected function setUp(): void {
		parent::setUp();
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE `{$this->sourcesTable()}`" );

		$this->setPluginOptions( array( 'enabled' => '1' ) );
		MailInterceptor::register();
	}

	// ------------------------------------------------------------------
	// CoreTemplates inventory
	// ------------------------------------------------------------------

	public function test_core_templates_isCoreTemplate_recognises_supported_keys(): void {
		self::assertTrue( CoreTemplates::isCoreTemplate( 'wp_core:password_reset' ) );
		self::assertTrue( CoreTemplates::isCoreTemplate( 'wp_core:new_user' ) );
		self::assertTrue( CoreTemplates::isCoreTemplate( 'wp_core:recovery_mode' ) );
	}

	public function test_core_templates_isCoreTemplate_rejects_unknown_keys(): void {
		self::assertFalse( CoreTemplates::isCoreTemplate( 'plugin:woocommerce' ) );
		self::assertFalse( CoreTemplates::isCoreTemplate( 'wp_core:auto_update' ), 'Dynamic-body templates were intentionally cut from v2.6.0.' );
		self::assertFalse( CoreTemplates::isCoreTemplate( 'wp_core:unknown' ) );
	}

	public function test_core_templates_get_returns_full_tuple(): void {
		$tpl = CoreTemplates::get( 'wp_core:password_reset' );
		self::assertNotNull( $tpl );
		self::assertArrayHasKey( 'subject', $tpl );
		self::assertArrayHasKey( 'body', $tpl );
		self::assertArrayHasKey( 'tokens', $tpl );
		self::assertArrayHasKey( 'label', $tpl );
		self::assertContains( 'username', $tpl['tokens'] );
		self::assertContains( 'reset_url', $tpl['tokens'] );
	}

	// ------------------------------------------------------------------
	// Detector per-call context
	// ------------------------------------------------------------------

	public function test_detector_setData_consumeData_round_trip(): void {
		Detector::setData( array( 'username' => 'alice', 'reset_url' => 'https://example.test/reset' ) );

		$first = Detector::consumeData();
		self::assertSame( 'alice', $first['username'] );
		self::assertSame( 'https://example.test/reset', $first['reset_url'] );

		// Subsequent consume returns empty (single-shot).
		self::assertSame( array(), Detector::consumeData() );
	}

	// ------------------------------------------------------------------
	// Repository
	// ------------------------------------------------------------------

	public function test_repository_updateTemplateOverrides_persists_all_three_columns(): void {
		$id = SourcesRepository::register( 'wp_core:password_reset', 'Password reset', 'WordPress Core' );

		SourcesRepository::updateTemplateOverrides( $id, '[Acme] Reset', 'Hi {username}, click here.', true );

		$row = SourcesRepository::findById( $id );
		self::assertNotNull( $row );
		self::assertSame( '[Acme] Reset', $row['subject_override'] );
		self::assertSame( 'Hi {username}, click here.', $row['body_override'] );
		self::assertSame( '1', (string) $row['skip_template_wrap'] );
	}

	public function test_repository_clearTemplateOverrides_resets_all_three(): void {
		$id = SourcesRepository::register( 'wp_core:password_reset', 'Password reset', 'WordPress Core' );
		SourcesRepository::updateTemplateOverrides( $id, '[Acme] Reset', 'Hi {username}', true );

		SourcesRepository::clearTemplateOverrides( $id );

		$row = SourcesRepository::findById( $id );
		self::assertSame( '', $row['subject_override'] );
		self::assertSame( '', $row['body_override'] );
		self::assertSame( '0', (string) $row['skip_template_wrap'] );
	}

	// ------------------------------------------------------------------
	// MailInterceptor override pipeline
	// ------------------------------------------------------------------

	public function test_mailInterceptor_applies_subject_and_body_override_with_tokens(): void {
		$id = SourcesRepository::register( 'wp_core:password_reset', 'Password reset', 'WordPress Core' );
		SourcesRepository::updateTemplateOverrides(
			$id,
			'[Acme] Reset for {username}',
			"Hi {username},\n\nReset link: {reset_url}\n\nFrom: {site_title}",
			false
		);

		Detector::setCurrent( 'wp_core:password_reset', 'Password reset', 'WordPress Core' );
		Detector::setData( array(
			'username'  => 'alice',
			'reset_url' => 'https://example.test/reset?key=ABC',
		) );

		wp_mail( 'alice@example.test', 'Original WP subject', 'Original WP body' );

		global $wpdb;
		$row = $wpdb->get_row( "SELECT * FROM `{$this->queueTable()}` ORDER BY id DESC LIMIT 1", ARRAY_A );
		self::assertNotNull( $row );
		self::assertSame( '[Acme] Reset for alice', $row['subject'] );
		self::assertStringContainsString( 'Hi alice,', $row['message'] );
		self::assertStringContainsString( 'https://example.test/reset?key=ABC', $row['message'] );
		self::assertStringContainsString( (string) get_bloginfo( 'name' ), $row['message'] );
	}

	public function test_mailInterceptor_message_original_token_preserves_wp_default(): void {
		$id = SourcesRepository::register( 'wp_core:password_reset', 'Password reset', 'WordPress Core' );
		SourcesRepository::updateTemplateOverrides(
			$id,
			'',
			"Forwarded:\n{message_original}\n— Acme",
			false
		);

		Detector::setCurrent( 'wp_core:password_reset', 'Password reset', 'WordPress Core' );
		wp_mail( 'a@example.test', 'S', 'WP would have written THIS body.' );

		global $wpdb;
		$row = $wpdb->get_row( "SELECT * FROM `{$this->queueTable()}` ORDER BY id DESC LIMIT 1", ARRAY_A );
		self::assertStringContainsString( 'Forwarded:', $row['message'] );
		self::assertStringContainsString( 'WP would have written THIS body.', $row['message'] );
		self::assertStringContainsString( '— Acme', $row['message'] );
	}

	public function test_mailInterceptor_skip_template_wrap_bypasses_engine_envelope(): void {
		$id = SourcesRepository::register( 'wp_core:password_reset', 'Password reset', 'WordPress Core' );
		SourcesRepository::updateTemplateOverrides( $id, '', 'Plain body, no envelope.', true );

		Detector::setCurrent( 'wp_core:password_reset', 'Password reset', 'WordPress Core' );
		wp_mail( 'a@example.test', 'S', 'unused' );

		global $wpdb;
		$row = $wpdb->get_row( "SELECT * FROM `{$this->queueTable()}` ORDER BY id DESC LIMIT 1", ARRAY_A );
		self::assertSame( 'Plain body, no envelope.', $row['message'], 'skip_template_wrap=1 must keep the body raw.' );
	}

	public function test_batch_processor_drain_honors_skip_template_wrap(): void {
		// Regression for v2.6.1 — at drain time the Engine on `wp_mail`
		// filter @100 used to re-wrap rows that MailInterceptor left raw,
		// nullifying the per-source skip_template_wrap flag. Drain now
		// short-circuits Engine via the `wp_tmq_template_skip` filter
		// when the source has skip_template_wrap=1.
		$id = SourcesRepository::register( 'wp_core:password_reset', 'Password reset', 'WordPress Core' );
		SourcesRepository::updateTemplateOverrides( $id, '', 'Stays raw on drain.', true );

		// 1. Skipping helper recognises a skip_template_wrap row.
		self::assertTrue(
			$this->callPrivate( BatchProcessor::class, 'skipsTemplateWrap', array( 'wp_core:password_reset' ) ),
			'skip_template_wrap=1 must be detected by BatchProcessor at drain time.'
		);

		// 2. Same helper returns false for a row without the flag.
		SourcesRepository::updateTemplateOverrides( $id, '', '', false );
		self::assertFalse(
			$this->callPrivate( BatchProcessor::class, 'skipsTemplateWrap', array( 'wp_core:password_reset' ) )
		);

		// 3. Same helper returns false for a non-wp_core source even if
		// the skip flag would somehow be set on its row.
		$plugin_id = SourcesRepository::register( 'plugin:woocommerce', 'WooCommerce', 'Plugins' );
		SourcesRepository::updateTemplateOverrides( $plugin_id, '', '', true );
		self::assertFalse(
			$this->callPrivate( BatchProcessor::class, 'skipsTemplateWrap', array( 'plugin:woocommerce' ) ),
			'Non-wp_core sources do not honor skip_template_wrap (the column does not apply to them).'
		);
	}

	/**
	 * Reflection helper for testing private static methods.
	 *
	 * @param string             $class  Fully-qualified class name.
	 * @param string             $method Method name.
	 * @param array<int,mixed>   $args   Positional arguments.
	 * @return mixed
	 */
	private function callPrivate( string $class, string $method, array $args ) {
		$ref = new \ReflectionMethod( $class, $method );
		$ref->setAccessible( true );
		return $ref->invoke( null, ...$args );
	}

	public function test_mailInterceptor_non_wp_core_source_is_not_overridden(): void {
		// Even if a row exists in sources for a plugin, isCoreTemplate
		// returns false so the override columns are ignored.
		$id = SourcesRepository::register( 'plugin:woocommerce', 'WooCommerce', 'Plugins' );
		SourcesRepository::updateTemplateOverrides( $id, '[ignored]', 'ignored body', false );

		Detector::setCurrent( 'plugin:woocommerce', 'WooCommerce', 'Plugins' );
		wp_mail( 'a@example.test', 'WC subject', 'WC body content' );

		global $wpdb;
		$row = $wpdb->get_row( "SELECT * FROM `{$this->queueTable()}` ORDER BY id DESC LIMIT 1", ARRAY_A );
		self::assertSame( 'WC subject', $row['subject'], 'Plugin-source override must not apply.' );
		self::assertStringContainsString( 'WC body content', $row['message'] );
	}

	// ------------------------------------------------------------------
	// SourcesPage form
	// ------------------------------------------------------------------

	public function test_sourcesPage_save_persists_template_overrides(): void {
		$id = SourcesRepository::register( 'wp_core:password_reset', 'Password reset', 'WordPress Core' );

		$_POST['page']                       = 'wp_tmq_mail_queue-tab-sources';
		$_POST['source_id']                  = (string) $id;
		$_POST['label_override']             = '';
		$_POST['group_override']             = '';
		$_POST['subject_override']           = '[Acme] Custom subject';
		$_POST['body_override']              = 'Custom body for {username}';
		$_POST['skip_template_wrap']         = '1';
		$_POST['wp_tmq_source_edit_save']    = '1';
		$_POST['wp_tmq_source_edit_nonce']   = wp_create_nonce( 'wp_tmq_source_edit_' . $id );

		ob_start();
		SourcesPage::render();
		ob_end_clean();

		$row = SourcesRepository::findById( $id );
		self::assertSame( '[Acme] Custom subject', $row['subject_override'] );
		self::assertSame( 'Custom body for {username}', $row['body_override'] );
		self::assertSame( '1', (string) $row['skip_template_wrap'] );

		unset(
			$_POST['page'],
			$_POST['source_id'],
			$_POST['label_override'],
			$_POST['group_override'],
			$_POST['subject_override'],
			$_POST['body_override'],
			$_POST['skip_template_wrap'],
			$_POST['wp_tmq_source_edit_save'],
			$_POST['wp_tmq_source_edit_nonce']
		);
	}

	public function test_sourcesPage_reset_template_action_clears_overrides(): void {
		$id = SourcesRepository::register( 'wp_core:password_reset', 'Password reset', 'WordPress Core' );
		SourcesRepository::updateTemplateOverrides( $id, '[A]', 'B', true );

		$_GET['page']          = 'wp_tmq_mail_queue-tab-sources';
		$_GET['source-action'] = 'reset_template';
		$_GET['source-id']     = (string) $id;
		$_GET['_wpnonce']      = wp_create_nonce( 'wp_tmq_source_reset_template_' . $id );

		ob_start();
		SourcesPage::render();
		ob_end_clean();

		$row = SourcesRepository::findById( $id );
		self::assertSame( '', $row['subject_override'] );
		self::assertSame( '', $row['body_override'] );
		self::assertSame( '0', (string) $row['skip_template_wrap'] );

		unset( $_GET['page'], $_GET['source-action'], $_GET['source-id'], $_GET['_wpnonce'] );
	}

	// ------------------------------------------------------------------
	// Migration: Sender from Templates → Settings
	// ------------------------------------------------------------------

	public function test_migrator_copies_legacy_sender_to_settings_when_empty(): void {
		// Seed a legacy options row with from_email/from_name + force the
		// settings row to NOT have those keys yet.
		update_option( 'wp_tmq_template_options', array( 'from_email' => 'legacy@example.test', 'from_name' => 'Legacy' ) );
		update_option( 'wp_tmq_settings', array( 'enabled' => '1' ) );

		Migrator::install();

		$settings = get_option( 'wp_tmq_settings' );
		self::assertSame( 'legacy@example.test', $settings['from_email'] );
		self::assertSame( 'Legacy', $settings['from_name'] );

		// Legacy values stripped from the old row.
		$legacy = get_option( 'wp_tmq_template_options' );
		self::assertArrayNotHasKey( 'from_email', $legacy );
		self::assertArrayNotHasKey( 'from_name', $legacy );
	}

	public function test_migrator_preserves_existing_settings_sender(): void {
		// Pre-existing settings: should not be overwritten by legacy values.
		update_option( 'wp_tmq_template_options', array( 'from_email' => 'legacy@example.test' ) );
		update_option( 'wp_tmq_settings', array( 'enabled' => '1', 'from_email' => 'current@example.test' ) );

		Migrator::install();

		$settings = get_option( 'wp_tmq_settings' );
		self::assertSame( 'current@example.test', $settings['from_email'], 'Existing Settings value must win over legacy template-options value.' );
	}

	// ------------------------------------------------------------------
	// WpVersionNotice
	// ------------------------------------------------------------------

	public function test_wp_version_notice_silent_on_first_run(): void {
		delete_option( WpVersionNotice::OPTION_NAME );

		ob_start();
		WpVersionNotice::render();
		$html = (string) ob_get_clean();

		self::assertSame( '', $html, 'First run should silently stamp the version, not render.' );
		self::assertSame( (string) get_bloginfo( 'version' ), get_option( WpVersionNotice::OPTION_NAME ) );
	}

	public function test_wp_version_notice_fires_on_version_change(): void {
		update_option( WpVersionNotice::OPTION_NAME, '1.0.0-old', true );

		ob_start();
		WpVersionNotice::render();
		$html = (string) ob_get_clean();

		self::assertStringContainsString( 'Total Mail Queue', $html );
		self::assertStringContainsString( 'WordPress was updated', $html );
		self::assertStringContainsString( '1.0.0-old', $html );
	}

	private function sourcesTable(): string {
		return Schema::sourcesTable();
	}
}
