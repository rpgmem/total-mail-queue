<?php
/**
 * The filters a Log or Retention list is narrowed by.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Queue;

/**
 * Immutable set of list filters: status, source, free-text search and a
 * date range on `timestamp`.
 *
 * Built from the request by {@see ListFilter::fromRequest()}, which is the
 * only place the raw values are read and validated. {@see QueueRepository}
 * turns it into SQL, so a value that reaches the query has already passed
 * the checks below.
 */
final class ListFilter {

	/**
	 * The request keys the filters travel under. They are GET parameters, so
	 * pagination and the bulk-action form keep them.
	 */
	public const KEYS = array( 'status_filter', 'source_filter', 's', 'date_from', 'date_to' );

	/**
	 * Exact status, or '' for every status the list shows.
	 *
	 * @var string
	 */
	public string $status;

	/**
	 * Exact `source_key`, or ''.
	 *
	 * @var string
	 */
	public string $source;

	/**
	 * Text matched against recipient and subject, or ''.
	 *
	 * @var string
	 */
	public string $search;

	/**
	 * First day of the range, `Y-m-d`, or ''.
	 *
	 * @var string
	 */
	public string $from;

	/**
	 * Last day of the range, `Y-m-d`, or ''.
	 *
	 * @var string
	 */
	public string $to;

	/**
	 * Build a filter from already-validated values.
	 *
	 * A range given backwards is put the right way round, so "from the 10th
	 * to the 3rd" lists the same week as "from the 3rd to the 10th".
	 *
	 * @param string $status Status, or ''.
	 * @param string $source Source key, or ''.
	 * @param string $search Search text, or ''.
	 * @param string $from   `Y-m-d`, or ''. An invalid date is dropped.
	 * @param string $to     `Y-m-d`, or ''. An invalid date is dropped.
	 */
	public function __construct( string $status = '', string $source = '', string $search = '', string $from = '', string $to = '' ) {
		$from = self::validDate( $from );
		$to   = self::validDate( $to );
		if ( '' !== $from && '' !== $to && $from > $to ) {
			list( $from, $to ) = array( $to, $from );
		}
		$this->status = $status;
		$this->source = $source;
		$this->search = trim( $search );
		$this->from   = $from;
		$this->to     = $to;
	}

	/**
	 * Read the filters from the current request.
	 *
	 * Read-only list narrowing, so no nonce: nothing is changed by it.
	 */
	public static function fromRequest(): self {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only list filters, not a state change.
		$read   = static function ( string $key ): string {
			return isset( $_REQUEST[ $key ] ) ? sanitize_text_field( wp_unslash( (string) $_REQUEST[ $key ] ) ) : '';
		};
		$filter = new self(
			sanitize_key( $read( 'status_filter' ) ),
			$read( 'source_filter' ),
			$read( 's' ),
			$read( 'date_from' ),
			$read( 'date_to' )
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		return $filter;
	}

	/**
	 * Whether any filter is set.
	 */
	public function isActive(): bool {
		return '' !== $this->status . $this->source . $this->search . $this->from . $this->to;
	}

	/**
	 * The non-empty filters as query arguments, to carry them into a URL.
	 *
	 * @return array<string,string>
	 */
	public function queryArgs(): array {
		return array_filter(
			array(
				'status_filter' => $this->status,
				'source_filter' => $this->source,
				's'             => $this->search,
				'date_from'     => $this->from,
				'date_to'       => $this->to,
			),
			static fn ( string $value ): bool => '' !== $value
		);
	}

	/**
	 * The date when it is a real `Y-m-d` calendar day, '' otherwise.
	 *
	 * @param string $date Candidate date.
	 */
	private static function validDate( string $date ): string {
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m ) ) {
			return '';
		}
		return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ? $date : '';
	}
}
