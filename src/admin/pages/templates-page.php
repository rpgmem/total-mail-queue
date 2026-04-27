<?php
/**
 * Templates admin tab — visual customization for the HTML wrapper engine.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Admin\Pages;

use TotalMailQueue\Settings\Options as PluginOptions;
use TotalMailQueue\Templates\Options;
use TotalMailQueue\Templates\TestEmailSender;

/**
 * Renderer for the "Templates" tab introduced in v2.5.0.
 *
 * Surfaces every {@see \TotalMailQueue\Templates\Options} key in a single form
 * with WP-native color pickers + media uploader, plus three actions:
 * Save / Reset / Send test email. The page also surfaces the plugin operating
 * mode at the top so the admin understands when the engine is bypassed
 * (block mode) or when wrapping has no effect because nothing leaves anyway
 * (disabled mode).
 *
 * Form submissions are handled inline (no redirect) so notices fall through
 * to the next render. The same pattern as
 * {@see \TotalMailQueue\Admin\Pages\SmtpPage}.
 */
final class TemplatesPage {

	/**
	 * Nonce action used by the main settings form.
	 */
	private const NONCE_SAVE = 'wp_tmq_templates_save';

	/**
	 * Nonce action used by the reset link.
	 */
	private const NONCE_RESET = 'wp_tmq_templates_reset';

	/**
	 * Render the Templates tab. Invoked by
	 * {@see \TotalMailQueue\Admin\PluginPage::render()} once the page slug
	 * matches `wp_tmq_mail_queue-tab-templates` and the user has
	 * `manage_options`.
	 */
	public static function render(): void {
		self::handlePost();
		self::handleReset();

		$options = Options::get();
		$mode    = (string) PluginOptions::get()['enabled'];

		self::renderModeNotice( $mode );

		echo '<form method="post" action="admin.php?page=' . esc_attr( \TotalMailQueue\Admin\PluginPage::TAB_TEMPLATES ) . '" class="tmq-templates-form">';
		wp_nonce_field( self::NONCE_SAVE, 'wp_tmq_templates_nonce' );

		self::renderToggleSection( $options );
		self::renderHeaderSection( $options );
		self::renderBodySection( $options );
		self::renderFooterSection( $options );
		self::renderWrapperSection( $options );
		self::renderSenderSection( $options );

		echo '<p class="submit">';
		echo '<button type="submit" name="wp_tmq_templates_save" class="button button-primary">' . esc_html__( 'Save Changes', 'total-mail-queue' ) . '</button> ';

		$reset_url = wp_nonce_url(
			admin_url( 'admin.php?page=' . \TotalMailQueue\Admin\PluginPage::TAB_TEMPLATES . '&templates-action=reset' ),
			self::NONCE_RESET
		);
		echo '<a href="' . esc_url( $reset_url ) . '" class="button" onclick="return confirm(\'' . esc_js( __( 'Reset all template settings to defaults? This cannot be undone.', 'total-mail-queue' ) ) . '\');">' . esc_html__( 'Reset to Defaults', 'total-mail-queue' ) . '</a>';
		echo '</p>';
		echo '</form>';

		self::renderTestEmailWidget();
	}

