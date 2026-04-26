<?php
/**
 * Sources admin tab — list / toggle / bulk-toggle / edit / reset the
 * source catalog.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Admin\Pages;

use TotalMailQueue\Admin\Tables\SourcesTable;
use TotalMailQueue\Settings\Options;
use TotalMailQueue\Sources\KnownSources;
use TotalMailQueue\Sources\Repository as SourcesRepository;

/**
 * Renderer for the "Sources" tab.
 *
 * Invoked by {@see \TotalMailQueue\Admin\PluginPage::render()} once the
 * page slug matches `wp_tmq_mail_queue-tab-sources`. Owns:
 *
 * - the per-row Enable/Disable links (GET, nonce-protected);
 * - the bulk Enable/Disable selected dropdown (POST via WP_List_Table);
 * - the per-group Enable/Disable toolbar above the table;
 * - the per-row "Edit label/group" inline form + Reset link.
 */
final class SourcesPage {

	/**
	 * Entry point — invoked with `manage_options` already verified.
	 */
	public static function render(): void {
		self::handleRequest();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing only, individual handlers verify their own nonces.
		$action = isset( $_GET['source-action'] ) ? sanitize_key( wp_unslash( $_GET['source-action'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- same.
		$edit_id = isset( $_GET['source-id'] ) ? (int) $_GET['source-id'] : 0;

		echo '<div class="tmq-box">';
		echo '<h2>' . esc_html__( 'Sources', 'total-mail-queue' ) . '</h2>';

		self::renderModeNotice();

		echo '<p class="description">';
		echo esc_html__( 'Every email passing through wp_mail() is tagged with a source — a stable identifier (key) plus a human-readable label and group. New sources are auto-detected and registered with delivery enabled. Disabling a source stores any further messages from it with the "Blocked by source" status and skips the actual send. System sources marked "always on" cannot be disabled (they protect the plugin\'s own monitoring email).', 'total-mail-queue' );
		echo '</p>';

		if ( 'edit' === $action && $edit_id > 0 ) {
			self::renderEditForm( $edit_id );
			echo '</div>';
			return;
		}

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

		echo '</div>';
	}

	/**
	 * Show a warning banner when the plugin's global Operation Mode means
	 * per-source toggles can't actually take effect:
	 *
	 * - Block (`enabled = 2`): every email is retained regardless of
	 *   per-source choice — the toggles just decide which status the row
	 *   ends up under, not whether it ships.
	 * - Disabled (`enabled = 0`): MailInterceptor isn't even hooked, so
	 *   the toggles are inert.
	 */
	private static function renderModeNotice(): void {
		$mode = (string) Options::get()['enabled'];
		if ( '1' === $mode ) {
			return;
		}
		$settings_link_open  = '<a href="' . esc_url( admin_url( 'admin.php?page=wp_tmq_mail_queue' ) ) . '">';
		$settings_link_close = '</a>';

		if ( '2' === $mode ) {
			echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Block mode is active.', 'total-mail-queue' ) . '</strong> ' . wp_kses_post(
				sprintf(
					/* translators: %1$s: opening Settings link tag, %2$s: closing tag */
					__( 'Every outgoing email is being retained regardless of these per-source toggles. Change the Operation Mode in %1$sSettings%2$s to use the catalog below for actual delivery decisions.', 'total-mail-queue' ),
					$settings_link_open,
					$settings_link_close
				)
			) . '</p></div>';
			return;
		}

		echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'The plugin is currently disabled.', 'total-mail-queue' ) . '</strong> ' . wp_kses_post(
			sprintf(
				/* translators: %1$s: opening Settings link tag, %2$s: closing tag */
				__( 'Emails go straight to wp_mail() without interception, so toggling sources here has no effect until you enable the plugin in %1$sSettings%2$s.', 'total-mail-queue' ),
				$settings_link_open,
				$settings_link_close
			)
		) . '</p></div>';
	}

	/**
	 * Render the inline edit form for a single source row.
	 *
	 * @param int $source_id Row id to edit.
	 */
	private static function renderEditForm( int $source_id ): void {
		$row = SourcesRepository::findById( $source_id );
		if ( null === $row ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Source not found.', 'total-mail-queue' ) . '</p></div>';
			return;
		}

		$is_system  = SourcesRepository::isSystem( (string) $row['source_key'] );
		$cancel_url = admin_url( 'admin.php?page=wp_tmq_mail_queue-tab-sources' );

		echo '<h3>' . esc_html(
			sprintf(
				/* translators: %s: source key */
				__( 'Edit source: %s', 'total-mail-queue' ),
				(string) $row['source_key']
			)
		) . '</h3>';
		echo '<p class="description">' . esc_html__( 'Leave a field empty to fall back to the translated default. The override only affects what you see in the admin — the source_key, detection and enforcement stay identical.', 'total-mail-queue' ) . '</p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin.php?page=wp_tmq_mail_queue-tab-sources' ) ) . '">';
		wp_nonce_field( 'wp_tmq_source_edit_' . $source_id, 'wp_tmq_source_edit_nonce' );
		echo '<input type="hidden" name="page" value="wp_tmq_mail_queue-tab-sources" />';
		echo '<input type="hidden" name="source_id" value="' . esc_attr( (string) $source_id ) . '" />';

