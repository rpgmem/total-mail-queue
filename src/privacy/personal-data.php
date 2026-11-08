<?php
/**
 * Personal data export and erasure for the email log.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Privacy;

use TotalMailQueue\Admin\Access;
use TotalMailQueue\Database\Schema;
use TotalMailQueue\Queue\AttachmentStore;
use TotalMailQueue\Support\Serializer;

/**
 * Plugs the log into WordPress's own privacy tools (Tools → Export Personal
 * Data / Erase Personal Data), so a request made under the LGPD or the GDPR
 * reaches the emails the plugin keeps (#178).
 *
 * The log holds whole emails, so it is personal data of every recipient. A
 * row belongs to a request when one of its recipient addresses is the
 * requester's, compared address by address: a `LIKE` narrows the rows, and
 * the decoded recipient list decides, so `ana@example.org` never matches
 * `joana@example.org`.
 *
 * - Export: date, status, subject and the message as plain text. The body of
 *   an email that carries a one-time account link (a password reset, a new
 *   user's set-password email…) is withheld, the rule the Log already applies
 *   to managers: a link in an export file is a link someone else may read.
 * - Erasure: sent, failed and alert rows are deleted with their stored
 *   attachments. Rows still waiting in the queue are kept until they are
 *   sent, and the request reports them as retained, with the reason.
 */
final class PersonalData {

	/**
	 * Rows read per exporter page, and rows deleted per eraser call.
	 */
	public const PAGE = 50;

	/**
	 * Key under which both callbacks are registered.
	 */
	public const KEY = 'total-mail-queue';

	/**
	 * Statuses of rows that have not been sent yet.
	 */
	private const PENDING = array( 'queue', 'high' );

