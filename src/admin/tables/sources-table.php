<?php
/**
 * Admin list table for the message-source catalog.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Admin\Tables;

use TotalMailQueue\Sources\Repository as SourcesRepository;
use WP_List_Table;

if ( ! class_exists( WP_List_Table::class ) ) {
	require_once ABSPATH . 'wp-admin/includes/screen.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * `WP_List_Table` powering the "Sources" admin tab.
 *
 * Read-only data flow — toggling rows on/off is handled by the parent
 * {@see \TotalMailQueue\Admin\Pages\SourcesPage}, not from inside the
 * table itself. This class is intentionally narrow: render columns,
 * declare the bulk actions and let the page handle the side effects.
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
	 * Populate {@see $items} from the repository.
	 */
	public function prepare_items() {
		$columns               = $this->get_columns();
		$hidden                = array();
		$sortable              = array();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		$rows        = SourcesRepository::all();
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
	protected function column_cb( $item ) {
		return '<input type="checkbox" name="id[]" value="' . esc_attr( (string) $item['id'] ) . '" />';
	}

	/**
	 * Default column renderer.
	 *
	 * @param array<string,mixed> $item        Row data.
	 * @param string              $column_name Column id.
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'enabled':
				return self::renderEnabledBadge( (int) $item['enabled'] );
			case 'label':
				$label = (string) ( $item['label'] ?? '' );
				return $label ? esc_html( $label ) : '<em class="description">' . esc_html__( '(no label)', 'total-mail-queue' ) . '</em>';
			case 'source_key':
				return '<code>' . esc_html( (string) $item['source_key'] ) . '</code>';
			case 'group_label':
				$group = (string) ( $item['group_label'] ?? '' );
				return $group ? esc_html( $group ) : '<em class="description">—</em>';
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
	 * Per-row action links — the per-row toggle and the "filter log by
	 * this source" shortcut. System sources (e.g. `total_mail_queue:alert`)
	 * render a non-actionable "system" badge instead of the toggle so the
	 * admin cannot disable the plugin's own monitoring email.
	 *
	 * @param array<string,mixed> $item Row data.
	 */
	private static function renderRowActions( array $item ): string {
		$id  = (int) $item['id'];
		$key = (string) $item['source_key'];

		$log_url    = admin_url( 'admin.php?page=wp_tmq_mail_queue-tab-log&source_filter=' . rawurlencode( $key ) );
		$filter_log = '<a href="' . esc_url( $log_url ) . '">' . esc_html__( 'Filter log', 'total-mail-queue' ) . '</a>';

		if ( SourcesRepository::isSystem( $key ) ) {
			$badge = '<span class="description" title="' . esc_attr__( 'This source is hardcoded as always-enabled and cannot be disabled.', 'total-mail-queue' ) . '">' . esc_html__( 'system (always on)', 'total-mail-queue' ) . '</span>';
			return $badge . ' | ' . $filter_log;
		}

		$enabled = 1 === (int) $item['enabled'];
		$action  = $enabled ? 'disable' : 'enable';
		$label   = $enabled ? __( 'Disable', 'total-mail-queue' ) : __( 'Enable', 'total-mail-queue' );
		$url     = wp_nonce_url(
			admin_url( 'admin.php?page=wp_tmq_mail_queue-tab-sources&source-action=' . $action . '&source-id=' . $id ),
			'wp_tmq_source_toggle_' . $id
		);
		$toggle  = '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';

		return $toggle . ' | ' . $filter_log;
	}
}
