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
 * A button carries its icon the same way, as the `tmq-icon-<name>` class on
 * the button itself ({@see Button}), painted in its `::before`. The admin
 * menu's mark ({@see Icons::BRAND}) and the screenshot viewer's controls
 * ({@see Icons::VIEWER}) come from here too.
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
		// Button icons (#164), each Free Form Certificate's drawing for the
		// same meaning, so a button reads the same in both plugins.
		'save'      => '<path d="M5 4h11l4 4v11a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1z"/><path d="M8 4v5h7V4M8 20v-6h8v6"/>',
		'plus'      => '<path d="M12 5v14M5 12h14"/>',
		'minus'     => '<path d="M5 12h14"/>',
		'check'     => '<path d="M5 12.5l4.5 4.5L19 7"/>',
		'cross'     => '<path d="M6 6l12 12M18 6L6 18"/>',
		'copy'      => '<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/>',
		'sync'      => '<path d="M20 11a8 8 0 0 0-14.3-4.9L4 8M4 4v4h4M4 13a8 8 0 0 0 14.3 4.9L20 16M20 20v-4h-4"/>',
		'restore'   => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/>',
		'filter'    => '<path d="M3 5h18l-7 8v6l-4-2v-4z"/>',
		'image'     => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M21 16l-5-5-9 9"/>',
		'network'   => '<rect x="9" y="3" width="6" height="5" rx="1"/><rect x="3" y="16" width="6" height="5" rx="1"/><rect x="15" y="16" width="6" height="5" rx="1"/><path d="M12 8v4M6 16v-2h12v2"/>',
		'previous'  => '<path d="M15 6l-6 6 6 6"/>',
		'next'      => '<path d="M9 6l6 6-6 6"/>',
	);

	/**
	 * The plugin's own mark, drawn in the admin menu: three filled shapes on a
	 * 178×178 grid, the brand icon's silhouette. It is not one of
	 * {@see Icons::PATHS} because it is filled, not stroked, and lives on its
	 * own grid; {@see Icons::menuMask()} turns it into the mask
	 * {@see Menu::menuStylesheet()} draws.
	 */
	public const BRAND = '<path fill-rule="evenodd" clip-rule="evenodd" d="M78.7241 5.25309C76.9074 4.7081 74.9401 5.05413 73.4184 6.18629C71.8968 7.31845 71 9.10341 71 11.0001V167C71 168.897 71.8968 170.682 73.4184 171.814C74.9401 172.946 76.9074 173.292 78.7241 172.747L158.724 148.747C161.262 147.986 163 145.65 163 143V35C163 32.3504 161.262 30.0145 158.724 29.2531L78.7241 5.25309ZM85.7887 22.3846C87.7854 21.4394 90.1703 22.2919 91.1154 24.2887L122.852 91.3378L143.334 44.4002C144.217 42.3755 146.575 41.4503 148.6 42.3338C150.625 43.2174 151.55 45.575 150.666 47.5998L126.666 102.6C126.039 104.037 124.629 104.976 123.061 105C121.493 105.024 120.056 104.129 119.385 102.711L83.8846 27.7113C82.9394 25.7146 83.7919 23.3297 85.7887 22.3846Z"/>'
		. '<path d="M49 13C52.3137 13 55 15.6863 55 19V159C55 162.314 52.3137 165 49 165C45.6863 165 43 162.314 43 159V19C43 15.6863 45.6863 13 49 13Z"/>'
		. '<path d="M21 21C24.3137 21 27 23.6863 27 27V151C27 154.314 24.3137 157 21 157C17.6863 157 15 154.314 15 151V27C15 23.6863 17.6863 21 21 21Z"/>';

	/**
	 * The icons the "View details" screenshot viewer draws. That modal loads
	 * `screenshots.css`, not `admin.css`, so it carries its own copy of these
	 * masks, generated by {@see Icons::masks()}.
	 */
	public const VIEWER = array( 'minus', 'plus', 'cross', 'previous', 'next' );

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
		return $css . self::masks( array_keys( self::PATHS ) );
	}

	/**
	 * One `--tmq-icon` declaration per named icon. Whatever paints the icon
	 * (`.tmq-icon`, a `.tmq-btn`'s `::before`, the screenshot viewer) reads
	 * that property, so an element carrying `tmq-icon-<name>` has its drawing.
	 *
	 * @param string[] $names Keys of {@see Icons::PATHS}; unknown ones are skipped.
	 */
	public static function masks( array $names ): string {
		$css = '';
		foreach ( $names as $name ) {
			if ( isset( self::PATHS[ $name ] ) ) {
				$css .= '.tmq-icon-' . $name . '{--tmq-icon:url("' . self::dataUri( self::PATHS[ $name ] ) . '")}';
			}
		}
		return $css;
	}

	/**
	 * The admin-menu mark ({@see Icons::BRAND}) as a `url()` for a mask.
	 * The fill colour is irrelevant: only the mask's alpha is used.
	 */
	public static function menuMask(): string {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 178 178" fill="#000">' . self::BRAND . '</svg>';
		return 'url("data:image/svg+xml,' . rawurlencode( $svg ) . '")';
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
