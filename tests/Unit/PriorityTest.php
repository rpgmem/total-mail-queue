<?php

declare(strict_types=1);

namespace TMQ\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TotalMailQueue\Queue\Priority;

/**
 * @covers \TotalMailQueue\Queue\Priority
 */
final class PriorityTest extends TestCase {

    public function test_scale_constants_are_ordered_low_is_more_urgent(): void {
        self::assertLessThan( Priority::NORMAL, Priority::HIGH );
        self::assertSame( 1, Priority::MIN );
        self::assertGreaterThan( Priority::NORMAL, Priority::MAX );
    }

    public function test_clamp_bounds_to_the_valid_range(): void {
        self::assertSame( Priority::MIN, Priority::clamp( 0 ) );
        self::assertSame( Priority::MIN, Priority::clamp( -50 ) );
        self::assertSame( Priority::MAX, Priority::clamp( 9999 ) );
        self::assertSame( 42, Priority::clamp( 42 ) );
    }

    public function test_most_urgent_returns_the_smaller_value(): void {
        self::assertSame( Priority::HIGH, Priority::mostUrgent( Priority::NORMAL, Priority::HIGH ) );
        self::assertSame( 1, Priority::mostUrgent( 1, 50 ) );
        self::assertSame( 7, Priority::mostUrgent( 7, 7 ) );
    }
}
