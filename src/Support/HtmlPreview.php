<?php
/**
 * HTML preview rendering used by the admin log/queue tables.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Support;

/**
 * Builds the expandable email-source preview shown when an admin clicks
 * "View message" on a row of the Log/Retention tables, and provides the
 * base64 redaction helper used by that preview.
 */
final class HtmlPreview {

	/**
	 * Render the message body as an HTML preview with collapsible sections.
	 *
	 * @param string $message            Raw message body as stored in the queue.
	 * @param bool   $is_content_type_html Whether the email's Content-Type header indicated text/html.
	 * @return string HTML safe to embed in the admin response.
	 */
	public static function renderListMessage( string $message, bool $is_content_type_html ): string {
		$parts   = explode( '<body', $message );
		$is_html = $is_content_type_html || count( $parts ) > 1;

		if ( $is_html ) {
			if ( count( $parts ) > 1 ) {
				$header = $parts[0];
				$body   = '<body' . $parts[1];
			} else {
				$header = '';
				$body   = $parts[0];
			}
			$parts = explode( '</body>', $body );
			if ( count( $parts ) > 1 ) {
				$body   = $parts[0] . '</body>';
				$footer = $parts[1];
			} else {
				$body   = $parts[0];
				$footer = '';
			}
			if ( ! function_exists( 'convert_html_to_text' ) ) {
				require_once dirname( __DIR__, 2 ) . '/lib/html2text/html2text.php';
			}
			$internal_errors = libxml_use_internal_errors( true );
			$text            = convert_html_to_text( $body );
			libxml_use_internal_errors( $internal_errors );
		} else {
			$text   = $message;
			$header = '';
			$body   = '';
			$footer = '';
		}

		$html  = '<details class="tmq-email-source-meta" open><summary>' . esc_html__( 'Text', 'total-mail-queue' ) . '</summary><pre class="tmq-email-plain-text">' . esc_html( $text ) . '</pre></details>';
		$html .= $header ? '<details class="tmq-email-source-meta"><summary>' . esc_html__( 'HTML Header', 'total-mail-queue' ) . '</summary><pre>' . esc_html( self::redactBase64( $header ) ) . '</pre></details>' : '';
		$html .= $body ? '<details class="tmq-email-source-meta"><summary>' . esc_html__( 'HTML Body', 'total-mail-queue' ) . '</summary><pre>' . esc_html( self::redactBase64( $body ) ) . '</pre></details>' : '';
		$html .= $footer ? '<details class="tmq-email-source-meta"><summary>' . esc_html__( 'HTML Footer', 'total-mail-queue' ) . '</summary><pre>' . esc_html( self::redactBase64( $footer ) ) . '</pre></details>' : '';
		return $html;
	}

	/**
	 * Replace the base64 payload of inline data URLs with a placeholder.
	 *
	 * Inline images (`src="data:image/png;base64,…"`) inflate the preview
	 * dramatically and the byte content offers no value to the reader.
	 *
	 * @param string $html HTML fragment.
	 * @return string The same fragment with base64 payloads collapsed to "[...]".
	 */
	public static function redactBase64( string $html ): string {
		$result = preg_replace( '/;base64,[^"\']+("|\')+/', ';base64, [...] $1', $html );
		return is_string( $result ) ? $result : $html;
	}
}
