<?php
/**
 * Admin list table for the message-source catalog.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Admin\Tables;

use TotalMailQueue\Sources\KnownSources;
use TotalMailQueue\Sources\Repository as SourcesRepository;
use WP_List_Table;

if ( ! class_exists( WP_List_Table::class ) ) {
	require_once ABSPATH . 'wp-admin/includes/screen.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * `WP_List_Table` powering the "Sources" admin tab.
 *
 * Read-only data flow — POST/GET side effects are owned by the parent
 * {@see \TotalMailQueue\Admin\Pages\SourcesPage}. This class is
 * intentionally narrow: render columns, declare bulk actions and the
 * group-filter dropdown, let the page handle the side effects.
 *
 * Display priority for label and group columns (see
 * {@see KnownSources::displayLabel()} / {@see KnownSources::displayGroup()}):
 * admin override → translated canonical → raw stored value.
 */
final class SourcesTable extends WP_List_Table {

	/**
	 * Define visible columns.
	 *
	 * @return array<string,string>
	 */
	public function get_columns() {
		return array(
			'cb'           => '<label><span class="screen-reader-text">' . esc_html__( 'Select all', 'total-mail-queue' ) . '</span><input class="tmq-select-all" type="checkbox"></label>',
			'enabled'      => __( 'Status', 'total-mail-queue' ),
			'label'        => __( 'Label', 'total-mail-queue' ),
			'source_key'   => __( 'Source key', 'total-mail-queue' ),
			'group_label'  => __( 'Group', 'total-mail-queue' ),
			'total_count'  => __( 'Total emails', 'total-mail-queue' ),
			'last_seen_at' => __( 'Last seen', 'total-mail-queue' ),
			'actions'      => __( 'Actions', 'total-mail-queue' ),
		);
	}

	/**
	 * Bulk actions exposed in the dropdowns above and below the table.
	 *
	 * @return array<string,string>
	 */
	public function get_bulk_actions() {
		return array(
			'enable'  => __( 'Enable', 'total-mail-queue' ),
			'disable' => __( 'Disable', 'total-mail-queue' ),
		);
	}

	/**
	 * Populate {@see $items} from the repository, optionally narrowed by
	 * the `?group_filter=` query param.
	 */
	public function prepare_items(): void {
		$columns               = $this->get_columns();
		$hidden                = array();
		$sortable              = array();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only narrowing, not a destructive action.
		$group_filter = isset( $_REQUEST['group_filter'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['group_filter'] ) ) : '';

		$rows = SourcesRepository::all();
		if ( '' !== $group_filter ) {
			// Match canonical: a row passes if its raw group_label matches
			// OR its admin-set group_override matches. The dropdown uses
			// canonical option values so localised display labels never
			// leak into the WHERE comparison.
			$rows = array_values(
				array_filter(
					$rows,
					static fn ( array $row ): bool => (string) ( $row['group_label'] ?? '' ) === $group_filter
						|| (string) ( $row['group_override'] ?? '' ) === $group_filter
				)
			);
		}

		$this->items = $rows;
		$this->set_pagination_args(
			array(
				'total_items' => count( $rows ),
				'per_page'    => max( count( $rows ), 1 ),
			)
		);
	}

	/**
	 * Render the per-row checkbox.
	 *
	 * @param array<string,mixed> $item Row data.
	 */
	protected function column_cb( $item ): string {
		return '<input type="checkbox" name="id[]" value="' . esc_attr( (string) $item['id'] ) . '" />';
	}

