<?php

declare(strict_types=1);

namespace TMQ\Tests\Functional;

use TotalMailQueue\Admin\Pages\TemplatesPage;
use TotalMailQueue\Templates\Options;

/**
 * T3 — Templates admin tab: form save, reset, mode notices, toggle-off
 * inline warning, and the rendered structure.
 *
 * @covers \TotalMailQueue\Admin\Pages\TemplatesPage
 */
final class TemplatesPageTest extends FunctionalTestCase {

	protected function setUp(): void {
		parent::setUp();

		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		Options::reset();
	}

	public function test_save_post_persists_all_fields(): void {
		$_POST['wp_tmq_templates_save']  = '1';
		$_POST['wp_tmq_templates_nonce'] = wp_create_nonce( 'wp_tmq_templates_save' );
		$_POST['tmq_template']           = array(
			'enabled'           => '1',
			'header_bg'         => '#112233',
			'header_alignment'  => 'left',
			'body_font_family'  => 'serif',
			'body_font_size'    => '16',
			'footer_text'       => 'Custom footer',
			'footer_powered_by' => '1',
			'from_email'        => 'mailer@example.test',
			'from_name'         => 'Mailer Bot',
		);

		ob_start();
		TemplatesPage::render();
		$html = (string) ob_get_clean();

		self::assertStringContainsString( 'Template settings saved', $html );

		$opts = Options::get();
		self::assertTrue( $opts['enabled'] );
		self::assertSame( '#112233', $opts['header_bg'] );
		self::assertSame( 'left', $opts['header_alignment'] );
		self::assertSame( 'serif', $opts['body_font_family'] );
		self::assertSame( 16, $opts['body_font_size'] );
		self::assertSame( 'Custom footer', $opts['footer_text'] );
		self::assertTrue( $opts['footer_powered_by'] );
		self::assertSame( 'mailer@example.test', $opts['from_email'] );
		self::assertSame( 'Mailer Bot', $opts['from_name'] );

		unset( $_POST['wp_tmq_templates_save'], $_POST['wp_tmq_templates_nonce'], $_POST['tmq_template'] );
	}

	public function test_save_post_with_unchecked_toggle_persists_false(): void {
		// Seed defaults (toggle = on).
		Options::update( array( 'enabled' => true ) );
		self::assertTrue( Options::isEnabled() );

		// User unticks the box → checkbox absent from $_POST.
		$_POST['wp_tmq_templates_save']  = '1';
		$_POST['wp_tmq_templates_nonce'] = wp_create_nonce( 'wp_tmq_templates_save' );
		$_POST['tmq_template']           = array(
			'header_bg' => '#000000',
			// 'enabled' deliberately absent — that's how WP submits unchecked checkboxes
		);

		ob_start();
		TemplatesPage::render();
		ob_end_clean();

		self::assertFalse( Options::isEnabled(), 'Unchecked toggle must persist as false rather than falling back to the default `true`.' );

		unset( $_POST['wp_tmq_templates_save'], $_POST['wp_tmq_templates_nonce'], $_POST['tmq_template'] );
	}

	public function test_save_post_rejects_invalid_nonce(): void {
		$_POST['wp_tmq_templates_save']  = '1';
		$_POST['wp_tmq_templates_nonce'] = 'not-a-real-nonce';
		$_POST['tmq_template']           = array( 'header_bg' => '#aabbcc' );

		// SmtpPage / SourcesPage both `wp_die()` on bad nonce — the test
		// framework catches these as exceptions.
		$this->expectException( \WPDieException::class );

		try {
			ob_start();
			TemplatesPage::render();
		} finally {
			ob_end_clean();
			unset( $_POST['wp_tmq_templates_save'], $_POST['wp_tmq_templates_nonce'], $_POST['tmq_template'] );
		}
	}

	public function test_reset_action_clears_options(): void {
		Options::update( array( 'header_bg' => '#aabbcc', 'from_name' => 'Override' ) );
		self::assertSame( '#aabbcc', Options::get()['header_bg'] );

		$_GET['templates-action'] = 'reset';
		$_GET['_wpnonce']         = wp_create_nonce( 'wp_tmq_templates_reset' );

		ob_start();
		TemplatesPage::render();
		$html = (string) ob_get_clean();

		self::assertStringContainsString( 'reset to defaults', $html );

		$defaults = Options::defaults();
		self::assertSame( $defaults['header_bg'], Options::get()['header_bg'] );
		self::assertSame( $defaults['from_name'], Options::get()['from_name'] );

		unset( $_GET['templates-action'], $_GET['_wpnonce'] );
	}

	public function test_block_mode_renders_warning_notice(): void {
		$this->setPluginOptions( array( 'enabled' => '2' ) );

		ob_start();
		TemplatesPage::render();
		$html = (string) ob_get_clean();

		self::assertStringContainsString( 'Block mode is active', $html );
	}

	public function test_disabled_mode_renders_warning_notice(): void {
		$this->setPluginOptions( array( 'enabled' => '0' ) );

		ob_start();
		TemplatesPage::render();
		$html = (string) ob_get_clean();

		self::assertStringContainsString( 'currently disabled', $html );
	}

	public function test_queue_mode_does_not_render_mode_notice(): void {
		$this->setPluginOptions( array( 'enabled' => '1' ) );

		ob_start();
		TemplatesPage::render();
		$html = (string) ob_get_clean();

		self::assertStringNotContainsString( 'Block mode is active', $html );
		self::assertStringNotContainsString( 'currently disabled', $html );
	}

	public function test_toggle_off_renders_inline_warning(): void {
		Options::update( array( 'enabled' => false ) );

		ob_start();
		TemplatesPage::render();
		$html = (string) ob_get_clean();

		self::assertStringContainsString( 'template engine is currently off', $html );
	}

	public function test_render_outputs_form_with_every_section(): void {
		$this->setPluginOptions( array( 'enabled' => '1' ) );

		ob_start();
		TemplatesPage::render();
		$html = (string) ob_get_clean();

		// Every section heading present.
		self::assertStringContainsString( 'HTML Template Engine', $html );
		self::assertStringContainsString( '>Header<', $html );
		self::assertStringContainsString( '>Body<', $html );
		self::assertStringContainsString( '>Footer<', $html );
		self::assertStringContainsString( '>Wrapper<', $html );
		self::assertStringContainsString( '>Sender<', $html );

		// Save / reset / test buttons present.
		self::assertStringContainsString( 'Save Changes', $html );
		self::assertStringContainsString( 'Reset to Defaults', $html );
		self::assertStringContainsString( 'Send test email', $html );
	}
}
