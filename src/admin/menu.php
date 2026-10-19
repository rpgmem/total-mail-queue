<?php
/**
 * Admin top-level menu and submenus.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Admin;

use TotalMailQueue\Plugin;

/**
 * Adds the plugin's top-level menu plus one hidden submenu per tab. Every
 * entry dispatches to {@see PluginPage::render()} which then routes by `page`
 * slug.
 *
 * The submenus stay registered, so each tab keeps its own slug and screen id
 * (links, redirects, list-table classes and bookmarks all depend on them), but
 * they are hidden from the sidebar: the tab column on the page is the
 * navigation. See {@see Menu::menuStylesheet()}.
 */
final class Menu {

	/**
	 * Slug of the top-level menu page (and the "Settings" submenu).
	 */
	public const SLUG = 'wp_tmq_mail_queue';

	/**
	 * Hook the menu registration onto `admin_menu`, and the glyph onto every
	 * admin screen, since the sidebar is on every screen.
	 *
	 * `admin_enqueue_scripts` at the default priority: core has registered
	 * `admin-menu` by then, which the chrome sheet depends on.
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'addMenuItems' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueueChromeStyle' ) );
	}

	/**
	 * `admin_menu` callback. Registers the top-level entry plus every tab.
	 */
	public static function addMenuItems(): void {
		$render = array( PluginPage::class, 'render' );

		// `none` leaves the icon slot empty; the stylesheet draws the glyph.
		// The entry carries the Settings tab's capability. For a user who may
		// only view, WordPress then points the entry at the first tab that
		// user may open (the Log) instead of at a Settings page they would be
		// refused; each tab asks for its own capability below.
		add_menu_page( 'Total Mail Queue', 'Total Mail Queue', Access::tabCapability( PluginPage::TAB_SETTINGS ), self::SLUG, $render, 'none' );
		foreach ( PluginPage::tabs() as $slug => $label ) {
			add_submenu_page( self::SLUG, $label, $label, Access::tabCapability( $slug ), $slug, $render );
		}
		// The FAQ tab became the documentation; its slug stays registered so
		// bookmarks and links in old emails still open a page.
		$docs = __( 'Documentation', 'total-mail-queue' );
		add_submenu_page( self::SLUG, $docs, $docs, Access::VIEW, PluginPage::TAB_FAQ, $render );
	}

	/**
	 * `admin_enqueue_scripts` callback: load `admin-chrome.css`, the sheet
	 * for what the plugin draws on every admin screen (the menu glyph, the
	 * WordPress-version notice), after core's `admin-menu` sheet.
	 *
	 * The menu rules in it are generated from {@see Menu::menuStylesheet()};
	 * `GeneratedCssTest` fails when the file and the method disagree.
	 */
	public static function enqueueChromeStyle(): void {
		$base = plugin_dir_url( Plugin::container()->get( 'plugin.file' ) );
		wp_enqueue_style( 'wp_tmq_chrome', $base . 'assets/css/admin-chrome' . Assets::suffix() . '.css', array( 'admin-menu' ), Plugin::VERSION );
	}

	/**
	 * The sidebar rules: the glyph and the hidden submenu.
	 *
	 * The glyph is the plugin's mark from the icon registry
	 * ({@see Icons::BRAND}), drawn as a CSS mask filled with `currentColor`,
	 * so it takes the colour WordPress gives the menu item in every admin
	 * colour scheme, on hover, on the current page and in the folded menu,
	 * from CSS alone. A
	 * base64 SVG `icon_url` depends on core's svg-painter, a script that
	 * rewrites the `fill` after load and leaves the glyph its baked-in grey
	 * until it runs. In forced-colors mode the mask takes the system link
	 * colour.
	 */
	public static function menuStylesheet(): string {
		$item = '#adminmenu #toplevel_page_' . self::SLUG;
		$mask = Icons::menuMask() . ' center / 20px 20px no-repeat';

		return $item . ' div.wp-menu-image::before{content:"";display:block;width:20px;height:20px;margin:7px auto 0;padding:0;background-color:currentColor;-webkit-mask:' . $mask . ';mask:' . $mask . '}'
			. $item . ' .wp-submenu{display:none}'
			. '@media (forced-colors:active){' . $item . ' div.wp-menu-image::before{forced-color-adjust:none;background-color:LinkText}}';
	}
}
