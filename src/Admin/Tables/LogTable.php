<?php
/**
 * Admin list table powering the Log and Retention tabs.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Admin\Tables;

use TotalMailQueue\Database\Schema;
use TotalMailQueue\Support\Serializer;
use WP_List_Table;

if ( ! class_exists( WP_List_Table::class ) ) {
	require_once ABSPATH . 'wp-admin/includes/screen.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * `WP_List_Table` subclass that powers both the Log and the Retention tabs.
 *
 * Switches its row source between the queued/high-priority rows and the rest
 * of the log based on the active admin page (`wp_tmq_mail_queue-tab-log` vs
 * `wp_tmq_mail_queue-tab-queue`), and exposes the bulk actions delete /
 * resend / force_resend.
 */
final class LogTable extends WP_List_Table {

	/**
	 * Build the WHERE clause used by the log queries.
	 *
	 * @param string $status_filter Optional `sent`/`error`/`alert` filter.
	 */
	protected function get_log_where( string $status_filter = '' ): string {
		global $wpdb;
		if ( $status_filter && in_array( $status_filter, array( 'sent', 'error', 'alert' ), true ) ) {
			return $wpdb->prepare( '`status` = %s', $status_filter );
		}
		return "`status` != 'queue' AND `status` != 'high'";
	}

	/**
	 * Count rows matching the log filter.
	 *
	 * @param string $status_filter Status filter.
	 */
	protected function get_log_count( string $status_filter = '' ): int {
		global $wpdb;
		$table = Schema::queueTable();
		$where = $this->get_log_where( $status_filter );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$table` WHERE $where" );
	}

	/**
	 * Fetch a page of log rows.
	 *
	 * @param string $status_filter Status filter.
	 * @param int    $per_page      Page size.
	 * @param int    $offset        Row offset.
	 * @return array<int,array<string,mixed>>
	 */
	protected function get_log( string $status_filter = '', int $per_page = 50, int $offset = 0 ): array {
		global $wpdb;
		$table = Schema::queueTable();
		$where = $this->get_log_where( $status_filter );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `$table` WHERE $where ORDER BY `timestamp` DESC LIMIT %d OFFSET %d", $per_page, $offset ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Count pending queue rows.
	 */
	protected function get_queue_count(): int {
		global $wpdb;
		$table = Schema::queueTable();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$table` WHERE `status` = 'queue' OR `status` = 'high'" );
	}

