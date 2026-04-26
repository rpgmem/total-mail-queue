<?php
/**
 * wp-admin top-level menu and submenus.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Admin;

/**
 * Adds the plugin's top-level menu plus the per-tab submenus. Every entry
 * dispatches to {@see PluginPage::render()} which then routes by `page` slug.
 */
final class Menu {

	/**
	 * Slug of the top-level menu page (and the "Settings" submenu).
	 */
	public const SLUG = 'wp_tmq_mail_queue';

	/**
	 * Hook the menu registration onto `admin_menu`.
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'addMenuItems' ) );
	}

	/**
	 * `admin_menu` callback. Registers the top-level entry plus every tab.
	 */
	public static function addMenuItems(): void {
		$icon = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTc4IiBoZWlnaHQ9IjE3OCIgdmlld0JveD0iMCAwIDE3OCAxNzgiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxwYXRoIGZpbGwtcnVsZT0iZXZlbm9kZCIgY2xpcC1ydWxlPSJldmVub2RkIiBkPSJNNzguNzI0MSA1LjI1MzA5Qzc2LjkwNzQgNC43MDgxIDc0Ljk0MDEgNS4wNTQxMyA3My40MTg0IDYuMTg2MjlDNzEuODk2OCA3LjMxODQ1IDcxIDkuMTAzNDEgNzEgMTEuMDAwMVYxNjdDNzEgMTY4Ljg5NyA3MS44OTY4IDE3MC42ODIgNzMuNDE4NCAxNzEuODE0Qzc0Ljk0MDEgMTcyLjk0NiA3Ni45MDc0IDE3My4yOTIgNzguNzI0MSAxNzIuNzQ3TDE1OC43MjQgMTQ4Ljc0N0MxNjEuMjYyIDE0Ny45ODYgMTYzIDE0NS42NSAxNjMgMTQzVjM1QzE2MyAzMi4zNTA0IDE2MS4yNjIgMzAuMDE0NSAxNTguNzI0IDI5LjI1MzFMNzguNzI0MSA1LjI1MzA5Wk04NS43ODg3IDIyLjM4NDZDODcuNzg1NCAyMS40Mzk0IDkwLjE3MDMgMjIuMjkxOSA5MS4xMTU0IDI0LjI4ODdMMTIyLjg1MiA5MS4zMzc4TDE0My4zMzQgNDQuNDAwMkMxNDQuMjE3IDQyLjM3NTUgMTQ2LjU3NSA0MS40NTAzIDE0OC42IDQyLjMzMzhDMTUwLjYyNSA0My4yMTc0IDE1MS41NSA0NS41NzUgMTUwLjY2NiA0Ny41OTk4TDEyNi42NjYgMTAyLjZDMTI2LjAzOSAxMDQuMDM3IDEyNC42MjkgMTA0Ljk3NiAxMjMuMDYxIDEwNUMxMjEuNDkzIDEwNS4wMjQgMTIwLjA1NiAxMDQuMTI5IDExOS4zODUgMTAyLjcxMUw4My44ODQ2IDI3LjcxMTNDODIuOTM5NCAyNS43MTQ2IDgzLjc5MTkgMjMuMzI5NyA4NS43ODg3IDIyLjM4NDZaIiBmaWxsPSIjYTdhYWFkIi8+CjxwYXRoIGQ9Ik00OSAxM0M1Mi4zMTM3IDEzIDU1IDE1LjY4NjMgNTUgMTlWMTU5QzU1IDE2Mi4zMTQgNTIuMzEzNyAxNjUgNDkgMTY1QzQ1LjY4NjMgMTY1IDQzIDE2Mi4zMTQgNDMgMTU5VjE5QzQzIDE1LjY4NjMgNDUuNjg2MyAxMyA0OSAxM1oiIGZpbGw9IiNhN2FhYWQiLz4KPHBhdGggZD0iTTIxIDIxQzI0LjMxMzcgMjEgMjcgMjMuNjg2MyAyNyAyN1YxNTFDMjcgMTU0LjMxNCAyNC4zMTM3IDE1NyAyMSAxNTdDMTcuNjg2MyAxNTcgMTUgMTU0LjMxNCAxNSAxNTFWMjdDMTUgMjMuNjg2MyAxNy42ODYzIDIxIDIxIDIxWiIgZmlsbD0iI2E3YWFhZCIvPgo8L3N2Zz4=';

		$render = array( PluginPage::class, 'render' );

		add_menu_page( 'Total Mail Queue', 'Total Mail Queue', 'manage_options', self::SLUG, $render, $icon );
		add_submenu_page( self::SLUG, __( 'Settings', 'total-mail-queue' ), __( 'Settings', 'total-mail-queue' ), 'manage_options', self::SLUG, $render );
		add_submenu_page( self::SLUG, __( 'Log', 'total-mail-queue' ), __( 'Log', 'total-mail-queue' ), 'manage_options', 'wp_tmq_mail_queue-tab-log', $render );
		add_submenu_page( self::SLUG, __( 'Retention', 'total-mail-queue' ), __( 'Retention', 'total-mail-queue' ), 'manage_options', 'wp_tmq_mail_queue-tab-queue', $render );
		add_submenu_page( self::SLUG, __( 'SMTP Accounts', 'total-mail-queue' ), __( 'SMTP Accounts', 'total-mail-queue' ), 'manage_options', 'wp_tmq_mail_queue-tab-smtp', $render );
		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			add_submenu_page( self::SLUG, __( 'Cron Information', 'total-mail-queue' ), __( 'Cron Information', 'total-mail-queue' ), 'manage_options', 'wp_tmq_mail_queue-tab-croninfo', $render );
		}
		add_submenu_page( self::SLUG, __( 'FAQ', 'total-mail-queue' ), __( 'FAQ', 'total-mail-queue' ), 'manage_options', 'wp_tmq_mail_queue-tab-faq', $render );
	}
}
