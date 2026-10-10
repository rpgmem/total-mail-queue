<?php
/**
 * Admin top-level menu and submenus.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Admin;

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
	 * The menu glyph. Its own colour is irrelevant: it is drawn as a mask, so
	 * only its shape is used and the colour comes from the menu item.
	 */
	private const ICON = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTc4IiBoZWlnaHQ9IjE3OCIgdmlld0JveD0iMCAwIDE3OCAxNzgiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxwYXRoIGZpbGwtcnVsZT0iZXZlbm9kZCIgY2xpcC1ydWxlPSJldmVub2RkIiBkPSJNNzguNzI0MSA1LjI1MzA5Qzc2LjkwNzQgNC43MDgxIDc0Ljk0MDEgNS4wNTQxMyA3My40MTg0IDYuMTg2MjlDNzEuODk2OCA3LjMxODQ1IDcxIDkuMTAzNDEgNzEgMTEuMDAwMVYxNjdDNzEgMTY4Ljg5NyA3MS44OTY4IDE3MC42ODIgNzMuNDE4NCAxNzEuODE0Qzc0Ljk0MDEgMTcyLjk0NiA3Ni45MDc0IDE3My4yOTIgNzguNzI0MSAxNzIuNzQ3TDE1OC43MjQgMTQ4Ljc0N0MxNjEuMjYyIDE0Ny45ODYgMTYzIDE0NS42NSAxNjMgMTQzVjM1QzE2MyAzMi4zNTA0IDE2MS4yNjIgMzAuMDE0NSAxNTguNzI0IDI5LjI1MzFMNzguNzI0MSA1LjI1MzA5Wk04NS43ODg3IDIyLjM4NDZDODcuNzg1NCAyMS40Mzk0IDkwLjE3MDMgMjIuMjkxOSA5MS4xMTU0IDI0LjI4ODdMMTIyLjg1MiA5MS4zMzc4TDE0My4zMzQgNDQuNDAwMkMxNDQuMjE3IDQyLjM3NTUgMTQ2LjU3NSA0MS40NTAzIDE0OC42IDQyLjMzMzhDMTUwLjYyNSA0My4yMTc0IDE1MS41NSA0NS41NzUgMTUwLjY2NiA0Ny41OTk4TDEyNi42NjYgMTAyLjZDMTI2LjAzOSAxMDQuMDM3IDEyNC42MjkgMTA0Ljk3NiAxMjMuMDYxIDEwNUMxMjEuNDkzIDEwNS4wMjQgMTIwLjA1NiAxMDQuMTI5IDExOS4zODUgMTAyLjcxMUw4My44ODQ2IDI3LjcxMTNDODIuOTM5NCAyNS43MTQ2IDgzLjc5MTkgMjMuMzI5NyA4NS43ODg3IDIyLjM4NDZaIiBmaWxsPSIjYTdhYWFkIi8+CjxwYXRoIGQ9Ik00OSAxM0M1Mi4zMTM3IDEzIDU1IDE1LjY4NjMgNTUgMTlWMTU5QzU1IDE2Mi4zMTQgNTIuMzEzNyAxNjUgNDkgMTY1QzQ1LjY4NjMgMTY1IDQzIDE2Mi4zMTQgNDMgMTU5VjE5QzQzIDE1LjY4NjMgNDUuNjg2MyAxMyA0OSAxM1oiIGZpbGw9IiNhN2FhYWQiLz4KPHBhdGggZD0iTTIxIDIxQzI0LjMxMzcgMjEgMjcgMjMuNjg2MyAyNyAyN1YxNTFDMjcgMTU0LjMxNCAyNC4zMTM3IDE1NyAyMSAxNTdDMTcuNjg2MyAxNTcgMTUgMTU0LjMxNCAxNSAxNTFWMjdDMTUgMjMuNjg2MyAxNy42ODYzIDIxIDIxIDIxWiIgZmlsbD0iI2E3YWFhZCIvPgo8L3N2Zz4=';

	/**
	 * Hook the menu registration onto `admin_menu`, and the glyph onto every
	 * admin screen, since the sidebar is on every screen.
	 *
	 * `admin_enqueue_scripts` at the default priority: core has registered
	 * `admin-menu` by then, which is the sheet the rules attach to.
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'addMenuItems' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueueMenuStyle' ) );
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
	 * `admin_enqueue_scripts` callback: attach the menu rules to core's
	 * `admin-menu` sheet, so they load wherever the sidebar does.
	 */
	public static function enqueueMenuStyle(): void {
		wp_add_inline_style( 'admin-menu', self::menuStylesheet() );
	}

	/**
	 * The sidebar rules: the glyph and the hidden submenu.
	 *
	 * The glyph is a CSS mask filled with `currentColor`, so it takes the
	 * colour WordPress gives the menu item in every admin colour scheme, on
	 * hover, on the current page and in the folded menu, from CSS alone. A
	 * base64 SVG `icon_url` depends on core's svg-painter, a script that
	 * rewrites the `fill` after load and leaves the glyph its baked-in grey
	 * until it runs. In forced-colors mode the mask takes the system link
	 * colour.
	 */
	public static function menuStylesheet(): string {
		$item = '#adminmenu #toplevel_page_' . self::SLUG;
		$mask = 'url("' . self::ICON . '") center / 20px 20px no-repeat';

		return $item . ' div.wp-menu-image::before{content:"";display:block;width:20px;height:20px;margin:7px auto 0;padding:0;background-color:currentColor;-webkit-mask:' . $mask . ';mask:' . $mask . '}'
			. $item . ' .wp-submenu{display:none}'
			. '@media (forced-colors:active){' . $item . ' div.wp-menu-image::before{forced-color-adjust:none;background-color:LinkText}}';
	}
}
