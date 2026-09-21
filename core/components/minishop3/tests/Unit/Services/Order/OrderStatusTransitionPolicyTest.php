<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Order;

use MiniShop3\Services\Order\OrderStatusTransitionPolicy;
use PHPUnit\Framework\TestCase;

final class OrderStatusTransitionPolicyTest extends TestCase
{
    public function testEmptySettingDisablesPolicy(): void
    {
        self::assertSame(
            OrderStatusTransitionPolicy::MODE_OFF,
            OrderStatusTransitionPolicy::resolve('')['mode']
        );
        self::assertSame(
            OrderStatusTransitionPolicy::MODE_OFF,
            OrderStatusTransitionPolicy::resolve(null)['mode']
        );
        self::assertSame(
            OrderStatusTransitionPolicy::MODE_OFF,
            OrderStatusTransitionPolicy::resolve('   ')['mode']
        );
    }

    public function testParsesCsvPairs(): void
    {
        $resolved = OrderStatusTransitionPolicy::resolve('2:3, 3:4,2:5');
        self::assertSame(OrderStatusTransitionPolicy::MODE_ON, $resolved['mode']);
        self::assertTrue(isset($resolved['edges'][2][3]));
        self::assertTrue(isset($resolved['edges'][3][4]));
        self::assertTrue(isset($resolved['edges'][2][5]));
        self::assertFalse(isset($resolved['edges'][2][4]));
    }

    public function testParsesJsonPairs(): void
    {
        $resolved = OrderStatusTransitionPolicy::resolve('[[2,3],[3,4]]');
        self::assertSame(OrderStatusTransitionPolicy::MODE_ON, $resolved['mode']);
        self::assertTrue(isset($resolved['edges'][2][3]));
        self::assertFalse(isset($resolved['edges'][4][5]));
    }

    public function testInvalidJsonIsNotDenyAll(): void
    {
        $resolved = OrderStatusTransitionPolicy::resolve('[not-json');
        self::assertSame(OrderStatusTransitionPolicy::MODE_INVALID, $resolved['mode']);
    }

    public function testGarbageCsvIsInvalid(): void
    {
        $resolved = OrderStatusTransitionPolicy::resolve('2-3,foo');
        self::assertSame(OrderStatusTransitionPolicy::MODE_INVALID, $resolved['mode']);
    }
}
