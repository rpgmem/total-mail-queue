<?php
/**
 * Template engine: HTML-wraps outgoing mail at `wp_mail` filter time.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Templates;

use TotalMailQueue\Settings\Options as MainOptions;

/**
 * Wraps the body of every outgoing email in a styled HTML envelope and
 * forces the `Content-Type: text/html` header, so plain-text messages
 * sent through `wp_mail()` arrive with the plugin-defined visual identity.
 *
 * Hooks into `wp_mail` at priority 100 — late enough that other plugins
 * have already done their content modifications, then the wrapping is the
 * last transformation before PHPMailer takes over.
 *
 * Coexistence with `MailInterceptor`:
 * - In queue mode the interceptor short-circuits via `pre_wp_mail` (priority
 *   99999), so the `wp_mail` filter never fires and the body is queued raw.
 *   On cron drain the interceptor stays out of the way (`wp_doing_cron()`),
 *   `wp_mail` fires normally, and this engine wraps the body just before
 *   it leaves the server.
 * - In disabled mode (`enabled=0`) `pre_wp_mail` does not short-circuit,
 *   `wp_mail` fires immediately, and this engine wraps inline.
 * - In block mode (`enabled=2`) the engine intentionally does not register:
 *   no mail leaves anyway, so wrapping is wasted work.
 */
final class Engine {

	/**
	 * Option key under which the templates feature settings live.
	 */
	public const OPTION_NAME = 'wp_tmq_template_options';

	/**
	 * Filter priority for the `wp_mail` hook. Run late so other plugins
	 * have already modified the body (the wrapper sees the final content).
	 */
	public const FILTER_PRIORITY = 100;

	/**
	 * Register the engine's filters when the templates feature is on
	 * and the plugin is not in block mode.
	 *
	 * The `wp_mail` filter registration is gated on whether the
	 * {@see \TotalMailQueue\Queue\MailInterceptor} is going to catch the call
	 * itself: if it is (queue/block mode, non-cron context), the interceptor
	 * dispatches to {@see Engine::apply()} directly so we keep the
	 * "capture → process → queue" ordering and avoid double-wrapping. In
	 * disabled mode, or during cron drains where the interceptor stays out
	 * of the way, the engine binds to `wp_mail` to wrap inline.
	 */
	public static function register(): void {
		if ( ! self::isEnabled() ) {
			return;
		}
		if ( self::isBlockMode() ) {
			return;
		}

		if ( ! self::interceptorWillDispatch() ) {
			add_filter( 'wp_mail', array( self::class, 'apply' ), self::FILTER_PRIORITY, 1 );
		}
		add_filter( 'wp_mail_content_type', array( self::class, 'forceHtmlContentType' ), self::FILTER_PRIORITY, 1 );
		add_filter( 'wp_mail_from', array( self::class, 'overrideFromEmail' ), self::FILTER_PRIORITY, 1 );
		add_filter( 'wp_mail_from_name', array( self::class, 'overrideFromName' ), self::FILTER_PRIORITY, 1 );
	}

	/**
	 * Whether the queue interceptor will catch outgoing mail in the current
	 * request — when it does, we leave the `wp_mail` filter alone and let
	 * {@see \TotalMailQueue\Queue\MailInterceptor::handle()} call us directly
	 * just before the queue insert.
	 */
	private static function interceptorWillDispatch(): bool {
		if ( wp_doing_cron() ) {
			return false;
		}
		$tmq = MainOptions::get();
		return isset( $tmq['enabled'] ) && in_array( (string) $tmq['enabled'], array( '1', '2' ), true );
	}

	/**
	 * `wp_mail` filter callback. Wraps the message body in the default
	 * HTML envelope unless the body is already a full HTML document or a
	 * third-party filter short-circuits via `wp_tmq_template_skip`.
	 *
	 * @param array<string,mixed> $args wp_mail() arguments.
	 * @return array<string,mixed>
	 */
	public static function apply( $args ): array {
		if ( ! is_array( $args ) ) {
			return array();
		}

		// Self-gate so direct callers (MailInterceptor::handle()) get the
		// same toggle / block-mode short-circuit as the wp_mail filter path.
		if ( ! self::isEnabled() || self::isBlockMode() ) {
			return $args;
		}

		if ( empty( $args['message'] ) || ! is_string( $args['message'] ) ) {
			return $args;
		}

		/**
		 * Allow third-party code to skip the template wrapping for a specific
		 * email — useful for builders (Elementor, MemberPress, etc.) that
		 * already produce a complete HTML document.
		 *
		 * @param bool                $skip Whether to skip wrapping.
		 * @param array<string,mixed> $args wp_mail() arguments.
		 */
		if ( apply_filters( 'wp_tmq_template_skip', false, $args ) ) {
			return $args;
		}

		// Already a full HTML document — never double-wrap.
		if ( self::isFullHtml( $args['message'] ) ) {
			return $args;
		}

		do_action( 'wp_tmq_template_before_apply', $args );

		$body = self::preprocessBody( $args['message'] );
		$html = self::renderWrapper( $body, $args );
		$html = self::replacePlaceholders( $html, $args );

		/**
		 * Final pass over the rendered HTML before it is handed back to
		 * `wp_mail()`. Use to inject custom markup, tracking pixels, etc.
		 *
		 * @param string              $html Rendered HTML.
		 * @param array<string,mixed> $args wp_mail() arguments.
		 */
		$args['message'] = (string) apply_filters( 'wp_tmq_template_html', $html, $args );

		do_action( 'wp_tmq_template_after_apply', $args );

		return $args;
	}