	/**
	 * Save POST handler.
	 */
	private static function handlePost(): void {
		if ( ! isset( $_POST['wp_tmq_templates_save'] ) ) {
			return;
		}
		if ( ! isset( $_POST['wp_tmq_templates_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['wp_tmq_templates_nonce'] ) ), self::NONCE_SAVE ) ) {
			wp_die( esc_html__( 'Security check failed!', 'total-mail-queue' ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- handed to Options::sanitize() below
		$raw = isset( $_POST['tmq_template'] ) && is_array( $_POST['tmq_template'] ) ? wp_unslash( $_POST['tmq_template'] ) : array();

		// Checkboxes only appear in $_POST when ticked — explicitly seed false
		// for the toggles so the sanitizer doesn't fall back to defaults
		// (which would re-enable them after the user un-ticks).
		foreach ( array( 'enabled', 'footer_powered_by' ) as $bool_field ) {
			if ( ! array_key_exists( $bool_field, $raw ) ) {
				$raw[ $bool_field ] = false;
			}
		}

		Options::update( $raw );

		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Template settings saved.', 'total-mail-queue' ) . '</p></div>';
	}

	/**
	 * Reset link handler — wipes the option row so reads return defaults.
	 */
	private static function handleReset(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified just below.
		$action = isset( $_GET['templates-action'] ) ? sanitize_key( wp_unslash( $_GET['templates-action'] ) ) : '';
		if ( 'reset' !== $action ) {
			return;
		}
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), self::NONCE_RESET ) ) {
			wp_die( esc_html__( 'Security check failed!', 'total-mail-queue' ) );
		}

		Options::reset();

		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Template settings reset to defaults.', 'total-mail-queue' ) . '</p></div>';
	}

	/**
	 * Block / disabled / queue mode notice. Mirrors the pattern in
	 * {@see SourcesPage::renderModeNotice()}.
	 *
	 * @param string $mode `enabled` value of the main plugin settings.
	 */
	private static function renderModeNotice( string $mode ): void {
		if ( '1' === $mode ) {
			return;
		}
		$settings_link_open  = '<a href="' . esc_url( admin_url( 'admin.php?page=wp_tmq_mail_queue' ) ) . '">';
		$settings_link_close = '</a>';

		if ( '2' === $mode ) {
			echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Block mode is active.', 'total-mail-queue' ) . '</strong> ' . wp_kses_post(
				sprintf(
					/* translators: %1$s: opening Settings link tag, %2$s: closing tag */
					__( 'No mail leaves the server right now. You can still edit the template, but changes have no visible effect until the Operation Mode is set to Queue in %1$sSettings%2$s.', 'total-mail-queue' ),
					$settings_link_open,
					$settings_link_close
				)
			) . '</p></div>';
			return;
		}

		echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'The plugin is currently disabled.', 'total-mail-queue' ) . '</strong> ' . wp_kses_post(
			sprintf(
				/* translators: %1$s: opening Settings link tag, %2$s: closing tag */
				__( 'Mail goes straight to wp_mail() without queueing. The template engine still wraps outgoing messages inline whenever the master toggle below is on. Switch to Queue mode in %1$sSettings%2$s for the full pipeline.', 'total-mail-queue' ),
				$settings_link_open,
				$settings_link_close
			)
		) . '</p></div>';
	}

	/**
	 * Master toggle.
	 *
	 * @param array<string,mixed> $options Resolved options.
	 */
	private static function renderToggleSection( array $options ): void {
		$enabled = ! empty( $options['enabled'] );
		echo '<h2>' . esc_html__( 'HTML Template Engine', 'total-mail-queue' ) . '</h2>';

		if ( ! $enabled ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'The template engine is currently off — outgoing emails are sent as-is. Tick the box below to enable it.', 'total-mail-queue' ) . '</p></div>';
		}

