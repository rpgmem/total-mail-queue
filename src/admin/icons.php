<?php
/**
 * The admin icon set.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Admin;

/**
 * A small set of 24×24 stroke icons, drawn for this plugin.
 *
 * Each icon is a CSS mask filled with `currentColor`, so it takes its
 * element's text colour and follows the dark theme with nothing extra. Markup
 * reaches one through {@see Icons::tag()}; the rules come from
 * {@see Icons::stylesheet()}, attached to the admin sheet by {@see Assets}.
 *
 * A new icon joins {@see Icons::PATHS}; no emoji, dashicon or one-off inline
 * SVG stands in for it.
 */
final class Icons {

	/**
	 * Icon name => SVG body (stroked, no fill, on a 24×24 grid).
	 */
	public const PATHS = array(
		'settings'  => '<path d="M4 6h9M17 6h3M4 12h3M11 12h9M4 18h11M19 18h1"/><circle cx="15" cy="6" r="2"/><circle cx="9" cy="12" r="2"/><circle cx="17" cy="18" r="2"/>',
		'log'       => '<path d="M9 6h11M9 12h11M9 18h11"/><circle cx="5" cy="6" r="1"/><circle cx="5" cy="12" r="1"/><circle cx="5" cy="18" r="1"/>',
		'retention' => '<path d="M3 13h5l1 3h6l1-3h5"/><path d="M5.5 5h13l2.5 8v6H3v-6z"/>',
		'smtp'      => '<rect x="3" y="4" width="18" height="7" rx="1.5"/><rect x="3" y="13" width="18" height="7" rx="1.5"/><path d="M7 7.5h2M7 16.5h2"/>',
		'sources'   => '<path d="M3 12V4h8l10 10-7 7z"/><circle cx="7.5" cy="8" r="1.5"/>',
		'templates' => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 9v12"/>',
		'docs'      => '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21.5z"/><path d="M4 21.5V5.5M8 7h8M8 11h6"/>',
		'top'       => '<path d="M6 15l6-6 6 6"/>',
		'faq'       => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 0 1 5 .4c0 1.7-2.5 2.2-2.5 3.6M12 17h.01"/>',
		'cron'      => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		'send'      => '<path d="M21 3 3 10.5l7 2.5 2.5 7z"/><path d="M10 13 21 3"/>',
		'alert'     => '<path d="M6 16v-5a6 6 0 0 1 12 0v5l2 2H4z"/><path d="M10 20.5a2 2 0 0 0 4 0"/>',
		'advanced'  => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9 7 7M17 17l2.1 2.1M4.9 19.1 7 17M17 7l2.1-2.1"/>',
		'theme'     => '<path d="M20 14.5A8 8 0 1 1 9.5 4a6.5 6.5 0 0 0 10.5 10.5z"/>',
		'export'    => '<path d="M12 4v11M7 10l5 5 5-5M4 20h16"/>',
		'import'    => '<path d="M12 20V9M7 14l5-5 5 5M4 4h16"/>',
		'header'    => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9.5h18M7 7h5"/>',
		'body'      => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 9h10M7 12.5h10M7 16h6"/>',
		'footer'    => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 14.5h18M7 17h5"/>',
		'chevron'   => '<path d="m9 6 6 6-6 6"/>',
		'search'    => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
		'wrapper'   => '<rect x="2.5" y="2.5" width="19" height="19" rx="3"/><rect x="7" y="7" width="10" height="10" rx="1"/>',
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