	/**
	 * `wp_mail_content_type` filter callback. Force `text/html` so the
	 * wrapped envelope is interpreted as HTML by mail clients.
	 *
	 * @param string $type Current content type (unused — always overridden).
	 * @return string
	 */
	public static function forceHtmlContentType( $type ): string {
		unset( $type );
		return 'text/html';
	}

	/**
	 * `wp_mail_from` filter callback. Returns the user-defined sender
	 * email if configured; otherwise leaves the upstream value untouched.
	 *
	 * @param string $email Current from-address.
	 * @return string
	 */
	public static function overrideFromEmail( $email ): string {
		$options = self::options();
		if ( empty( $options['from_email'] ) ) {
			return (string) $email;
		}
		return (string) $options['from_email'];
	}

	/**
	 * `wp_mail_from_name` filter callback. Returns the user-defined sender
	 * name if configured; otherwise leaves the upstream value untouched.
	 *
	 * @param string $name Current from-name.
	 * @return string
	 */
	public static function overrideFromName( $name ): string {
		$options = self::options();
		if ( empty( $options['from_name'] ) ) {
			return (string) $name;
		}
		return (string) $options['from_name'];
	}

	/**
	 * WordPress' password reset email arrives with the link wrapped in
	 * `<...>` brackets — which most clients render literally. Strip them
	 * and convert the bare URL into a clickable anchor.
	 *
	 * Exposed as a static helper rather than a registered filter — callers
	 * that know the body is a reset email pre-process it before passing
	 * it through `wp_mail()`.
	 *
	 * @param string $message Raw plain-text body.
	 * @return string
	 */
	public static function cleanResetPasswordLink( string $message ): string {
		return make_clickable( (string) preg_replace( '@<(http[^> ]+)>@', '$1', $message ) );
	}

	/**
	 * Whether the templates feature is currently enabled.
	 */
	public static function isEnabled(): bool {
		$options = self::options();
		// Default to enabled when no row exists yet.
		if ( ! array_key_exists( 'enabled', $options ) ) {
			return true;
		}
		$value = $options['enabled'];
		return ( true === $value || '1' === $value || 1 === $value );
	}

	/**
	 * Read template options merged with built-in defaults. Cheap on hot
	 * paths — `get_option()` is cached by core.
	 *
	 * @return array<string,mixed>
	 */
	public static function options(): array {
		$stored   = get_option( self::OPTION_NAME, array() );
		$stored   = is_array( $stored ) ? $stored : array();
		$defaults = self::defaults();

		/**
		 * Filter the resolved template options after the defaults have been
		 * merged in. Use to override values without changing the row in the
		 * options table.
		 *
		 * @param array<string,mixed> $options Resolved options.
		 */
		return (array) apply_filters( 'wp_tmq_template_options', array_merge( $defaults, $stored ) );
	}