		echo '<table class="form-table">';

		// Label row.
		echo '<tr><th scope="row"><label for="tmq-source-label">' . esc_html__( 'Label', 'total-mail-queue' ) . '</label></th><td>';
		echo '<input type="text" id="tmq-source-label" name="label_override" class="regular-text" value="' . esc_attr( (string) $row['label_override'] ) . '" maxlength="255" />';
		echo '<p class="description">';
		$default_label = KnownSources::translatedLabel( (string) $row['source_key'] );
		if ( null !== $default_label ) {
			echo esc_html(
				sprintf(
					/* translators: %s: default translated label */
					__( 'Default: %s', 'total-mail-queue' ),
					$default_label
				)
			);
		} elseif ( '' !== (string) $row['label'] ) {
			echo esc_html(
				sprintf(
					/* translators: %s: stored fallback label */
					__( 'Default (stored fallback): %s', 'total-mail-queue' ),
					(string) $row['label']
				)
			);
		} else {
			echo esc_html__( 'No translated default available — fill in a label below.', 'total-mail-queue' );
		}
		echo '</p></td></tr>';

		// Group row.
		echo '<tr><th scope="row"><label for="tmq-source-group">' . esc_html__( 'Group', 'total-mail-queue' ) . '</label></th><td>';
		if ( $is_system ) {
			echo '<input type="text" class="regular-text" value="' . esc_attr( (string) $row['group_label'] ) . '" disabled="disabled" />';
			echo '<p class="description">' . esc_html__( 'System sources keep their built-in group; the group cannot be overridden.', 'total-mail-queue' ) . '</p>';
		} else {
			echo '<input type="text" id="tmq-source-group" name="group_override" class="regular-text" value="' . esc_attr( (string) $row['group_override'] ) . '" maxlength="120" />';
			echo '<p class="description">';
			$default_group = KnownSources::translatedGroup( (string) $row['source_key'] );
			if ( null !== $default_group ) {
				echo esc_html(
					sprintf(
						/* translators: %s: default translated group label */
						__( 'Default: %s', 'total-mail-queue' ),
						$default_group
					)
				);
			} else {
				echo esc_html(
					sprintf(
						/* translators: %s: stored fallback group label */
						__( 'Default (stored fallback): %s', 'total-mail-queue' ),
						(string) $row['group_label']
					)
				);
			}
			echo '</p>';
		}
		echo '</td></tr>';

		echo '</table>';

		echo '<p class="submit">';
		echo '<input type="submit" name="wp_tmq_source_edit_save" class="button button-primary" value="' . esc_attr__( 'Save', 'total-mail-queue' ) . '" /> ';
		echo '<a href="' . esc_url( $cancel_url ) . '" class="button">' . esc_html__( 'Cancel', 'total-mail-queue' ) . '</a>';
		echo '</p>';
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

	/**
	 * Process POST/GET handlers for the page (per-row toggle, bulk toggle,
	 * group toggle, edit save, reset). Echoes admin notices for the
	 * user-visible outcome.
	 */
	private static function handleRequest(): void {
		// Per-row enable / disable via GET link.
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

			if ( $source_id > 0 && 'reset' === $action ) {
				if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'wp_tmq_source_reset_' . $source_id ) ) {
					wp_die( esc_html__( 'Security check failed!', 'total-mail-queue' ) );
				}
				SourcesRepository::updateOverrides( $source_id, '', '' );
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Custom label/group cleared; the translated default is back.', 'total-mail-queue' ) . '</p></div>';
			}
		}

		// Edit form submit.
		if ( isset( $_POST['wp_tmq_source_edit_save'] ) ) {
			$source_id = isset( $_POST['source_id'] ) ? (int) $_POST['source_id'] : 0;
			if ( $source_id <= 0 ) {
				return;
			}
			if ( ! isset( $_POST['wp_tmq_source_edit_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['wp_tmq_source_edit_nonce'] ) ), 'wp_tmq_source_edit_' . $source_id ) ) {
				wp_die( esc_html__( 'Security check failed!', 'total-mail-queue' ) );
			}
			$label = isset( $_POST['label_override'] ) ? sanitize_text_field( wp_unslash( $_POST['label_override'] ) ) : '';
			$group = isset( $_POST['group_override'] ) ? sanitize_text_field( wp_unslash( $_POST['group_override'] ) ) : '';
			SourcesRepository::updateOverrides( $source_id, $label, $group );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Source updated.', 'total-mail-queue' ) . '</p></div>';
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
						KnownSources::translateRawGroup( $group )
					)
				) . '</p></div>';
			}
		}
	}
}
