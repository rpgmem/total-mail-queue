<?php
/**
 * Admin notices for last-error visibility and incompatible-plugin warnings.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Admin;

use TotalMailQueue\Database\Schema;
use TotalMailQueue\Settings\Options;

/**
 * Renders the global admin notices the plugin emits.
 *
 * Two notices are produced:
 *
 * - When the most recent log row has `status = error`, all manage_options users
 *   see a high-visibility notice with the failure reason; non-admins capable of
 *   editing posts see a softer "contact your administrator" variant.
 * - On the plugin's own settings screen, warn when known wp_mail-bypassing
 *   plugins (currently MailPoet) are active.
 */
final class Notices {

	/**
	 * Hook the renderer onto admin_notices.
	 */
	public static function register(): void {
		add_action( 'admin_notices', array( self::class, 'render' ) );
	}

	/**
	 * `admin_notices` callback.
	 */
	public static function render(): void {
		$options = Options::get();
		if ( ! in_array( (string) $options['enabled'], array( '1', '2' ), true ) ) {
			return;
		}

		global $wpdb;
		$table = Schema::queueTable();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$last_mail = $wpdb->get_row( "SELECT * FROM `$table` WHERE `status` != 'queue' AND `status` != 'high' ORDER BY `id` DESC", 'ARRAY_A' );

		if ( $last_mail && 'error' === $last_mail['status'] ) {
			self::renderLastErrorNotice( $last_mail );
		}

		self::maybeRenderConflictNotice();
	}

	/**
	 * Render the appropriate "last email failed" notice for the current user.
	 *
	 * @param array<string,mixed> $last_mail The most recent non-queued log row.
	 */
	private static function renderLastErrorNotice( array $last_mail ): void {
		if ( current_user_can( 'manage_options' ) ) {
			$notice  = '<div class="notice notice-error is-dismissible">';
			$notice .= '<h1>' . esc_html__( 'Attention: Your website has problems sending e-mails', 'total-mail-queue' ) . '</h1>';
			/* translators: %1$s: opening italic tag, %2$s: closing italic tag, %3$s: opening Mail Log link tag, %4$s: closing link tag */
			$notice .= '<p>' . sprintf( __( 'This is an important message from your %1$sTotal Mail Queue%2$s plugin. Please take a look at your %3$sMail Log%4$s. The last email(s) couldn\'t be sent properly.', 'total-mail-queue' ), '<i>', '</i>', '<a href="admin.php?page=wp_tmq_mail_queue-tab-log">', '</a>' ) . '</p>';
			/* translators: %s: error message */
			$notice .= '<p>' . sprintf( __( 'Last error message was: %s', 'total-mail-queue' ), '<b>' . esc_html( (string) $last_mail['info'] ) . '</b>' ) . '</p>';
			$notice .= '</div>';
			echo wp_kses_post( $notice );
			return;
		}

		if ( current_user_can( 'edit_posts' ) ) {
			$notice  = '<div class="notice notice-error is-dismissible">';
			$notice .= '<h1>' . esc_html__( 'Attention: Your website has problems sending e-mails', 'total-mail-queue' ) . '</h1>';
			$notice .= '<p>' . esc_html__( 'Please contact your Administrator. It seems that WordPress is not able to send emails.', 'total-mail-queue' ) . '</p>';
			/* translators: %s: error message */
			$notice .= '<p>' . sprintf( __( 'Last error message: %s', 'total-mail-queue' ), '<b>' . esc_html( (string) $last_mail['info'] ) . '</b>' ) . '</p>';
			$notice .= '</div>';
			echo wp_kses_post( $notice );
		}
	}

	/**
	 * On the plugin's main settings page, warn when known wp_mail-bypassing
	 * plugins (currently MailPoet) are active.
	 */
	private static function maybeRenderConflictNotice(): void {
		$current_screen = get_current_screen();
		if ( ! $current_screen || 'toplevel_page_wp_tmq_mail_queue' !== $current_screen->base ) {
			return;
		}

		$bypassing = array(
			'mailpoet/mailpoet.php' => 'MailPoet',
		);
		$active    = array();
		foreach ( array_keys( $bypassing ) as $plugin ) {
			if ( is_plugin_active( $plugin ) ) {
				$active[] = $plugin;
			}
		}
		if ( ! $active ) {
			return;
		}

		$notice  = '<div class="notice notice-warning is-dismissible">';
		$notice .= '<p>';
		$notice .= '<strong>' . esc_html__( 'Please note:', 'total-mail-queue' ) . '</strong>';
		$notice .= '<br />' . wp_kses_post( __( 'This plugin is not supported when using in combination with plugins that do not use the standard <i>wp_mail()</i> function.', 'total-mail-queue' ) );
		$notice .= '</p>';
		$notice .= '<p>';
		$notice .= wp_kses_post( __( 'It seems you are using the following plugin(s) that do not use <i>wp_mail()</i>:', 'total-mail-queue' ) );
		$notice .= '<br />' . implode(
			', ',
			array_map(
				static function ( $plugin ) use ( $bypassing ) {
					return esc_html( $bypassing[ $plugin ] );
				},
				$active
			)
		);
		$notice .= '</p>';
		$notice .= '<p><a href="' . esc_url( get_admin_url( null, 'admin.php?page=wp_tmq_mail_queue-tab-faq' ) ) . '">' . esc_html__( 'More information', 'total-mail-queue' ) . '</a></p>';
		$notice .= '</div>';
		echo wp_kses_post( $notice );
	}
}
