<?php

declare(strict_types=1);

namespace TMQ\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * @covers \TotalMailQueue\Support\Serializer::encode
 * @covers \TotalMailQueue\Support\Serializer::decode
 */
final class SerializationTest extends TestCase {

    public function test_encode_emits_json_for_array(): void {
        $value = array( 'foo', 'bar' );

        $encoded = \TotalMailQueue\Support\Serializer::encode( $value );

        self::assertSame( '["foo","bar"]', $encoded );
    }

    public function test_encode_decode_round_trip_for_associative_array(): void {
        $value = array(
            'recipient' => 'user@example.com',
            'tags'      => array( 'a', 'b' ),
            'count'     => 7,
        );

        self::assertSame( $value, \TotalMailQueue\Support\Serializer::decode( \TotalMailQueue\Support\Serializer::encode( $value ) ) );
    }

    public function test_decode_returns_input_when_empty_or_non_string(): void {
        self::assertSame( '', \TotalMailQueue\Support\Serializer::decode( '' ) );
        self::assertNull( \TotalMailQueue\Support\Serializer::decode( null ) );
        self::assertSame( array( 1, 2 ), \TotalMailQueue\Support\Serializer::decode( array( 1, 2 ) ) );
    }

    public function test_decode_returns_raw_string_when_not_json_or_serialized(): void {
        $raw = 'plain-string-not-encoded';

        self::assertSame( $raw, \TotalMailQueue\Support\Serializer::decode( $raw ) );
    }

    public function test_decode_falls_back_to_unserialize_for_legacy_php_serialized_data(): void {
        // Legacy data written by older plugin versions used PHP serialize().
        $value     = array( 'one' => 1, 'two' => 2 );
        $legacy    = serialize( $value );

        self::assertSame( $value, \TotalMailQueue\Support\Serializer::decode( $legacy ) );
    }

    public function test_decode_blocks_object_classes_in_legacy_serialized_data(): void {
        // Even when reading legacy data, object instantiation must be blocked
        // (allowed_classes => false). The serialized payload below references
        // a class that does not exist in this test process.
        $payload = 'O:8:"stdClass":1:{s:1:"a";i:1;}';

        $decoded = \TotalMailQueue\Support\Serializer::decode( $payload );

        self::assertInstanceOf( '__PHP_Incomplete_Class', $decoded );
    }
}
