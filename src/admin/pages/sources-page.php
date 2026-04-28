<?php
/**
 * Sources admin tab — list / toggle / bulk-toggle / edit / reset the
 * source catalog.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Admin\Pages;

use TotalMailQueue\Settings\Options;

/**
 * Router for the "Sources" tab.
 *
 * Invoked by {@see \TotalMailQueue\Admin\PluginPage::render()} once the
 * page slug matches `wp_tmq_mail_queue-tab-sources`. Side effects (per-row
 * toggle, bulk toggle, group toggle, edit save, reset) are owned by
 * {@see SourcesActions}; the inline edit form by {@see SourcesEditRenderer};
 * the catalog list by {@see SourcesListRenderer}. This class only does the
 * action-state routing between them and renders the page-level chrome
 * (title, mode notice, description).
 */
final class SourcesPage {

	/**
	 * Entry point — invoked with `manage_options` already verified.
	 */
	public static function render(): void {
		SourcesActions::handle();

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
			SourcesEditRenderer::render( $edit_id );
			echo '</div>';
			return;
		}

		SourcesListRenderer::render();

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
}
