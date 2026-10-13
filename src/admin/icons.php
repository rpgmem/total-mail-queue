<?php
/**
 * The admin icon set.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Admin;

/**
 * A small set of 24×24 stroke icons. Where Free Form Certificate has an icon
 * for the same idea, the drawing is its own, so the plugins look alike.
 *
 * Each icon is a CSS mask filled with `currentColor`, so it takes its
 * element's text colour and follows the dark theme with nothing extra. Markup
 * reaches one through {@see Icons::tag()}; the rules come from
 * {@see Icons::stylesheet()}, written into `assets/css/admin.css` between the
 * `tmq-icons` markers; `GeneratedCssTest` fails when the two disagree.
 *
 * A new icon joins {@see Icons::PATHS}; no emoji, dashicon or one-off inline
 * SVG stands in for it.
 */
final class Icons {

	/**
	 * Icon name => SVG body (stroked, no fill, on a 24×24 grid).
	 */
	public const PATHS = array(
		'settings'  => '<circle cx="12" cy="12" r="3"/><circle cx="12" cy="12" r="7"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9L7 7M17 17l2.1 2.1M4.9 19.1L7 17M17 7l2.1-2.1"/>',
		'log'       => '<path d="M9 6h11M9 12h11M9 18h11"/><circle cx="4.5" cy="6" r="1" fill="currentColor" stroke="none"/><circle cx="4.5" cy="12" r="1" fill="currentColor" stroke="none"/><circle cx="4.5" cy="18" r="1" fill="currentColor" stroke="none"/>',
		'retention' => '<path d="M3 13l2.5-8h13L21 13v6H3z"/><path d="M3 13h5l1.5 2.5h5L16 13h5"/>',
		'smtp'      => '<rect x="3" y="4" width="18" height="7" rx="1.5"/><rect x="3" y="13" width="18" height="7" rx="1.5"/><path d="M7 7.5h.01M7 16.5h.01M11 7.5h6M11 16.5h6"/>',
		'sources'   => '<path d="M3 12V4a1 1 0 0 1 1-1h8l9 9-9 9z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
		'templates' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M9 9v11"/>',
		'docs'      => '<path d="M4 5a2 2 0 0 1 2-2h14v16H6a2 2 0 0 0-2 2z"/><path d="M4 19V5M8 7h8"/>',
		'top'       => '<path d="M6 15l6-6 6 6"/>',
		'faq'       => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.6v.6"/><circle cx="12" cy="17" r="1.1" fill="currentColor" stroke="none"/>',
		'cron'      => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		'send'      => '<path d="M21 3L3 10.5l7 2.5 2.5 7z"/><path d="M21 3L10 13"/>',
		'alert'     => '<path d="M6 16v-5a6 6 0 0 1 12 0v5l2 2H4z"/><path d="M10 20.5a2 2 0 0 0 4 0"/>',
		'advanced'  => '<path d="M4 6h9M17 6h3M4 12h3M11 12h9M4 18h11M19 18h1"/><circle cx="15" cy="6" r="2"/><circle cx="9" cy="12" r="2"/><circle cx="17" cy="18" r="2"/>',
		'theme'     => '<path d="M20 14.5A8 8 0 1 1 9.5 4a6.5 6.5 0 0 0 10.5 10.5z"/>',
		'export'    => '<path d="M12 4v11M7 10l5 5 5-5M5 20h14"/>',
		'import'    => '<path d="M12 16V5M7 10l5-5 5 5M5 20h14"/>',
		'header'    => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9.5h18M7 7h5"/>',
		'body'      => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 9h10M7 12.5h10M7 16h6"/>',
		'footer'    => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 14.5h18M7 17h5"/>',
		'chevron'   => '<path d="M9 6l6 6-6 6"/>',
		'search'    => '<circle cx="11" cy="11" r="6.5"/><path d="M20 20l-4.4-4.4"/>',
		'wrapper'   => '<rect x="2.5" y="2.5" width="19" height="19" rx="3"/><rect x="7" y="7" width="10" height="10" rx="1"/>',
		'preview'   => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
		'access'    => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.8-3.5 3.4-5.5 6.5-5.5s5.7 2 6.5 5.5"/><path d="M16 4.6a3.5 3.5 0 0 1 0 6.8M18 14.8c2 .7 3.2 2.5 3.5 5.2"/>',
	);

	/**
	 * Tab slug => icon name. Every tab in {@see PluginPage::tabs()} has one.
	 */
	public const TABS = array(
		PluginPage::TAB_SETTINGS  => 'settings',
		PluginPage::TAB_LOG       => 'log',
		PluginPage::TAB_QUEUE     => 'retention',
		PluginPage::TAB_SMTP      => 'smtp',
		PluginPage::TAB_SOURCES   => 'sources',
		PluginPage::TAB_TEMPLATES => 'templates',
		PluginPage::TAB_CRON_INFO => 'cron',
		PluginPage::TAB_DOCS      => 'docs',
		PluginPage::TAB_FAQ       => 'faq',
	);

	/**
	 * Markup for one icon: a decorative span the stylesheet paints.
	 *
	 * @param string $name A key of {@see Icons::PATHS}.
	 */
	public static function tag( string $name ): string {
		if ( ! isset( self::PATHS[ $name ] ) ) {
			return '';
		}
		return '<span class="tmq-icon tmq-icon-' . esc_attr( $name ) . '" aria-hidden="true"></span>';
	}

	/**
	 * A card or section heading: the icon, then the escaped title.
	 *
	 * Every heading the plugin prints on its screens goes through here, so
	 * none ships without an icon; `AdminHeadingsTest` holds that.
	 *
	 * @param string $icon  A key of {@see Icons::PATHS}.
	 * @param string $title Heading text, already translated.
	 * @param string $tag   `h2` or `h3`.
	 */
	public static function heading( string $icon, string $title, string $tag = 'h2' ): string {
		$tag = 'h3' === $tag ? 'h3' : 'h2';
		return '<' . $tag . ' class="tmq-card-title">' . self::tag( $icon ) . esc_html( $title ) . '</' . $tag . '>';
	}

	/**
	 * The icon rules: one mask per icon, painted in `currentColor`.
	 */
	public static function stylesheet(): string {
		$css = '.tmq-icon{display:inline-block;flex:0 0 auto;width:18px;height:18px;margin-right:8px;vertical-align:-3px;background-color:currentColor;-webkit-mask:var(--tmq-icon) center/contain no-repeat;mask:var(--tmq-icon) center/contain no-repeat}';
		foreach ( self::PATHS as $name => $body ) {
			$css .= '.tmq-icon-' . $name . '{--tmq-icon:url("' . self::dataUri( $body ) . '")}';
		}
		return $css;
	}

	/**
	 * A drawing as a URL-encoded SVG data URI. The stroke colour is
	 * irrelevant (only the mask's alpha is used), so it is plain black.
	 *
	 * @param string $body SVG body from {@see Icons::PATHS}.
	 */
	private static function dataUri( string $body ): string {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $body . '</svg>';
		return 'data:image/svg+xml,' . rawurlencode( $svg );
	}
}
