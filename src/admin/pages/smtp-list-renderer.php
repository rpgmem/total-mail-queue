<?php
/**
 * Listing renderer for the SMTP Accounts admin tab.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Admin\Pages;

/**
 * Renders the SMTP-account index: the action toolbar (Add / Reset Counters)
 * plus the read-only listing table. Pulled out of {@see SmtpPage} so the row
 * cell formatting and the index header live next to each other.
 */
final class SmtpListRenderer {

	/**
	 * Render the SMTP-account list.
	 *
	 * @param string $smtp_table Fully prefixed SMTP table name.
	 */
	public static function render( string $smtp_table ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$accounts = $wpdb->get_results( "SELECT * FROM `$smtp_table` ORDER BY `priority` ASC, `name` ASC", ARRAY_A );

		echo '<div class="tmq-box">';
		echo '<h3>' . esc_html__( 'SMTP Accounts', 'total-mail-queue' ) . '</h3>';
		echo '<p>';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=wp_tmq_mail_queue-tab-smtp&smtp-action=add' ) ) . '" class="button button-primary">' . esc_html__( 'Add SMTP Account', 'total-mail-queue' ) . '</a> ';

		if ( ! empty( $accounts ) ) {
			echo '<form method="post" class="tmq-inline-form">';
			wp_nonce_field( 'wp_tmq_smtp_reset_counters', 'wp_tmq_smtp_reset_nonce' );
			echo '<input type="submit" name="wp_tmq_smtp_reset_counters" class="button" value="' . esc_attr__( 'Reset Counters', 'total-mail-queue' ) . '" onclick="return confirm(\'' . esc_js( __( 'Are you sure you want to reset all sending counters to zero?', 'total-mail-queue' ) ) . '\');" />';
			echo '</form>';
		}

		echo '</p>';

		if ( empty( $accounts ) ) {
			echo '<p>' . esc_html__( 'No SMTP accounts configured yet.', 'total-mail-queue' ) . '</p>';
			echo '</div>';
			return;
		}

		echo '<table class="widefat striped">';
		echo '<thead>';
		echo '<tr>';
		echo '<th>' . esc_html__( 'ID', 'total-mail-queue' ) . '</th>';
		echo '<th>' . esc_html__( 'Name', 'total-mail-queue' ) . '</th>';
		echo '<th>' . esc_html__( 'Host:Port', 'total-mail-queue' ) . '</th>';
		echo '<th>' . esc_html__( 'From', 'total-mail-queue' ) . '</th>';
		echo '<th>' . esc_html__( 'Priority', 'total-mail-queue' ) . '</th>';
		echo '<th>' . esc_html__( 'Daily Limit', 'total-mail-queue' ) . '</th>';
		echo '<th>' . esc_html__( 'Monthly Limit', 'total-mail-queue' ) . '</th>';
		echo '<th>' . esc_html__( 'Interval', 'total-mail-queue' ) . '</th>';
		echo '<th>' . esc_html__( 'Per Cycle', 'total-mail-queue' ) . '</th>';
		echo '<th>' . esc_html__( 'Enabled', 'total-mail-queue' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'total-mail-queue' ) . '</th>';
		echo '</tr>';
		echo '</thead>';
		echo '<tbody>';

		foreach ( $accounts as $acct ) {
			self::renderRow( $acct );
		}

		echo '</tbody>';
		echo '</table>';
		echo '</div>';
	}

	/**
	 * Render a single row of the listing.
	 *
	 * @param array<string,mixed> $acct Row from the SMTP table.
	 */
	private static function renderRow( array $acct ): void {
		$daily_display  = esc_html( (string) $acct['daily_sent'] ) . ' / ';
		$daily_display .= 0 === intval( $acct['daily_limit'] ) ? esc_html__( 'unlimited', 'total-mail-queue' ) : esc_html( (string) $acct['daily_limit'] );

		$monthly_display  = esc_html( (string) $acct['monthly_sent'] ) . ' / ';
		$monthly_display .= 0 === intval( $acct['monthly_limit'] ) ? esc_html__( 'unlimited', 'total-mail-queue' ) : esc_html( (string) $acct['monthly_limit'] );

		$from_display = esc_html( (string) $acct['from_email'] );
		if ( ! empty( $acct['from_name'] ) ) {
			$from_display = esc_html( (string) $acct['from_name'] ) . ' &lt;' . $from_display . '&gt;';
		}

		$edit_url   = admin_url( 'admin.php?page=wp_tmq_mail_queue-tab-smtp&smtp-action=edit&smtp-id=' . intval( $acct['id'] ) );
		$delete_url = wp_nonce_url(
			admin_url( 'admin.php?page=wp_tmq_mail_queue-tab-smtp&smtp-action=delete&smtp-id=' . intval( $acct['id'] ) ),
			'wp_tmq_smtp_delete_' . intval( $acct['id'] )
		);

		$interval_display = 0 === intval( $acct['send_interval'] ) ? esc_html__( 'global', 'total-mail-queue' ) : esc_html( (string) $acct['send_interval'] ) . ' min';
		$bulk_display     = 0 === intval( $acct['send_bulk'] ) ? esc_html__( 'global', 'total-mail-queue' ) : esc_html( (string) $acct['send_bulk'] );

		echo '<tr>';
		echo '<td>#' . esc_html( (string) $acct['id'] ) . '</td>';
		echo '<td>' . esc_html( (string) $acct['name'] ) . '</td>';
		echo '<td>' . esc_html( (string) $acct['host'] ) . ':' . esc_html( (string) $acct['port'] ) . '</td>';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $from_display is pre-escaped with esc_html().
		echo '<td>' . $from_display . '</td>';
		echo '<td>' . esc_html( (string) $acct['priority'] ) . '</td>';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $daily_display is pre-escaped with esc_html().
		echo '<td>' . $daily_display . '</td>';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $monthly_display is pre-escaped with esc_html().
		echo '<td>' . $monthly_display . '</td>';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $interval_display is pre-escaped with esc_html().
		echo '<td>' . $interval_display . '</td>';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $bulk_display is pre-escaped with esc_html().
		echo '<td>' . $bulk_display . '</td>';
		echo '<td>' . ( intval( $acct['enabled'] ) ? '<span class="tmq-ok">' . esc_html__( 'Yes', 'total-mail-queue' ) . '</span>' : esc_html__( 'No', 'total-mail-queue' ) ) . '</td>';
		echo '<td>';
		echo '<a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'total-mail-queue' ) . '</a> | ';
		echo '<a href="' . esc_url( $delete_url ) . '" onclick="return confirm(\'' . esc_js( __( 'Are you sure you want to delete this SMTP account?', 'total-mail-queue' ) ) . '\');">' . esc_html__( 'Delete', 'total-mail-queue' ) . '</a>';
		echo '</td>';
		echo '</tr>';
	}
}
