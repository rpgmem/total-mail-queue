<?php

declare(strict_types=1);

namespace TMQ\Tests\Support;

/**
 * In-memory stand-in for $wpdb.
 *
 * Records every call made by code under test and lets tests pre-load return
 * values for read methods (get_var/get_row/get_col/get_results). prepare() is
 * implemented as a thin sprintf-like helper — sufficient because no SQL is
 * actually executed; the placeholder substitution exists only so tests can
 * inspect the rendered query.
 */
final class MockWpdb {

    public string $prefix = 'wp_';

    public int $insert_id = 0;

    /** @var list<array{method:string,args:array}> */
    public array $calls = array();

    /** @var array<string,array<int,mixed>> queue of canned responses keyed by method */
    private array $returns = array(
        'get_var'     => array(),
        'get_row'     => array(),
        'get_col'     => array(),
        'get_results' => array(),
        'query'       => array(),
        'insert'      => array(),
        'update'      => array(),
        'delete'      => array(),
    );

    /**
     * Pre-load a return value for the next call to a read/write method.
     */
    public function will_return( string $method, mixed $value ): void {
        if ( ! array_key_exists( $method, $this->returns ) ) {
            $this->returns[ $method ] = array();
        }
        $this->returns[ $method ][] = $value;
    }

    /**
     * Find the first recorded call to a method, or null if it was never called.
     *
     * @return array{method:string,args:array}|null
     */
    public function call( string $method ): ?array {
        foreach ( $this->calls as $call ) {
            if ( $call['method'] === $method ) {
                return $call;
            }
        }
        return null;
    }

    /**
     * Find every recorded call to a method, in order.
     *
     * @return list<array{method:string,args:array}>
     */
    public function callsTo( string $method ): array {
        $matches = array();
        foreach ( $this->calls as $call ) {
            if ( $call['method'] === $method ) {
                $matches[] = $call;
            }
        }
        return $matches;
    }

    public function get_charset_collate(): string {
        return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
    }

    /**
     * Quote LIKE wildcards in the input. Mirrors $wpdb->esc_like(); we don't
     * track the call because callers always feed it back through prepare().
     */
    public function esc_like( string $text ): string {
        return addcslashes( $text, '_%\\' );
    }

    public function prepare( string $query, mixed ...$args ): string {
        $this->calls[] = array( 'method' => 'prepare', 'args' => array( $query, $args ) );
        // Replace WordPress format specifiers with sprintf-compatible ones.
        $rendered = preg_replace( '/%[dfsi]/', '%s', $query ) ?? $query;
        if ( $args === array() ) {
            return $query;
        }
        return @vsprintf( $rendered, $this->flatten_args( $args ) );
    }

    public function insert( string $table, array $data, $format = null ): int|false {
        $this->calls[] = array( 'method' => 'insert', 'args' => array( $table, $data, $format ) );
        $this->insert_id++;
        return $this->pop( 'insert', 1 );
    }

    public function update( string $table, array $data, array $where, $format = null, $where_format = null ): int|false {
        $this->calls[] = array( 'method' => 'update', 'args' => array( $table, $data, $where, $format, $where_format ) );
        return $this->pop( 'update', 1 );
    }

    public function delete( string $table, array $where, $where_format = null ): int|false {
        $this->calls[] = array( 'method' => 'delete', 'args' => array( $table, $where, $where_format ) );
        return $this->pop( 'delete', 1 );
    }

    public function query( string $sql ): int|bool {
        $this->calls[] = array( 'method' => 'query', 'args' => array( $sql ) );
        return $this->pop( 'query', 0 );
    }

    public function get_var( string $sql ): mixed {
        $this->calls[] = array( 'method' => 'get_var', 'args' => array( $sql ) );
        return $this->pop( 'get_var', null );
    }

    public function get_row( string $sql, string $output = 'OBJECT' ): mixed {
        $this->calls[] = array( 'method' => 'get_row', 'args' => array( $sql, $output ) );
        return $this->pop( 'get_row', null );
    }

    public function get_col( string $sql, int $col_offset = 0 ): array {
        $this->calls[] = array( 'method' => 'get_col', 'args' => array( $sql, $col_offset ) );
        $next = $this->pop( 'get_col', array() );
        return is_array( $next ) ? $next : array();
    }

    public function get_results( string $sql, string $output = 'OBJECT' ): mixed {
        $this->calls[] = array( 'method' => 'get_results', 'args' => array( $sql, $output ) );
        return $this->pop( 'get_results', array() );
    }

    private function pop( string $method, mixed $default ): mixed {
        if ( ! empty( $this->returns[ $method ] ) ) {
            return array_shift( $this->returns[ $method ] );
        }
        return $default;
    }

    private function flatten_args( array $args ): array {
        $flat = array();
        array_walk_recursive( $args, function ( $v ) use ( &$flat ) { $flat[] = $v; } );
        return $flat;
    }
}