	/**
	 * Register the exporter and the eraser.
	 *
	 * Both are WordPress's registry filters for its privacy tools; the
	 * default priority is enough, because the order of the plugins in the
	 * list changes nothing.
	 */
	public static function register(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( self::class, 'addExporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( self::class, 'addEraser' ) );
	}

	/**
	 * `wp_privacy_personal_data_exporters` filter.
	 *
	 * @param array<string,array<string,mixed>> $exporters Registered exporters.
	 * @return array<string,array<string,mixed>>
	 */
	public static function addExporter( $exporters ): array {
		$exporters              = (array) $exporters;
		$exporters[ self::KEY ] = array(
			'exporter_friendly_name' => __( 'Total Mail Queue email log', 'total-mail-queue' ),
			'callback'               => array( self::class, 'export' ),
		);
		return $exporters;
	}

	/**
	 * `wp_privacy_personal_data_erasers` filter.
	 *
	 * @param array<string,array<string,mixed>> $erasers Registered erasers.
	 * @return array<string,array<string,mixed>>
	 */
	public static function addEraser( $erasers ): array {
		$erasers              = (array) $erasers;
		$erasers[ self::KEY ] = array(
			'eraser_friendly_name' => __( 'Total Mail Queue email log', 'total-mail-queue' ),
			'callback'             => array( self::class, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * Export one page of the requester's log rows.
	 *
	 * @param string $email Requester's address.
	 * @param int    $page  1-based page.
	 * @return array{data: list<array<string,mixed>>, done: bool}
	 */
	public static function export( $email, $page = 1 ): array {
		$email = self::normalise( (string) $email );
		$page  = max( 1, (int) $page );
		if ( '' === $email ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		$rows = self::candidates( $email, self::PAGE, ( $page - 1 ) * self::PAGE );
		$data = array();
		foreach ( $rows as $row ) {
			if ( ! self::isRecipient( $email, $row['recipient'] ) ) {
				continue;
			}
			$data[] = array(
				'group_id'    => 'total-mail-queue-log',
				'group_label' => __( 'Email log', 'total-mail-queue' ),
				'item_id'     => 'total-mail-queue-log-' . (int) $row['id'],
				'data'        => array(
					array(
						'name'  => __( 'Date', 'total-mail-queue' ),
						'value' => (string) $row['timestamp'],
					),
					array(
						'name'  => __( 'Status', 'total-mail-queue' ),
						'value' => (string) $row['status'],
					),
					array(
						'name'  => __( 'Subject', 'total-mail-queue' ),
						'value' => (string) $row['subject'],
					),
					array(
						'name'  => __( 'Message', 'total-mail-queue' ),
						'value' => self::exportedBody( $row ),
					),
				),
			);
		}

		return array(
			'data' => $data,
			'done' => count( $rows ) < self::PAGE,
		);
	}

	/**
	 * Erase the requester's sent, failed and alert rows.
	 *
	 * Rows are read by id, in order, and every call deletes up to PAGE of
	 * them; WordPress calls again while `done` is false. A call that deletes
	 * nothing has read every candidate, so the request always ends.
	 *
	 * @param string $email Requester's address.
	 * @param int    $page  1-based page (unused: a deleted row leaves the set).
	 * @return array{items_removed: bool, items_retained: bool, messages: list<string>, done: bool}
	 */
	public static function erase( $email, $page = 1 ): array {
		unset( $page );
		$email  = self::normalise( (string) $email );
		$result = array(
			'items_removed'  => false,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
		if ( '' === $email ) {
			return $result;
		}

		global $wpdb;
		$table   = Schema::queueTable();
		$deleted = 0;
		$pending = 0;
		$last    = 0;
		do {
			$rows = self::candidates( $email, self::PAGE, 0, $last );
			$read = count( $rows );
			foreach ( $rows as $row ) {
				$last = (int) $row['id'];
				if ( ! self::isRecipient( $email, $row['recipient'] ) ) {
					continue;
				}
				if ( in_array( (string) $row['status'], self::PENDING, true ) ) {
					++$pending;
					continue;
				}
				if ( $deleted >= self::PAGE ) {
					// Budget spent: the next call picks up here.
					$result['done'] = false;
					break 2;
				}
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the plugin's own table, which WordPress has no API for
				if ( $wpdb->delete( $table, array( 'id' => (int) $row['id'] ), array( '%d' ) ) ) {
					AttachmentStore::releaseFor( Serializer::decode( $row['attachments'] ) );
					++$deleted;
				}
			}
		} while ( self::PAGE === $read );

		$result['items_removed'] = $deleted > 0;
		if ( $result['done'] && $pending > 0 ) {
			$result['items_retained'] = true;
			$result['messages'][]     = sprintf(
				/* translators: %d: number of emails */
				_n(
					'%d email to this address is still waiting in the queue. It is kept until it is sent, and a new erasure request after that removes it.',
					'%d emails to this address are still waiting in the queue. They are kept until they are sent, and a new erasure request after that removes them.',
					$pending,
					'total-mail-queue'
				),
				$pending
			);
		}
		return $result;
	}

	/**
	 * Every address a stored recipient value names, lower case.
	 *
	 * The column holds what wp_mail() was given, JSON-encoded: one string, a
	 * comma-separated list or an array, each entry an address or
	 * `Name <address>`.
	 *
	 * @param mixed $stored Raw `recipient` column.
	 * @return list<string>
	 */
	public static function addresses( $stored ): array {
		$decoded = Serializer::decode( $stored );
		$entries = array();
		foreach ( is_array( $decoded ) ? $decoded : array( $decoded ) as $value ) {
			if ( ! is_scalar( $value ) ) {
				continue;
			}
			foreach ( explode( ',', (string) $value ) as $entry ) {
				$entries[] = $entry;
			}
		}
		$out = array();
		foreach ( $entries as $entry ) {
			$address = preg_match( '/<([^>]+)>/', $entry, $m ) ? $m[1] : $entry;
			$address = self::normalise( $address );
			if ( '' !== $address ) {
				$out[] = $address;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Whether the address is one of the row's recipients.
	 *
	 * @param string $email  Normalised address.
	 * @param mixed  $stored Raw `recipient` column.
	 */
	private static function isRecipient( string $email, $stored ): bool {
		return in_array( $email, self::addresses( $stored ), true );
	}

	/**
	 * Rows whose recipient column contains the address, by id.
	 *
	 * @param string $email  Normalised address.
	 * @param int    $limit  Rows to read.
	 * @param int    $offset Rows to skip.
	 * @param int    $after  Only rows with a higher id.
	 * @return list<array<string,mixed>>
	 */
	private static function candidates( string $email, int $limit, int $offset, int $after = 0 ): array {
		global $wpdb;
		$table = Schema::queueTable();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the plugin's own table, which WordPress has no API for; a privacy request must read it live
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT `id`, `timestamp`, `status`, `recipient`, `subject`, `message`, `source_key`, `attachments` FROM `$table` WHERE `id` > %d AND `recipient` LIKE %s ORDER BY `id` ASC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name comes from Schema, not from input
				$after,
				'%' . $wpdb->esc_like( $email ) . '%',
				$limit,
				$offset
			),
			ARRAY_A
		);
		return is_array( $rows ) ? array_values( $rows ) : array();
	}

	/**
	 * The message as the export shows it.
	 *
	 * @param array<string,mixed> $row Log row.
	 */
	private static function exportedBody( array $row ): string {
		if ( in_array( (string) $row['source_key'], Access::ADMIN_ONLY_BODIES, true ) ) {
			return __( 'Withheld: this email carries a one-time account link.', 'total-mail-queue' );
		}
		$body = (string) $row['message'];
		if ( wp_strip_all_tags( $body ) !== $body ) {
			$body = (string) preg_replace( '#<br\s*/?>|</(?:p|div|li|h[1-6]|tr)>#i', "\n", $body );
			$body = wp_strip_all_tags( $body );
		}
		return trim( (string) preg_replace( "/\n{3,}/", "\n\n", $body ) );
	}

	/**
	 * An address trimmed and in lower case, or '' when it is not one.
	 *
	 * @param string $address Address.
	 */
	private static function normalise( string $address ): string {
		$address = strtolower( trim( $address ) );
		return is_email( $address ) ? $address : '';
	}
}
