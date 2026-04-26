<?php
/**
 * Action links shown next to the plugin in wp-admin → Plugins.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Admin;

/**
 * Appends the plugin's tab links (Settings / Log / Retention / SMTP / FAQ)
 * to the row of action links on the Plugins screen.
 */
final class PluginRowLinks {

	/**
	 * Plugin entry filename (passed as `__FILE__` from the bootstrap).
	 *
	 * Built from the container so the filter target is computed once per request.
	 *
	 * @return string Filter tag, e.g. `plugin_action_links_total-mail-queue/total-mail-queue.php`.
	 */
	private static function filterTag(): string {
		return 'plugin_action_links_' . plugin_basename( \TotalMailQueue\Plugin::container()->get( 'plugin.file' ) );
	}

	/**
	 * Hook the filter.
	 */
	public static function register(): void {
		add_filter( self::filterTag(), array( self::class, 'addLinks' ) );
	}

	/**
	 * Filter callback. Appends the plugin's quick links.
	 *
	 * @param array<int,string> $actions Existing action links.
	 * @return array<int,string>
	 */
	public static function addLinks( $actions ): array {
		$links = array(
			'<a href="' . esc_url( admin_url( 'admin.php?page=wp_tmq_mail_queue' ) ) . '">' . esc_html__( 'Settings', 'total-mail-queue' ) . '</a>',
			'<a href="' . esc_url( admin_url( 'admin.php?page=wp_tmq_mail_queue-tab-log' ) ) . '">' . esc_html__( 'Log', 'total-mail-queue' ) . '</a>',
			'<a href="' . esc_url( admin_url( 'admin.php?page=wp_tmq_mail_queue-tab-queue' ) ) . '">' . esc_html__( 'Retention', 'total-mail-queue' ) . '</a>',
			'<a href="' . esc_url( admin_url( 'admin.php?page=wp_tmq_mail_queue-tab-smtp' ) ) . '">' . esc_html__( 'SMTP', 'total-mail-queue' ) . '</a>',
			'<a href="' . esc_url( admin_url( 'admin.php?page=wp_tmq_mail_queue-tab-faq' ) ) . '">' . esc_html__( 'FAQ', 'total-mail-queue' ) . '</a>',
		);
		return array_merge( $actions, $links );
	}
}