	/**
	 * Built-in defaults for every option key. T2 will surface these in the
	 * admin UI; T1 keeps them inline so the engine produces a sensible
	 * envelope out of the box.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		/**
		 * Filter the engine's hardcoded defaults. Plugins can override
		 * baseline styling without touching the stored row.
		 *
		 * @param array<string,mixed> $defaults Built-in defaults.
		 */
		return (array) apply_filters(
			'wp_tmq_template_default_options',
			array(
				'enabled'               => true,
				'header_bg'             => '#2c3e50',
				'header_text_color'     => '#ffffff',
				'header_alignment'      => 'center',
				'header_padding'        => 24,
				'header_logo_url'       => '',
				'header_logo_max_width' => 180,
				'body_bg'               => '#ffffff',
				'body_text_color'       => '#333333',
				'body_link_color'       => '#2271b1',
				'body_font_family'      => 'system',
				'body_font_size'        => 14,
				'body_padding'          => 24,
				'body_max_width'        => 600,
				'footer_bg'             => '#f5f5f5',
				'footer_text_color'     => '#666666',
				'footer_text'           => 'Sent by {site_title}',
				'footer_powered_by'     => true,
				'wrapper_bg'            => '#f0f0f1',
				'wrapper_border_radius' => 6,
				'wrapper_padding'       => 32,
				'from_email'            => '',
				'from_name'             => '',
			)
		);
	}

	/**
	 * Detect whether the message is already a full HTML document. We check
	 * for either `<!doctype` or `<html` near the top — covers the cases
	 * Elementor / MemberPress / custom email builders produce.
	 *
	 * @param string $message Body to inspect.
	 * @return bool
	 */
	private static function isFullHtml( string $message ): bool {
		// Inspect only the first ~512 bytes — DOCTYPE / <html must appear up top.
		$prefix = strtolower( substr( $message, 0, 512 ) );
		return ( false !== strpos( $prefix, '<!doctype' ) ) || ( false !== strpos( $prefix, '<html' ) );
	}

	/**
	 * Plain-text → display-ready HTML conversion. Mirrors the chain a
	 * `text/html` email needs when the source body was plain text.
	 *
	 * @param string $body Raw message body.
	 * @return string
	 */
	private static function preprocessBody( string $body ): string {
		/**
		 * Filter the email body before it is wrapped — the standard
		 * `wpautop` / `wptexturize` / `convert_chars` pipeline runs through
		 * this filter so plugins can extend or replace it.
		 *
		 * @param string $body Raw body.
		 */
		$body = (string) apply_filters( 'wp_tmq_template_content', $body );

		// If the body already has HTML markup, skip auto-paragraphing — it
		// would double-wrap content authored as HTML.
		if ( ! self::looksLikeHtml( $body ) ) {
			$body = wptexturize( $body );
			$body = convert_chars( $body );
			$body = wpautop( $body );
		}
		return $body;
	}

	/**
	 * Heuristic: does the body already contain block-level HTML markup?
	 *
	 * @param string $body Body to inspect.
	 * @return bool
	 */
	private static function looksLikeHtml( string $body ): bool {
		return (bool) preg_match( '/<(p|div|table|h[1-6]|br|ul|ol)\b/i', $body );
	}

	/**
	 * Capture-and-return the default HTML wrapper, with `$body` and
	 * `$options` exposed to the template.
	 *
	 * @param string              $body Pre-processed body HTML.
	 * @param array<string,mixed> $args wp_mail() arguments (so the template
	 *                                  can access `$args['subject']` etc).
	 * @return string
	 */
	private static function renderWrapper( string $body, array $args ): string {
		$options = self::options();
		$subject = isset( $args['subject'] ) ? (string) $args['subject'] : '';

		ob_start();
		include __DIR__ . '/default-html.php';
		return (string) ob_get_clean();
	}

	/**
	 * Substitute the global `{token}` placeholders. T4 will introduce a
	 * proper registry; T1 ships with the nine globals only.
	 *
	 * @param string              $html Rendered HTML.
	 * @param array<string,mixed> $args wp_mail() arguments.
	 * @return string
	 */
	private static function replacePlaceholders( string $html, array $args ): string {
		$tokens = self::globalTokens( $args );

		/**
		 * Filter the token map used for placeholder substitution. Add
		 * source-specific tokens here (T4 will register a default
		 * WooCommerce handler).
		 *
		 * @param array<string,string> $tokens   Token name (without braces) → value.
		 * @param array<string,mixed>  $args     wp_mail() arguments.
		 */
		$tokens = (array) apply_filters( 'wp_tmq_template_tokens', $tokens, $args );

		$search  = array();
		$replace = array();
		foreach ( $tokens as $name => $value ) {
			$search[]  = '{' . $name . '}';
			$replace[] = (string) $value;
		}
		return str_replace( $search, $replace, $html );
	}

	/**
	 * Built-in token map — the same nine values are available in any
	 * email regardless of source.
	 *
	 * @param array<string,mixed> $args wp_mail() arguments.
	 * @return array<string,string>
	 */
	private static function globalTokens( array $args ): array {
		$to        = isset( $args['to'] ) ? $args['to'] : '';
		$recipient = is_array( $to ) ? (string) reset( $to ) : (string) $to;

		return array(
			'site_title'       => (string) get_bloginfo( 'name' ),
			'site_url'         => (string) get_option( 'siteurl' ),
			'home_url'         => (string) get_option( 'home' ),
			'site_description' => (string) get_bloginfo( 'description' ),
			'admin_email'      => (string) get_option( 'admin_email' ),
			'recipient'        => $recipient,
			'date'             => date_i18n( (string) get_option( 'date_format' ) ),
			'time'             => date_i18n( (string) get_option( 'time_format' ) ),
			'year'             => date_i18n( 'Y' ),
		);
	}

	/**
	 * Whether the plugin is currently in block mode (`enabled=2`). Block
	 * mode short-circuits the engine because no mail leaves the server.
	 */
	private static function isBlockMode(): bool {
		$tmq = MainOptions::get();
		return isset( $tmq['enabled'] ) && '2' === (string) $tmq['enabled'];
	}
}
