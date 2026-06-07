<?php
/**
 * Listing renderer for the SMTP Accounts admin tab.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Admin\Pages;

use TotalMailQueue\Smtp\Repository as SmtpRepository;

/**
 * Renders the SMTP-account index: the action toolbar (Add / Reset Counters)
 * plus the read-only listing table. Pulled out of {@see SmtpPage} so the row
 * cell formatting and the index header live next to each other.
 */
final class SmtpListRenderer {

	/**
	 * Render the SMTP-account list.
	 */
	public static function render(): void {
		$accounts = SmtpRepository::all();

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
		echo '<th>' . esc_html__( 'Last Sent', 'total-mail-queue' ) . '</th>';
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

		// Per-cycle cell shows current usage / limit, mirroring the daily and
		// monthly columns so the operator can see how full the active cycle is.
		$cycle_used   = esc_html( (string) intval( $acct['cycle_sent'] ) ) . ' / ';
		$bulk_display = $cycle_used . ( 0 === intval( $acct['send_bulk'] ) ? esc_html__( 'global', 'total-mail-queue' ) : esc_html( (string) $acct['send_bulk'] ) );

		$last_sent_display = self::renderLastSent( (string) $acct['last_sent_at'] );

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
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $last_sent_display is pre-escaped with esc_html().
		echo '<td>' . $last_sent_display . '</td>';
		echo '<td>' . ( intval( $acct['enabled'] ) ? '<span class="tmq-ok">' . esc_html__( 'Yes', 'total-mail-queue' ) . '</span>' : esc_html__( 'No', 'total-mail-queue' ) ) . '</td>';
		echo '<td>';
		echo '<a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'total-mail-queue' ) . '</a> | ';
		echo '<a href="' . esc_url( $delete_url ) . '" onclick="return confirm(\'' . esc_js( __( 'Are you sure you want to delete this SMTP account?', 'total-mail-queue' ) ) . '\');">' . esc_html__( 'Delete', 'total-mail-queue' ) . '</a>';
		echo '</td>';
		echo '</tr>';
	}

	/**
	 * Format the `last_sent_at` column for display: a human-readable "x ago"
	 * relative time with the absolute timestamp in the tooltip, or a muted
	 * "Never" for accounts that have not sent yet (the schema sentinel
	 * `2000-01-01 00:00:00`).
	 *
	 * @param string $last_sent_at Datetime as stored (site-local time).
	 * @return string Pre-escaped HTML for the cell.
	 */
	private static function renderLastSent( string $last_sent_at ): string {
		$timestamp = strtotime( $last_sent_at );
		if ( false === $timestamp || $timestamp <= strtotime( '2000-01-02 00:00:00' ) ) {
			return '<span class="description">' . esc_html__( 'Never', 'total-mail-queue' ) . '</span>';
		}

		/* translators: %s: human-readable time difference, e.g. "5 mins". */
		$relative = sprintf( __( '%s ago', 'total-mail-queue' ), human_time_diff( $timestamp, (int) current_time( 'timestamp' ) ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested

		return '<span title="' . esc_attr( $last_sent_at ) . '">' . esc_html( $relative ) . '</span>';
	}
}
