<?php
/**
 * Live preview of the HTML template on the Templates tab.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Templates;

use TotalMailQueue\Admin\Access;

/**
 * Renders a sample email with template options that are not saved yet
 * (`wp_ajax_tmq_template_preview`), and with the saved ones for the first
 * paint of the tab.
 *
 * The HTML comes from {@see Engine::render()}, the same code that wraps
 * real mail, so the preview cannot drift from what recipients receive.
 * Nothing is stored and nothing is sent.
 */
final class Preview {

	/**
	 * The AJAX action.
	 */
	public const ACTION = 'tmq_template_preview';

	/**
	 * Nonce action the page prints and the endpoint checks.
	 */
	public const NONCE = 'tmq_template_preview';

	/**
	 * Toggles a form only posts while they are on.
	 */
	private const BOOL_FIELDS = array( 'enabled', 'footer_powered_by' );

	/**
	 * Hook the endpoint.
	 */
	public static function register(): void {
		add_action( 'wp_ajax_' . self::ACTION, array( self::class, 'handle' ) );
	}

	/**
	 * Render the posted options. Replies with JSON and ends the request.
	 */
	public static function handle(): void {
		if ( ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session expired. Reload the page and try again.', 'total-mail-queue' ) ), 403 );
		}
		if ( ! Access::canManage() ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to change these settings.', 'total-mail-queue' ) ), 403 );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- every field goes through Options::sanitize() in optionsFrom().
		$raw = isset( $_POST['tmq_template'] ) && is_array( $_POST['tmq_template'] ) ? wp_unslash( $_POST['tmq_template'] ) : array();

		wp_send_json_success( array( 'html' => self::html( self::optionsFrom( $raw ) ) ) );
	}

	/**
	 * Clean options from a posted form. A toggle that is off is absent from
	 * the post, so it is read as off rather than falling back to its default.
	 *
	 * @param array<string,mixed> $raw The form's `tmq_template` fields.
	 * @return array<string,mixed>
	 */
	public static function optionsFrom( array $raw ): array {
		foreach ( self::BOOL_FIELDS as $field ) {
			if ( ! array_key_exists( $field, $raw ) ) {
				$raw[ $field ] = false;
			}
		}
		return Options::sanitize( $raw );
	}

	/**
	 * The sample email wrapped with the given options.
	 *
	 * @param array<string,mixed> $options Template options.
	 */
	public static function html( array $options ): string {
		$user = wp_get_current_user();
		$to   = $user instanceof \WP_User && '' !== $user->user_email ? $user->user_email : (string) get_option( 'admin_email' );

		return Engine::render(
			self::sampleMessage(),
			array(
				'to'      => $to,
				'subject' => __( 'Template preview', 'total-mail-queue' ),
			),
			$options
		);
	}

	/**
	 * Plain-text body, so the preview also shows how the engine turns
	 * paragraphs and links into HTML.
	 */
	private static function sampleMessage(): string {
		return implode(
			"\n\n",
			array(
				__( 'Hello,', 'total-mail-queue' ),
				__( 'This is how emails from this site look with the settings on this screen. The header, the footer and the colours update as you change them; save to apply them to real mail.', 'total-mail-queue' ),
				/* translators: %s: the site's home URL */
				sprintf( __( 'Links take the link colour, like this one: %s', 'total-mail-queue' ), '<a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html( home_url( '/' ) ) . '</a>' ),
				__( 'Thank you.', 'total-mail-queue' ),
			)
		);
	}
}
