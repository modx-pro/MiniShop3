<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Integration\Order;

use MiniShop3\MiniShop3;
use MiniShop3\Services\Order\OrderDraftManager;
use MiniShop3\Services\Order\OrderService;
use MiniShop3\Tests\Support\RecordingMsOrder;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

/**
 * Level-2: OrderDraftManager wires clampComputedTotal on delivery / recalculate.
 */
final class OrderDraftCostClampTest extends TestCase
{
    private OrderDraftManager $drafts;
    private RecordingMsOrder $draft;

    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 2) . '/stubs/ModxStub.php';
        require_once dirname(__DIR__, 2) . '/support/RecordingMsOrder.php';
        }

        $orderService = new OrderService(new modX());

        $modx = new class ($orderService) extends modX {
            public object $services;

            public function __construct(OrderService $orderService)
            {
                parent::__construct();
                $this->services = new class ($orderService) {
                    public function __construct(private OrderService $orderService)
                    {
                    }

                    public function get(string $key): mixed
                    {
                        return $key === 'ms3_order_service' ? $this->orderService : null;
                    }
                };
            }
        };

        $this->drafts = new OrderDraftManager($modx, $this->createStub(MiniShop3::class));
        $this->draft = new RecordingMsOrder([
            'id' => 7,
            'cart_cost' => 100.0,
            'delivery_cost' => 0.0,
            'cost' => 100.0,
            'weight' => 0.0,
        ]);
    }

    public function testSetDeliveryCostClampsNegativeTotalToZero(): void
    {
        $this->drafts->setDeliveryCost($this->draft, -150.0);

        self::assertSame(-150.0, $this->draft->get('delivery_cost'));
        self::assertSame(0.0, $this->draft->get('cost'));
        self::assertTrue($this->draft->saved);
    }

    public function testSetDeliveryCostKeepsPositiveTotal(): void
    {
        $this->drafts->setDeliveryCost($this->draft, 15.0);

        self::assertSame(15.0, $this->draft->get('delivery_cost'));
        self::assertSame(115.0, $this->draft->get('cost'));
    }

    public function testRecalculateAppliesWeightTimesCountAndClamp(): void
    {
        $this->draft->set('delivery_cost', -500.0);
        $this->draft->products = [
            new class {
                public function get(string $field): float|int
                {
                    return match ($field) {
                        'weight' => 1.5,
                        'count' => 2,
                        'cost' => 40.0,
                        default => 0,
                    };
                }
            },
        ];

        $this->drafts->recalculate($this->draft);

        self::assertSame(40.0, $this->draft->get('cart_cost'));
        self::assertSame(3.0, $this->draft->get('weight'));
        self::assertSame(0.0, $this->draft->get('cost'));
        self::assertTrue($this->draft->saved);
    }
}