	/**
	 * Fetch a page of queue rows.
	 *
	 * @param int $per_page Page size.
	 * @param int $offset   Row offset.
	 * @return array<int,array<string,mixed>>
	 */
	protected function get_queue( int $per_page = 50, int $offset = 0 ): array {
		global $wpdb;
		$table = Schema::queueTable();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `$table` WHERE `status` = 'queue' OR `status` = 'high' ORDER BY `status` ASC, `retry_count` ASC, `id` ASC LIMIT %d OFFSET %d", $per_page, $offset ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Define the visible columns. SMTP column appears only on the log tab.
	 *
	 * @return array<string,string>
	 */
	public function get_columns() {
		$columns = array(
			'cb'              => '<label><span class="screen-reader-text">' . esc_html__( 'Select all', 'total-mail-queue' ) . '</span><input class="tmq-select-all" type="checkbox"></label>',
			'timestamp'       => __( 'Time', 'total-mail-queue' ),
			'status'          => __( 'Status', 'total-mail-queue' ),
			'smtp_account_id' => __( 'SMTP', 'total-mail-queue' ),
			'info'            => __( 'Info', 'total-mail-queue' ),
			'recipient'       => __( 'Recipient', 'total-mail-queue' ),
			'subject'         => __( 'Subject', 'total-mail-queue' ),
			'message'         => __( 'Message', 'total-mail-queue' ),
			'headers'         => __( 'Headers', 'total-mail-queue' ),
			'attachments'     => __( 'Attachments', 'total-mail-queue' ),
		);
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- admin page routing, not form processing
		$type = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
		if ( 'wp_tmq_mail_queue-tab-log' !== $type ) {
			unset( $columns['smtp_account_id'] );
		}
		return $columns;
	}

	/**
	 * Render the status filter on top of the log tab.
	 *
	 * @param string $which `top` or `bottom`.
	 */
	protected function extra_tablenav( $which ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- admin page routing, not form processing
		$type = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
		if ( 'wp_tmq_mail_queue-tab-log' !== $type || 'top' !== $which ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- filter parameter, not destructive action
		$current  = isset( $_REQUEST['status_filter'] ) ? sanitize_key( $_REQUEST['status_filter'] ) : '';
		$statuses = array(
			''      => __( 'All statuses', 'total-mail-queue' ),
			'sent'  => __( 'Sent', 'total-mail-queue' ),
			'error' => __( 'Error', 'total-mail-queue' ),
			'alert' => __( 'Alert', 'total-mail-queue' ),
		);
		echo '<div class="alignleft actions">';
		echo '<select name="status_filter">';
		foreach ( $statuses as $value => $label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $value ),
				selected( $current, $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
		submit_button( __( 'Filter', 'total-mail-queue' ), '', 'filter_action', false );
		echo '</div>';
	}

	/**
	 * Populate {@see $items} based on the active tab.
	 */
	public function prepare_items() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- admin page routing, not form processing
		$type                  = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
		$columns               = $this->get_columns();
		$hidden                = array();
		$sortable              = array();
		$this->_column_headers = array( $columns, $hidden, $sortable );
		$this->process_bulk_action();

		$per_page    = 50;
		$offset      = ( $this->get_pagenum() - 1 ) * $per_page;
		$total_items = 0;
		$data        = array();

		if ( 'wp_tmq_mail_queue-tab-log' === $type ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- filter parameter, not destructive action
			$status_filter = isset( $_REQUEST['status_filter'] ) ? sanitize_key( $_REQUEST['status_filter'] ) : '';
			$total_items   = $this->get_log_count( $status_filter );
			$data          = $this->get_log( $status_filter, $per_page, $offset );
		} elseif ( 'wp_tmq_mail_queue-tab-queue' === $type ) {
			$total_items = $this->get_queue_count();
			$data        = $this->get_queue( $per_page, $offset );
		}

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
			)
		);
		$this->items = $data;
	}

	/**
	 * Default column renderer.
	 *
	 * @param array<string,mixed> $item        Row data.
	 * @param string              $column_name Column id.
	 * @return string Rendered HTML for the cell.
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'timestamp':
			case 'subject':
				return esc_html( Serializer::decode( $item[ $column_name ] ) );
			case 'info':
				return self::renderInfoColumn( $item );
			case 'recipient':
			case 'headers':
				$decoded = Serializer::decode( $item[ $column_name ] );
				return is_array( $decoded ) ? esc_html( implode( ',', $decoded ) ) : esc_html( $decoded );
			case 'attachments':
				$decoded = Serializer::decode( $item[ $column_name ] );
				if ( is_array( $decoded ) ) {
					$names = array_map( 'basename', $decoded );
					return esc_html( implode( '<br />', $names ) );
				}
				return esc_html( basename( $decoded ) );
			case 'status':
				return self::renderStatusColumn( $item );
			case 'smtp_account_id':
				return self::renderSmtpAccountColumn( $item );
			case 'message':
				return self::renderMessageColumn( $item );
			default:
				return esc_html( (string) ( $item[ $column_name ] ?? '' ) );
		}
	}

	/**
	 * "Info" cell with retry count + message.
	 *
	 * @param array<string,mixed> $item
	 */
	private static function renderInfoColumn( array $item ): string {
		$info        = isset( $item['info'] ) && $item['info'] ? $item['info'] : '';
		$retry_count = isset( $item['retry_count'] ) ? intval( $item['retry_count'] ) : 0;
		$parts       = array();
		if ( $retry_count > 0 ) {
			/* translators: %d: number of retry attempts */
			$parts[] = '<strong>' . sprintf( esc_html__( 'Attempt #%d', 'total-mail-queue' ), $retry_count + 1 ) . '</strong>';
		}
		if ( $info ) {
			$parts[] = esc_html( $info );
		}
		return implode( '<br>', $parts );
	}

