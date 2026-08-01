<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Order;

use MiniShop3\Services\Order\OrderService;
use MODX\Revolution\modX;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ClampComputedTotalTest extends TestCase
{
    private OrderService $service;

    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 3) . '/stubs/ModxStub.php';
        }

        $this->service = new OrderService(new modX());
    }

    #[DataProvider('totalCases')]
    public function testClampComputedTotal(
        float $expected,
        float $cartCost,
        float $deliveryCost,
        float $paymentCost
    ): void {
        self::assertSame(
            $expected,
            $this->service->clampComputedTotal(null, $cartCost, $deliveryCost, $paymentCost)
        );
    }

    /**
     * @return iterable<string, array{0: float, 1: float, 2: float, 3: float}>
     */
    public static function totalCases(): iterable
    {
        yield 'plain sum' => [110.0, 100.0, 10.0, 0.0];
        yield 'with payment' => [115.5, 100.0, 10.0, 5.5];
        yield 'negative delivery clamped' => [0.0, 10.0, -50.0, 0.0];
        yield 'negative component non-negative sum' => [90.0, 100.0, -10.0, 0.0];
        yield 'all zero' => [0.0, 0.0, 0.0, 0.0];
    }
}
