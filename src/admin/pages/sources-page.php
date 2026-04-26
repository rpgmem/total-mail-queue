<?php
/**
 * Sources admin tab — list / toggle / bulk-toggle the source catalog.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Admin\Pages;

use TotalMailQueue\Admin\Tables\SourcesTable;
use TotalMailQueue\Sources\Repository as SourcesRepository;

/**
 * Renderer for the "Sources" tab.
 *
 * Invoked by {@see \TotalMailQueue\Admin\PluginPage::render()} once the
 * page slug matches `wp_tmq_mail_queue-tab-sources`. Owns:
 *
 * - the per-row Enable/Disable links (GET, nonce-protected);
 * - the bulk Enable/Disable selected dropdown (POST via WP_List_Table);
 * - the "enable / disable all in group" buttons rendered above the table.
 *
 * Enforcement of the `enabled` flag at send time lives in S4
 * (Queue\MailInterceptor) — this tab only edits the catalog.
 */
final class SourcesPage {

	/**
	 * Entry point — invoked with `manage_options` already verified.
	 */
	public static function render(): void {
		self::handlePost();

		$table = new SourcesTable();
		$table->prepare_items();

		echo '<div class="tmq-box">';
		echo '<h2>' . esc_html__( 'Sources', 'total-mail-queue' ) . '</h2>';
		echo '<p class="description">';
		echo esc_html__( 'Every email passing through wp_mail() is tagged with a source — a stable identifier (key) plus a human-readable label and group. New sources are auto-detected and registered with delivery enabled. Disabling a source stores any further messages from it with the "Blocked by source" status and skips the actual send. System sources marked "always on" cannot be disabled (they protect the plugin\'s own monitoring email).', 'total-mail-queue' );
		echo '</p>';

		self::renderGroupToolbar();

		echo '<form method="post">';
		wp_nonce_field( 'wp_tmq_sources_bulk', 'wp_tmq_sources_nonce' );
		echo '<input type="hidden" name="page" value="wp_tmq_mail_queue-tab-sources" />';
		$table->display();
		echo '</form>';

		echo '</div>';
	}

	/**
	 * Render the per-group enable / disable toolbar above the table.
	 */
	private static function renderGroupToolbar(): void {
		$groups = self::distinctGroups();
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
			echo '<option value="' . esc_attr( $group ) . '">' . esc_html( $group ) . '</option>';
		}
		echo '</select>';
		echo '</label> ';
		echo '<button type="submit" name="wp_tmq_sources_group_action" value="enable" class="button">' . esc_html__( 'Enable group', 'total-mail-queue' ) . '</button> ';
		echo '<button type="submit" name="wp_tmq_sources_group_action" value="disable" class="button">' . esc_html__( 'Disable group', 'total-mail-queue' ) . '</button>';
		echo '</form>';
	}

	/**
	 * Process POST/GET handlers for the page (per-row toggle, bulk toggle,
	 * group toggle). Echoes admin notices for the user-visible outcome.
	 */
	private static function handlePost(): void {
		// Per-row enable/disable via GET link.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified just below.
		if ( isset( $_GET['source-action'], $_GET['source-id'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- same.
			$source_id = (int) $_GET['source-id'];
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- same.
			$action = sanitize_key( wp_unslash( $_GET['source-action'] ) );
			if ( $source_id > 0 && in_array( $action, array( 'enable', 'disable' ), true ) ) {
				if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'wp_tmq_source_toggle_' . $source_id ) ) {
					wp_die( esc_html__( 'Security check failed!', 'total-mail-queue' ) );
				}
				SourcesRepository::setEnabled( $source_id, 'enable' === $action );
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Source updated.', 'total-mail-queue' ) . '</p></div>';
			}
		}

		// Bulk action via the WP_List_Table dropdown.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified just below.
		$bulk_action = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';
		if ( '-1' === $bulk_action || '' === $bulk_action ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- same.
			$bulk_action = isset( $_POST['action2'] ) ? sanitize_key( wp_unslash( $_POST['action2'] ) ) : '';
		}
		if ( in_array( $bulk_action, array( 'enable', 'disable' ), true ) ) {
			if ( ! isset( $_POST['wp_tmq_sources_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['wp_tmq_sources_nonce'] ) ), 'wp_tmq_sources_bulk' ) ) {
				wp_die( esc_html__( 'Security check failed!', 'total-mail-queue' ) );
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above.
			$ids     = isset( $_POST['id'] ) ? wp_parse_id_list( wp_unslash( $_POST['id'] ) ) : array();
			$flag    = 'enable' === $bulk_action;
			$updated = 0;
			foreach ( $ids as $id ) {
				if ( $id <= 0 ) {
					continue;
				}
				SourcesRepository::setEnabled( (int) $id, $flag );
				++$updated;
			}
			if ( $updated > 0 ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(
					sprintf(
						/* translators: %d: number of source rows updated */
						_n( '%d source updated.', '%d sources updated.', $updated, 'total-mail-queue' ),
						$updated
					)
				) . '</p></div>';
			}
		}

		// Group enable/disable via the toolbar above the table.
		if ( isset( $_POST['wp_tmq_sources_group_action'] ) ) {
			if ( ! isset( $_POST['wp_tmq_sources_group_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['wp_tmq_sources_group_nonce'] ) ), 'wp_tmq_sources_group' ) ) {
				wp_die( esc_html__( 'Security check failed!', 'total-mail-queue' ) );
			}
			$group  = isset( $_POST['group_label'] ) ? sanitize_text_field( wp_unslash( $_POST['group_label'] ) ) : '';
			$action = sanitize_key( wp_unslash( $_POST['wp_tmq_sources_group_action'] ) );
			if ( '' !== $group && in_array( $action, array( 'enable', 'disable' ), true ) ) {
				$updated = SourcesRepository::setEnabledByGroup( $group, 'enable' === $action );
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(
					sprintf(
						/* translators: %1$d: number of rows, %2$s: group label */
						_n( '%1$d source in "%2$s" updated.', '%1$d sources in "%2$s" updated.', $updated, 'total-mail-queue' ),
						$updated,
						$group
					)
				) . '</p></div>';
			}
		}
	}

	/**
	 * Distinct, alphabetically-ordered list of every group_label currently
	 * present in the catalog (used to populate the toolbar select).
	 *
	 * @return array<int,string>
	 */
	private static function distinctGroups(): array {
		$groups = array();
		foreach ( SourcesRepository::all() as $row ) {
			$group = (string) ( $row['group_label'] ?? '' );
			if ( '' !== $group && ! in_array( $group, $groups, true ) ) {
				$groups[] = $group;
			}
		}
		sort( $groups );
		return $groups;
	}
}
