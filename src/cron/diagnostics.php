<?php
/**
 * Read/write the `wp_tmq_last_cron` diagnostics option.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue\Cron;

/**
 * Persists a snapshot of the most recent cron batch — used by the admin
 * Retention tab to surface "the last run did X, sent Y, errored Z".
 *
 * Mutable instance (not all-static) because every batch builds the
 * diagnostics array progressively.
 */
final class Diagnostics {

	/**
	 * Option key that stores the most recent batch summary.
	 */
	public const OPTION_NAME = 'wp_tmq_last_cron';

	/**
	 * Accumulated entries for the current batch.
	 *
	 * @var array<string,mixed>
	 */
	private array $entries;

	/**
	 * Build a fresh diagnostics accumulator.
	 *
	 * @param array<string,mixed> $initial Seed values (typically just `time`).
	 */
	public function __construct( array $initial = array() ) {
		$this->entries = $initial;
	}

	/**
	 * Set or override one diagnostic field.
	 *
	 * @param string $key   Field name.
	 * @param mixed  $value Field value.
	 */
	public function set( string $key, $value ): void {
		$this->entries[ $key ] = $value;
	}

	/**
	 * Increment a numeric counter (creating it if absent).
	 *
	 * @param string $key Field name.
	 * @param int    $by  Amount to add.
	 */
	public function increment( string $key, int $by = 1 ): void {
		$this->entries[ $key ] = ( isset( $this->entries[ $key ] ) ? (int) $this->entries[ $key ] : 0 ) + $by;
	}

	/**
	 * Whether a given field has been set.
	 *
	 * @param string $key Field name.
	 */
	public function has( string $key ): bool {
		return array_key_exists( $key, $this->entries );
	}

	/**
	 * Persist the current entries to the diagnostics option.
	 */
	public function save(): void {
		update_option( self::OPTION_NAME, $this->entries, false );
	}
}