	/**
	 * "Status" cell with translated label.
	 *
	 * @param array<string,mixed> $item
	 */
	private static function renderStatusColumn( array $item ): string {
		$labels = array(
			'sent'  => __( 'Sent', 'total-mail-queue' ),
			'error' => __( 'Error', 'total-mail-queue' ),
			'alert' => __( 'Alert', 'total-mail-queue' ),
			'queue' => __( 'Queue', 'total-mail-queue' ),
			'high'  => __( 'High', 'total-mail-queue' ),
		);
		$raw    = $item['status'];
		$label  = $labels[ $raw ] ?? esc_html( $raw );
		return '<span class="tmq-status tmq-status-' . sanitize_title( $raw ) . '">' . esc_html( $label ) . '</span>';
	}

	/**
	 * "SMTP" cell — caches account names per request.
	 *
	 * @param array<string,mixed> $item
	 */
	private static function renderSmtpAccountColumn( array $item ): string {
		$acct_id = intval( $item['smtp_account_id'] ?? 0 );
		if ( 0 === $acct_id ) {
			return '<span class="description">—</span>';
		}
		static $smtp_names = null;
		if ( null === $smtp_names ) {
			global $wpdb;
			$smtp_table = Schema::smtpTable();
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows       = $wpdb->get_results( "SELECT `id`, `name` FROM `$smtp_table`", ARRAY_A );
			$smtp_names = array();
			foreach ( (array) $rows as $r ) {
				$smtp_names[ intval( $r['id'] ) ] = $r['name'];
			}
		}
		$name = $smtp_names[ $acct_id ] ?? __( 'Deleted', 'total-mail-queue' );
		return '<span title="#' . esc_attr( $acct_id ) . '">#' . esc_html( $acct_id ) . ' ' . esc_html( $name ) . '</span>';
	}

	/**
	 * "Message" cell — collapsible preview, body lazy-loaded via REST.
	 *
	 * @param array<string,mixed> $item
	 */
	private static function renderMessageColumn( array $item ): string {
		$message = $item['message'];
		if ( ! $message ) {
			return '<em>' . esc_html__( 'Empty', 'total-mail-queue' ) . '</em>';
		}
		$message_len = strlen( $message );
		$out         = '<details>';
		/* translators: %s: message size in bytes */
		$out .= '<summary class="tmq-view-source" data-tmq-list-message-toggle="' . esc_attr( $item['id'] ) . '">' . wp_kses_post( sprintf( __( 'View message %s', 'total-mail-queue' ), '<i>(' . esc_html( (string) $message_len ) . ' bytes)</i>' ) ) . '</summary>';
		$out .= '<div class="tmq-email-source" data-tmq-list-message-content>' . esc_html__( 'Loading...', 'total-mail-queue' ) . '</div>';
		$out .= '</details>';
		return $out;
	}

	/**
	 * Checkbox column.
	 *
	 * @param array<string,mixed> $item Row data.
	 */
	protected function column_cb( $item ): string {
		return '<input type="checkbox" name="id[]" value="' . esc_attr( $item['id'] ) . '" />';
	}