	/**
	 * "Group" filter + status badge above the table.
	 *
	 * @param string $which `top` or `bottom`.
	 */
	protected function extra_tablenav( $which ): void {
		if ( 'top' !== $which ) {
			return;
		}
		$groups = SourcesRepository::distinctGroups();
		if ( empty( $groups ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only narrowing, not a destructive action.
		$current = isset( $_REQUEST['group_filter'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['group_filter'] ) ) : '';

		echo '<div class="alignleft actions">';
		echo '<input type="hidden" name="page" value="wp_tmq_mail_queue-tab-sources" />';
		echo '<select name="group_filter">';
		printf(
			'<option value="">%s</option>',
			esc_html__( 'All groups', 'total-mail-queue' )
		);
		foreach ( $groups as $group ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $group ),
				selected( $current, $group, false ),
				esc_html( KnownSources::translateRawGroup( $group ) )
			);
		}
		echo '</select>';
		submit_button( __( 'Filter', 'total-mail-queue' ), '', 'filter_action', false );
		if ( '' !== $current ) {
			$clear_url = admin_url( 'admin.php?page=wp_tmq_mail_queue-tab-sources' );
			echo ' <a href="' . esc_url( $clear_url ) . '">' . esc_html__( 'Clear group filter', 'total-mail-queue' ) . '</a>';
		}
		echo '</div>';
	}

	/**
	 * Default column renderer.
	 *
	 * @param array<string,mixed> $item        Row data.
	 * @param string              $column_name Column id.
	 */
	public function column_default( $item, $column_name ): string {
		switch ( $column_name ) {
			case 'enabled':
				return self::renderEnabledBadge( (int) $item['enabled'] );
			case 'label':
				return self::renderLabelColumn( $item );
			case 'source_key':
				return '<code>' . esc_html( (string) $item['source_key'] ) . '</code>';
			case 'group_label':
				return self::renderGroupColumn( $item );
			case 'total_count':
				return '<span title="' . esc_attr__( 'Total emails seen since this source was first detected', 'total-mail-queue' ) . '">' . esc_html( number_format_i18n( (int) $item['total_count'] ) ) . '</span>';
			case 'last_seen_at':
				return self::renderLastSeen( (string) $item['last_seen_at'] );
			case 'actions':
				return self::renderRowActions( $item );
			default:
				return esc_html( (string) ( $item[ $column_name ] ?? '' ) );
		}
	}

	/**
	 * Visual on/off badge for the `enabled` column.
	 *
	 * @param int $enabled 1 or 0.
	 */
	private static function renderEnabledBadge( int $enabled ): string {
		if ( 1 === $enabled ) {
			return '<span class="tmq-status tmq-status-sent">' . esc_html__( 'Enabled', 'total-mail-queue' ) . '</span>';
		}
		return '<span class="tmq-status tmq-status-alert">' . esc_html__( 'Disabled', 'total-mail-queue' ) . '</span>';
	}

	/**
	 * Render the Label cell using the priority:
	 * admin override → translated canonical → raw stored. Adds a small
	 * "(custom)" badge when the admin has set an override.
	 *
	 * @param array<string,mixed> $item Row data.
	 */
	private static function renderLabelColumn( array $item ): string {
		$display  = KnownSources::displayLabel( $item );
		$override = isset( $item['label_override'] ) ? (string) $item['label_override'] : '';
		if ( '' === $display ) {
			return '<em class="description">' . esc_html__( '(no label)', 'total-mail-queue' ) . '</em>';
		}
		$out = esc_html( $display );
		if ( '' !== $override ) {
			$out .= ' <span class="description" title="' . esc_attr__( 'Admin-set custom label (overrides the translated default)', 'total-mail-queue' ) . '">' . esc_html__( '(custom)', 'total-mail-queue' ) . '</span>';
		}
		return $out;
	}

	/**
	 * Render the Group cell, same priority as Label. Adds the "(custom)"
	 * badge when an override is set.
	 *
	 * @param array<string,mixed> $item Row data.
	 */
	private static function renderGroupColumn( array $item ): string {
		$display  = KnownSources::displayGroup( $item );
		$override = isset( $item['group_override'] ) ? (string) $item['group_override'] : '';
		if ( '' === $display ) {
			return '<em class="description">—</em>';
		}
		$out = esc_html( $display );
		if ( '' !== $override ) {
			$out .= ' <span class="description" title="' . esc_attr__( 'Admin-set custom group (overrides the translated default)', 'total-mail-queue' ) . '">' . esc_html__( '(custom)', 'total-mail-queue' ) . '</span>';
		}
		return $out;
	}