		echo '<table class="form-table"><tbody>';
		echo '<tr><th scope="row">' . esc_html__( 'Enable HTML wrapping', 'total-mail-queue' ) . '</th><td>';
		echo '<label><input type="checkbox" name="tmq_template[enabled]" value="1" ' . checked( $enabled, true, false ) . ' /> ';
		echo esc_html__( 'Wrap every outgoing email in a styled HTML envelope.', 'total-mail-queue' );
		echo '</label></td></tr>';
		echo '</tbody></table>';
	}

	/**
	 * Header settings.
	 *
	 * @param array<string,mixed> $options Resolved options.
	 */
	private static function renderHeaderSection( array $options ): void {
		echo '<h2>' . esc_html__( 'Header', 'total-mail-queue' ) . '</h2>';
		echo '<table class="form-table"><tbody>';

		self::renderColorRow( __( 'Background color', 'total-mail-queue' ), 'header_bg', (string) $options['header_bg'] );
		self::renderColorRow( __( 'Text color', 'total-mail-queue' ), 'header_text_color', (string) $options['header_text_color'] );
		self::renderEnumRow(
			__( 'Alignment', 'total-mail-queue' ),
			'header_alignment',
			(string) $options['header_alignment'],
			array(
				'left'   => __( 'Left', 'total-mail-queue' ),
				'center' => __( 'Center', 'total-mail-queue' ),
				'right'  => __( 'Right', 'total-mail-queue' ),
			)
		);
		self::renderIntRow( __( 'Padding (px)', 'total-mail-queue' ), 'header_padding', (int) $options['header_padding'] );
		self::renderUrlRow( __( 'Logo image URL', 'total-mail-queue' ), 'header_logo_url', (string) $options['header_logo_url'], __( 'Leave empty to fall back to the site title.', 'total-mail-queue' ) );
		self::renderIntRow( __( 'Logo max width (px)', 'total-mail-queue' ), 'header_logo_max_width', (int) $options['header_logo_max_width'] );

		echo '</tbody></table>';
	}

	/**
	 * Body settings.
	 *
	 * @param array<string,mixed> $options Resolved options.
	 */
	private static function renderBodySection( array $options ): void {
		echo '<h2>' . esc_html__( 'Body', 'total-mail-queue' ) . '</h2>';
		echo '<table class="form-table"><tbody>';

		self::renderColorRow( __( 'Background color', 'total-mail-queue' ), 'body_bg', (string) $options['body_bg'] );
		self::renderColorRow( __( 'Text color', 'total-mail-queue' ), 'body_text_color', (string) $options['body_text_color'] );
		self::renderColorRow( __( 'Link color', 'total-mail-queue' ), 'body_link_color', (string) $options['body_link_color'] );
		self::renderEnumRow(
			__( 'Font family', 'total-mail-queue' ),
			'body_font_family',
			(string) $options['body_font_family'],
			array(
				'system'  => __( 'System (default)', 'total-mail-queue' ),
				'serif'   => __( 'Serif', 'total-mail-queue' ),
				'mono'    => __( 'Monospace', 'total-mail-queue' ),
				'arial'   => __( 'Arial', 'total-mail-queue' ),
				'georgia' => __( 'Georgia', 'total-mail-queue' ),
			)
		);
		self::renderIntRow( __( 'Font size (px)', 'total-mail-queue' ), 'body_font_size', (int) $options['body_font_size'] );
		self::renderIntRow( __( 'Padding (px)', 'total-mail-queue' ), 'body_padding', (int) $options['body_padding'] );
		self::renderIntRow( __( 'Max width (px)', 'total-mail-queue' ), 'body_max_width', (int) $options['body_max_width'] );

		echo '</tbody></table>';
	}

	/**
	 * Footer settings.
	 *
	 * @param array<string,mixed> $options Resolved options.
	 */
	private static function renderFooterSection( array $options ): void {
		echo '<h2>' . esc_html__( 'Footer', 'total-mail-queue' ) . '</h2>';
		echo '<table class="form-table"><tbody>';

		self::renderColorRow( __( 'Background color', 'total-mail-queue' ), 'footer_bg', (string) $options['footer_bg'] );
		self::renderColorRow( __( 'Text color', 'total-mail-queue' ), 'footer_text_color', (string) $options['footer_text_color'] );

		echo '<tr><th scope="row"><label for="tmq_footer_text">' . esc_html__( 'Footer text', 'total-mail-queue' ) . '</label></th><td>';
		echo '<textarea id="tmq_footer_text" name="tmq_template[footer_text]" rows="3" class="large-text">' . esc_textarea( (string) $options['footer_text'] ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'Tokens supported: {site_title}, {site_url}, {home_url}, {admin_email}, {recipient}, {date}, {year}.', 'total-mail-queue' ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Powered-by line', 'total-mail-queue' ) . '</th><td>';
		echo '<label><input type="checkbox" name="tmq_template[footer_powered_by]" value="1" ' . checked( ! empty( $options['footer_powered_by'] ), true, false ) . ' /> ';
		echo esc_html__( 'Show "Sent via Total Mail Queue" beneath the footer text.', 'total-mail-queue' );
		echo '</label></td></tr>';

		echo '</tbody></table>';
	}

	/**
	 * Wrapper / outer styling.
	 *
	 * @param array<string,mixed> $options Resolved options.
	 */
	private static function renderWrapperSection( array $options ): void {
		echo '<h2>' . esc_html__( 'Wrapper', 'total-mail-queue' ) . '</h2>';
		echo '<table class="form-table"><tbody>';

		self::renderColorRow( __( 'Outer background color', 'total-mail-queue' ), 'wrapper_bg', (string) $options['wrapper_bg'] );
		self::renderIntRow( __( 'Border radius (px)', 'total-mail-queue' ), 'wrapper_border_radius', (int) $options['wrapper_border_radius'] );
		self::renderIntRow( __( 'Outer padding (px)', 'total-mail-queue' ), 'wrapper_padding', (int) $options['wrapper_padding'] );

		echo '</tbody></table>';
	}

	/**
	 * Sender override settings.
	 *
	 * @param array<string,mixed> $options Resolved options.
	 */
	private static function renderSenderSection( array $options ): void {
		echo '<h2>' . esc_html__( 'Sender', 'total-mail-queue' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Optional — leave blank to keep whatever upstream filters set.', 'total-mail-queue' ) . '</p>';
		echo '<table class="form-table"><tbody>';

		echo '<tr><th scope="row"><label for="tmq_from_email">' . esc_html__( 'From email', 'total-mail-queue' ) . '</label></th><td>';
		echo '<input type="email" id="tmq_from_email" name="tmq_template[from_email]" value="' . esc_attr( (string) $options['from_email'] ) . '" class="regular-text" />';
		echo '</td></tr>';

		echo '<tr><th scope="row"><label for="tmq_from_name">' . esc_html__( 'From name', 'total-mail-queue' ) . '</label></th><td>';
		echo '<input type="text" id="tmq_from_name" name="tmq_template[from_name]" value="' . esc_attr( (string) $options['from_name'] ) . '" class="regular-text" />';
		echo '</td></tr>';

		echo '</tbody></table>';
	}

	/**
	 * "Send test email" widget — separate form so it doesn't submit with the
	 * main settings form.
	 */
	private static function renderTestEmailWidget(): void {
		echo '<hr />';
		echo '<h2>' . esc_html__( 'Send a test email', 'total-mail-queue' ) . '</h2>';
		echo '<p>' . esc_html__( 'Sends a sample message to the address below using the current template settings (must be saved first).', 'total-mail-queue' ) . '</p>';

		$current_user = wp_get_current_user();
		$default_to   = $current_user instanceof \WP_User ? (string) $current_user->user_email : (string) get_option( 'admin_email' );

		echo '<div class="tmq-templates-test-row">';
		echo '<input type="email" id="tmq_test_email_to" value="' . esc_attr( $default_to ) . '" class="regular-text" /> ';
		echo '<button type="button" id="tmq_send_test_email" class="button" data-nonce="' . esc_attr( wp_create_nonce( TestEmailSender::NONCE ) ) . '" data-action="' . esc_attr( TestEmailSender::ACTION ) . '">';
		echo esc_html__( 'Send test email', 'total-mail-queue' );
		echo '</button>';
		echo '<span id="tmq_test_email_result" class="tmq-templates-test-result" aria-live="polite"></span>';
		echo '</div>';
	}

	/**
	 * Single-row helper for color picker fields.
	 *
	 * @param string $label  Translated row label.
	 * @param string $name   Field name (also used for the id).
	 * @param string $value  Current value.
	 */
	private static function renderColorRow( string $label, string $name, string $value ): void {
		$id = 'tmq_' . $name;
		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th><td>';
		echo '<input type="text" class="tmq-color-picker" id="' . esc_attr( $id ) . '" name="tmq_template[' . esc_attr( $name ) . ']" value="' . esc_attr( $value ) . '" data-default-color="' . esc_attr( $value ) . '" />';
		echo '</td></tr>';
	}

	/**
	 * Single-row helper for integer (number) fields.
	 *
	 * @param string $label Translated row label.
	 * @param string $name  Field name.
	 * @param int    $value Current value.
	 */
	private static function renderIntRow( string $label, string $name, int $value ): void {
		$id = 'tmq_' . $name;
		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th><td>';
		echo '<input type="number" min="0" id="' . esc_attr( $id ) . '" name="tmq_template[' . esc_attr( $name ) . ']" value="' . esc_attr( (string) $value ) . '" class="small-text" />';
		echo '</td></tr>';
	}

	/**
	 * Single-row helper for URL fields.
	 *
	 * @param string $label   Translated row label.
	 * @param string $name    Field name.
	 * @param string $value   Current value.
	 * @param string $hint    Optional helper text below the field.
	 */
	private static function renderUrlRow( string $label, string $name, string $value, string $hint = '' ): void {
		$id = 'tmq_' . $name;
		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th><td>';
		echo '<input type="url" id="' . esc_attr( $id ) . '" name="tmq_template[' . esc_attr( $name ) . ']" value="' . esc_attr( $value ) . '" class="regular-text tmq-media-url" /> ';
		echo '<button type="button" class="button tmq-media-pick" data-target="' . esc_attr( $id ) . '">' . esc_html__( 'Choose image…', 'total-mail-queue' ) . '</button>';
		if ( '' !== $hint ) {
			echo '<p class="description">' . esc_html( $hint ) . '</p>';
		}
		echo '</td></tr>';
	}

	/**
	 * Single-row helper for whitelist (dropdown) fields.
	 *
	 * @param string               $label   Translated row label.
	 * @param string               $name    Field name.
	 * @param string               $value   Current value.
	 * @param array<string,string> $choices Map of value → label.
	 */
	private static function renderEnumRow( string $label, string $name, string $value, array $choices ): void {
		$id = 'tmq_' . $name;
		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th><td>';
		echo '<select id="' . esc_attr( $id ) . '" name="tmq_template[' . esc_attr( $name ) . ']">';
		foreach ( $choices as $val => $lbl ) {
			echo '<option value="' . esc_attr( $val ) . '" ' . selected( $value, $val, false ) . '>' . esc_html( $lbl ) . '</option>';
		}
		echo '</select>';
		echo '</td></tr>';
	}
}
