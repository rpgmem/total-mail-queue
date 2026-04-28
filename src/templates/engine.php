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
	 * `wp_mail_from` filter callback. Returns the admin-defined Default
	 * Sender email (from the main Settings tab) if configured; otherwise
	 * leaves the upstream value untouched.
	 *
	 * @param string $email Current from-address.
	 * @return string
	 */
	public static function overrideFromEmail( $email ): string {
		$options = MainOptions::get();
		if ( empty( $options['from_email'] ) ) {
			return (string) $email;
		}
		return (string) $options['from_email'];
	}

	/**
	 * `wp_mail_from_name` filter callback. Returns the admin-defined Default
	 * Sender name (from the main Settings tab) if configured; otherwise
	 * leaves the upstream value untouched.
	 *
	 * @param string $name Current from-name.
	 * @return string
	 */
	public static function overrideFromName( $name ): string {
		$options = MainOptions::get();
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
	 * Whether the templates feature is currently enabled. Convenience
	 * delegate to {@see Options::isEnabled()} so callers don't need to
	 * reach across modules for the toggle check.
	 */
	public static function isEnabled(): bool {
		return Options::isEnabled();
	}

	/**
	 * Resolved template options. Convenience delegate to
	 * {@see Options::get()} for the same reason as {@see isEnabled()}.
	 *
	 * @return array<string,mixed>
	 */
	public static function options(): array {
		return Options::get();
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
	 * Substitute every `{token}` placeholder in the rendered HTML. Thin
	 * delegate to {@see Tokens::replace()} — the registry-aware module
	 * combines globals with filter-supplied additions (e.g. the bundled
	 * WooCommerce handler).
	 *
	 * @param string              $html Rendered HTML.
	 * @param array<string,mixed> $args wp_mail() arguments.
	 * @return string
	 */
	private static function replacePlaceholders( string $html, array $args ): string {
		return Tokens::replace( $html, $args );
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