	/**
	 * `last_seen_at` formatted as "X minutes ago" plus an absolute tooltip.
	 *
	 * @param string $datetime MySQL datetime string.
	 */
	private static function renderLastSeen( string $datetime ): string {
		if ( '' === $datetime || '2000-01-01 00:00:00' === $datetime ) {
			return '<em class="description">' . esc_html__( 'never', 'total-mail-queue' ) . '</em>';
		}
		$ts = strtotime( $datetime );
		if ( false === $ts ) {
			return esc_html( $datetime );
		}
		/* translators: %s: human-readable time difference */
		$relative = sprintf( __( '%s ago', 'total-mail-queue' ), human_time_diff( $ts ) );
		return '<span title="' . esc_attr( $datetime ) . '">' . esc_html( $relative ) . '</span>';
	}

	/**
	 * Per-row action links — Enable/Disable toggle, the Edit-label link,
	 * an optional Reset link (when an override exists), and the
	 * "filter log by this source" shortcut. System sources
	 * (e.g. `total_mail_queue:alert`) render a non-actionable "system"
	 * badge in place of the toggle.
	 *
	 * @param array<string,mixed> $item Row data.
	 */
	private static function renderRowActions( array $item ): string {
		$id  = (int) $item['id'];
		$key = (string) $item['source_key'];

		$actions = array();

		if ( SourcesRepository::isSystem( $key ) ) {
			$actions[] = '<span class="description" title="' . esc_attr__( 'This source is hardcoded as always-enabled and cannot be disabled.', 'total-mail-queue' ) . '">' . esc_html__( 'system (always on)', 'total-mail-queue' ) . '</span>';
		} else {
			$enabled       = 1 === (int) $item['enabled'];
			$toggle_action = $enabled ? 'disable' : 'enable';
			$toggle_label  = $enabled ? __( 'Disable', 'total-mail-queue' ) : __( 'Enable', 'total-mail-queue' );
			$toggle_url    = wp_nonce_url(
				admin_url( 'admin.php?page=wp_tmq_mail_queue-tab-sources&source-action=' . $toggle_action . '&source-id=' . $id ),
				'wp_tmq_source_toggle_' . $id
			);
			$actions[]     = '<a href="' . esc_url( $toggle_url ) . '">' . esc_html( $toggle_label ) . '</a>';
		}

		// Edit label/group.
		$edit_url  = admin_url( 'admin.php?page=wp_tmq_mail_queue-tab-sources&source-action=edit&source-id=' . $id );
		$actions[] = '<a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'total-mail-queue' ) . '</a>';

		// Reset overrides — only when at least one is set.
		$has_label_override = '' !== (string) ( $item['label_override'] ?? '' );
		$has_group_override = '' !== (string) ( $item['group_override'] ?? '' );
		if ( $has_label_override || $has_group_override ) {
			$reset_url = wp_nonce_url(
				admin_url( 'admin.php?page=wp_tmq_mail_queue-tab-sources&source-action=reset&source-id=' . $id ),
				'wp_tmq_source_reset_' . $id
			);
			$actions[] = '<a href="' . esc_url( $reset_url ) . '" title="' . esc_attr__( 'Drop the custom label/group; fall back to the translated default.', 'total-mail-queue' ) . '">' . esc_html__( 'Reset', 'total-mail-queue' ) . '</a>';
		}

		$log_url   = admin_url( 'admin.php?page=wp_tmq_mail_queue-tab-log&source_filter=' . rawurlencode( $key ) );
		$actions[] = '<a href="' . esc_url( $log_url ) . '">' . esc_html__( 'Filter log', 'total-mail-queue' ) . '</a>';

		return implode( ' | ', $actions );
	}
}
