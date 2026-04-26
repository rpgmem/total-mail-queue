<?php
/**
 * Tiny service container used by {@see Plugin}.
 *
 * @package TotalMailQueue
 */

declare(strict_types=1);

namespace TotalMailQueue;

/**
 * Minimal lazy-resolving service locator.
 *
 * Stores factories keyed by a string id; resolves them once on first
 * {@see Container::get()} and caches the resulting instance for the life of
 * the request. Intentionally small — there is no auto-wiring, no aliasing,
 * no decoration. Each consumer registers what it needs.
 */
final class Container {

	/**
	 * Registered factories keyed by service id.
	 *
	 * @var array<string,callable(self):mixed>
	 */
	private array $factories = array();

	/**
	 * Memoised service instances keyed by id.
	 *
	 * @var array<string,mixed>
	 */
	private array $instances = array();

	/**
	 * Register a factory for the given service id.
	 *
	 * @param string               $id      Service identifier.
	 * @param callable(self):mixed $factory Factory invoked the first time
	 *                                      {@see Container::get()} resolves $id.
	 *                                      Receives the container so it can
	 *                                      resolve dependencies of its own.
	 */
	public function set( string $id, callable $factory ): void {
		$this->factories[ $id ] = $factory;
		unset( $this->instances[ $id ] );
	}

	/**
	 * Resolve the service registered for $id.
	 *
	 * @param string $id Service identifier.
	 * @return mixed Whatever the factory produced (memoised after the first resolve).
	 *
	 * @throws \InvalidArgumentException When no factory is registered for $id.
	 */
	public function get( string $id ): mixed {
		if ( array_key_exists( $id, $this->instances ) ) {
			return $this->instances[ $id ];
		}
		if ( ! isset( $this->factories[ $id ] ) ) {
			throw new \InvalidArgumentException( "No service registered for id: $id" );
		}
		$this->instances[ $id ] = ( $this->factories[ $id ] )( $this );
		return $this->instances[ $id ];
	}

	/**
	 * Whether a factory is registered for $id.
	 *
	 * @param string $id Service identifier.
	 * @return bool True when {@see Container::set()} has been called for the id.
	 */
	public function has( string $id ): bool {
		return isset( $this->factories[ $id ] );
	}
}
