<?php
/**
 * POST/GET action handlers for the Sources admin tab.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Admin\Pages;

use TotalMailQueue\Sources\CoreTemplates;
use TotalMailQueue\Sources\KnownSources;
use TotalMailQueue\Sources\Repository as SourcesRepository;

/**
 * Handles the side-effecting half of the Sources tab: per-row enable/disable,
 * per-row label/group reset, edit-form save (with optional wp_core template
 * override save), reset-template GET link, bulk enable/disable from the
 * WP_List_Table dropdown, and the per-group enable/disable toolbar.
 *
 * Pulled out of {@see SourcesPage} so the page itself stays a thin router.
 * Each handler verifies its own nonce and emits an admin notice on success.
 */
final class SourcesActions {

	/**
	 * Run every applicable handler for the current request. Notices are echoed
	 * inline (no return value); the orchestrator displays them next to the
	 * page chrome.
	 */
	public static function handle(): void {
		self::handleRowAction();
		self::handleEditSave();
		self::handleTemplateReset();
		self::handleBulkToggle();
		self::handleGroupToggle();
	}

	/**
	 * Per-row enable/disable + reset (overrides clear) GET links.
	 */
	private static function handleRowAction(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified just below.
		if ( ! isset( $_GET['source-action'], $_GET['source-id'] ) ) {
			return;
		}
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

	/**
	 * Edit form submission — saves label/group overrides and, for wp_core
	 * sources, the subject/body/skip_template_wrap overrides.
	 */
	private static function handleEditSave(): void {
		if ( ! isset( $_POST['wp_tmq_source_edit_save'] ) ) {
			return;
		}
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

		// Template override fields (only relevant for wp_core sources).
		$row = SourcesRepository::findById( $source_id );
		if ( null !== $row && CoreTemplates::isCoreTemplate( (string) $row['source_key'] ) ) {
			$subject_override = isset( $_POST['subject_override'] ) ? sanitize_text_field( wp_unslash( $_POST['subject_override'] ) ) : '';
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- preserved as-is for textarea round-trip; rendered with str_replace + tokens, no shell/SQL context
			$body_override = isset( $_POST['body_override'] ) ? wp_kses_post( wp_unslash( (string) $_POST['body_override'] ) ) : '';
			$skip_wrap     = isset( $_POST['skip_template_wrap'] );
			SourcesRepository::updateTemplateOverrides( $source_id, $subject_override, $body_override, $skip_wrap );
		}

		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Source updated.', 'total-mail-queue' ) . '</p></div>';
	}

	/**
	 * Reset template overrides via the GET link inside the edit form.
	 */
	private static function handleTemplateReset(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified just below.
		if ( ! isset( $_GET['source-action'] ) || 'reset_template' !== $_GET['source-action'] || ! isset( $_GET['source-id'] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- same.
		$reset_id = (int) $_GET['source-id'];
		if ( $reset_id > 0 && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'wp_tmq_source_reset_template_' . $reset_id ) ) {
			SourcesRepository::clearTemplateOverrides( $reset_id );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Template overrides reset to WP default.', 'total-mail-queue' ) . '</p></div>';
		}
	}

	/**
	 * Bulk enable/disable from the WP_List_Table top/bottom dropdown.
	 */
	private static function handleBulkToggle(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified just below.
		$bulk_action = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';
		if ( '-1' === $bulk_action || '' === $bulk_action ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- same.
			$bulk_action = isset( $_POST['action2'] ) ? sanitize_key( wp_unslash( $_POST['action2'] ) ) : '';
		}
		if ( ! in_array( $bulk_action, array( 'enable', 'disable' ), true ) ) {
			return;
		}
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

	/**
	 * Per-group enable/disable from the toolbar above the table.
	 */
	private static function handleGroupToggle(): void {
		if ( ! isset( $_POST['wp_tmq_sources_group_action'] ) ) {
			return;
		}
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
