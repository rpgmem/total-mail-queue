<?php
/**
 * List-view renderer for the Sources admin tab.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Admin\Pages;

use TotalMailQueue\Admin\Tables\SourcesTable;
use TotalMailQueue\Sources\KnownSources;
use TotalMailQueue\Sources\Repository as SourcesRepository;

/**
 * Renders the Sources index: per-group enable/disable toolbar, the
 * WP_List_Table itself, and the wrapping POST form that carries the bulk
 * action nonce + the active group filter.
 *
 * Pulled out of {@see SourcesPage} so the form/table chrome lives next to
 * the toolbar that drives it.
 */
final class SourcesListRenderer {

	/**
	 * Render the toolbar + the WP_List_Table.
	 */
	public static function render(): void {
		self::renderGroupToolbar();

		$table = new SourcesTable();
		$table->prepare_items();

		echo '<form method="get">';
		echo '<input type="hidden" name="page" value="wp_tmq_mail_queue-tab-sources" />';
		// `display()` renders the WP_List_Table including the bulk-action
		// dropdown; bulk POST relies on a separate wrapping <form method=post>
		// — see below. Keeping the get-form for the group filter is cheaper
		// and avoids fighting WP_List_Table's expectations.
		echo '</form>';

		echo '<form method="post">';
		wp_nonce_field( 'wp_tmq_sources_bulk', 'wp_tmq_sources_nonce' );
		echo '<input type="hidden" name="page" value="wp_tmq_mail_queue-tab-sources" />';
		// Carry the active group filter through the bulk POST so a "Disable
		// selected" inside a filtered view returns the user to the same
		// filter.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only narrowing carried verbatim.
		$active_group = isset( $_REQUEST['group_filter'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['group_filter'] ) ) : '';
		if ( '' !== $active_group ) {
			echo '<input type="hidden" name="group_filter" value="' . esc_attr( $active_group ) . '" />';
		}
		$table->display();
		echo '</form>';
	}

	/**
	 * Render the per-group enable / disable toolbar above the table.
	 */
	private static function renderGroupToolbar(): void {
		$groups = SourcesRepository::distinctGroups();
		if ( empty( $groups ) ) {
			return;
		}

		echo '<form method="post" class="tmq-inline-form" style="margin-bottom:1em;">';
		wp_nonce_field( 'wp_tmq_sources_group', 'wp_tmq_sources_group_nonce' );
		echo '<input type="hidden" name="page" value="wp_tmq_mail_queue-tab-sources" />';
		echo '<label>';
		echo esc_html__( 'Toggle every source in group', 'total-mail-queue' ) . ' ';
		echo '<select name="group_label">';
		foreach ( $groups as $group ) {
			echo '<option value="' . esc_attr( $group ) . '">' . esc_html( KnownSources::translateRawGroup( $group ) ) . '</option>';
		}
		echo '</select>';
		echo '</label> ';
		echo '<button type="submit" name="wp_tmq_sources_group_action" value="enable" class="button">' . esc_html__( 'Enable group', 'total-mail-queue' ) . '</button> ';
		echo '<button type="submit" name="wp_tmq_sources_group_action" value="disable" class="button">' . esc_html__( 'Disable group', 'total-mail-queue' ) . '</button>';
		echo '</form>';
	}
}
