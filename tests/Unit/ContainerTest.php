<?php

declare(strict_types=1);

namespace TMQ\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TotalMailQueue\Container;

/**
 * The service container: lazy factories, one instance per id until the
 * factory is replaced, and a loud failure for an unknown id.
 *
 * @covers \TotalMailQueue\Container
 */
final class ContainerTest extends TestCase {

    public function test_a_factory_runs_once_and_receives_the_container(): void {
        $container = new Container();
        $calls     = 0;
        $container->set( 'dep', static fn (): string => 'value' );
        $container->set(
            'service',
            static function ( Container $c ) use ( &$calls ): \stdClass {
                ++$calls;
                $object      = new \stdClass();
                $object->dep = $c->get( 'dep' );
                return $object;
            }
        );

        $first = $container->get( 'service' );

        self::assertSame( $first, $container->get( 'service' ) );
        self::assertSame( 'value', $first->dep );
        self::assertSame( 1, $calls );
    }

    public function test_replacing_a_factory_drops_the_cached_instance(): void {
        $container = new Container();
        $container->set( 'id', static fn (): string => 'old' );
        self::assertSame( 'old', $container->get( 'id' ) );

        $container->set( 'id', static fn (): string => 'new' );

        self::assertSame( 'new', $container->get( 'id' ) );
    }

    public function test_has_reports_registration_and_an_unknown_id_throws(): void {
        $container = new Container();
        $container->set( 'known', static fn (): int => 1 );

        self::assertTrue( $container->has( 'known' ) );
        self::assertFalse( $container->has( 'unknown' ) );

        $this->expectException( \InvalidArgumentException::class );
        $this->expectExceptionMessage( 'No service registered for id: unknown' );
        $container->get( 'unknown' );
    }

    public function test_a_null_result_is_cached_like_any_other(): void {
        $container = new Container();
        $calls     = 0;
        $container->set(
            'nothing',
            static function () use ( &$calls ) {
                ++$calls;
                return null;
            }
        );

        $container->get( 'nothing' );
        $container->get( 'nothing' );

        self::assertSame( 1, $calls );
    }
}