	/**
	 * Bulk actions vary between Retention (delete only) and Log tabs.
	 *
	 * @return array<string,string>
	 */
	public function get_bulk_actions() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- admin page routing, not form processing
		if ( isset( $_GET['page'] ) && 'wp_tmq_mail_queue-tab-queue' === sanitize_key( $_GET['page'] ) ) {
			return array( 'delete' => __( 'Delete', 'total-mail-queue' ) );
		}
		return array(
			'delete'       => __( 'Delete', 'total-mail-queue' ),
			'resend'       => __( 'Resend', 'total-mail-queue' ),
			'force_resend' => __( 'Force Resend (ignore retry limit)', 'total-mail-queue' ),
		);
	}

	/**
	 * Dispatch the chosen bulk action.
	 */
	public function process_bulk_action() {
		if ( ! $this->current_action() ) {
			return;
		}

		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_key( $_POST['_wpnonce'] ) : '';
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'bulk-' . $this->_args['plural'] ) ) {
			wp_die( esc_html__( 'Security check failed!', 'total-mail-queue' ) );
		}

		$request_ids = isset( $_REQUEST['id'] ) ? wp_parse_id_list( wp_unslash( $_REQUEST['id'] ) ) : array();
		if ( empty( $request_ids ) ) {
			return;
		}

		switch ( $this->current_action() ) {
			case 'delete':
				$this->bulk_delete( $request_ids );
				break;
			case 'resend':
				$this->bulk_resend( $request_ids );
				break;
			case 'force_resend':
				$this->bulk_force_resend( $request_ids );
				break;
		}
	}

	/**
	 * Delete the supplied row ids.
	 *
	 * @param array<int,int> $ids Row ids.
	 */
	private function bulk_delete( array $ids ): void {
		global $wpdb;
		$table = Schema::queueTable();
		foreach ( $ids as $id ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete( $table, array( 'id' => intval( $id ) ), '%d' );
		}
	}

	/**
	 * Re-queue errored rows.
	 *
	 * @param array<int,int> $ids Row ids.
	 */
	private function bulk_resend( array $ids ): void {
		global $wpdb;
		$table  = Schema::queueTable();
		$resend = 0;
		$errors = 0;
		$skip_sent  = 0;
		$skip_queue = 0;
		foreach ( $ids as $id ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `$table` WHERE `id` = %d", intval( $id ) ) );
			if ( ! $row ) {
				continue;
			}
			if ( 'sent' === $row->status ) {
				++$skip_sent;
				continue;
			}
			if ( in_array( $row->status, array( 'queue', 'high' ), true ) ) {
				++$skip_queue;
				continue;
			}
			if ( ! $row->attachments || '' === $row->attachments ) {
				++$resend;
				$data = array(
					'timestamp'   => current_time( 'mysql', false ),
					'recipient'   => $row->recipient,
					'subject'     => $row->subject,
					'message'     => $row->message,
					'status'      => 'queue',
					'attachments' => '',
					'headers'     => $row->headers,
				);
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->insert( $table, $data );
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->delete( $table, array( 'id' => intval( $id ) ), '%d' );
			} else {
				++$errors;
				echo wp_kses_post( self::attachmentMissingNotice( $row->recipient ) );
			}
		}
		self::renderSkipNotices( $skip_sent, $skip_queue );
		$has_skips = ( $errors > 0 || $skip_sent > 0 || $skip_queue > 0 );
		if ( ! $has_skips && $resend > 0 ) {
			wp_safe_redirect( 'admin.php?page=wp_tmq_mail_queue-tab-queue&resent=' . $resend );
			exit;
		}
		if ( $resend > 0 ) {
			$notice  = '<div class="notice notice-success is-dismissible">';
			/* translators: %1$d: count, %2$s: link open, %3$s: link close */
			$notice .= '<p>' . sprintf( __( '%1$d email(s) have been put again into the %2$sQueue%3$s.', 'total-mail-queue' ), $resend, '<a href="admin.php?page=wp_tmq_mail_queue-tab-queue">', '</a>' ) . '</p>';
			$notice .= '</div>';
			echo wp_kses_post( $notice );
		}
	}

	/**
	 * Re-queue errored rows ignoring the retry limit.
	 *
	 * @param array<int,int> $ids Row ids.
	 */
	private function bulk_force_resend( array $ids ): void {
		global $wpdb;
		$table = Schema::queueTable();
		$count = 0;
		$errors = 0;
		$skip_sent  = 0;
		$skip_queue = 0;
		foreach ( $ids as $id ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `$table` WHERE `id` = %d", intval( $id ) ) );
			if ( ! $row ) {
				continue;
			}
			if ( 'sent' === $row->status ) {
				++$skip_sent;
				continue;
			}
			if ( in_array( $row->status, array( 'queue', 'high' ), true ) ) {
				++$skip_queue;
				continue;
			}
			if ( 'error' !== $row->status ) {
				++$errors;
				continue;
			}
			if ( ! $row->attachments || '' === $row->attachments ) {
				++$count;
				$data = array(
					'timestamp'   => current_time( 'mysql', false ),
					'recipient'   => $row->recipient,
					'subject'     => $row->subject,
					'message'     => $row->message,
					'status'      => 'queue',
					'attachments' => '',
					'headers'     => $row->headers,
					'retry_count' => 0,
					/* translators: %s: original error info */
					'info'        => sprintf( __( 'Force resent — Original: %s', 'total-mail-queue' ), $row->info ),
				);
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->insert( $table, $data );
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->delete( $table, array( 'id' => intval( $id ) ), '%d' );
			} else {
				++$errors;
				echo wp_kses_post( self::attachmentMissingNotice( $row->recipient ) );
			}
		}
		self::renderSkipNotices( $skip_sent, $skip_queue );
		$has_skips = ( $errors > 0 || $skip_sent > 0 || $skip_queue > 0 );
		if ( ! $has_skips && $count > 0 ) {
			wp_safe_redirect( 'admin.php?page=wp_tmq_mail_queue-tab-queue&resent=' . $count );
			exit;
		}
		if ( $count > 0 ) {
			$notice  = '<div class="notice notice-success is-dismissible">';
			/* translators: %1$d: number of emails resent, %2$s: link open, %3$s: link close */
			$notice .= '<p>' . sprintf( __( '%1$d email(s) have been force-resent to the %2$sRetention%3$s queue.', 'total-mail-queue' ), $count, '<a href="admin.php?page=wp_tmq_mail_queue-tab-queue">', '</a>' ) . '</p>';
			$notice .= '</div>';
			echo wp_kses_post( $notice );
		}
	}

	/**
	 * Build the notice shown when a row's attachments are no longer available.
	 *
	 * @param string $recipient Encoded recipient.
	 */
	private static function attachmentMissingNotice( string $recipient ): string {
		$notice  = '<div class="notice notice-error is-dismissible">';
		/* translators: %s: recipient email address */
		$notice .= '<p><b>' . sprintf( __( 'Sorry, your email to %s can\'t be sent again.', 'total-mail-queue' ), esc_html( $recipient ) ) . '</b></p>';
		$notice .= '<p>' . esc_html__( 'The email used to have attachments, which are not available anymore. Only emails without attachments can be resent.', 'total-mail-queue' ) . '</p>';
		$notice .= '</div>';
		return $notice;
	}

	/**
	 * Echo "skipped because already sent / already in queue" warnings when
	 * the user clicked Resend on rows that don't qualify.
	 *
	 * @param int $skip_sent  Count of skipped rows that were already sent.
	 * @param int $skip_queue Count of skipped rows already pending in the queue.
	 */
	private static function renderSkipNotices( int $skip_sent, int $skip_queue ): void {
		if ( $skip_sent > 0 ) {
			$notice  = '<div class="notice notice-warning is-dismissible">';
			/* translators: %d: number of skipped emails */
			$notice .= '<p>' . sprintf( __( '%d email(s) were skipped because they have already been sent successfully. Sent emails cannot be re-queued.', 'total-mail-queue' ), $skip_sent ) . '</p>';
			$notice .= '</div>';
			echo wp_kses_post( $notice );
		}
		if ( $skip_queue > 0 ) {
			$notice  = '<div class="notice notice-warning is-dismissible">';
			/* translators: %d: number of skipped emails */
			$notice .= '<p>' . sprintf( __( '%d email(s) were skipped because they are already in the queue waiting to be sent.', 'total-mail-queue' ), $skip_queue ) . '</p>';
			$notice .= '</div>';
			echo wp_kses_post( $notice );
		}
	}
}
