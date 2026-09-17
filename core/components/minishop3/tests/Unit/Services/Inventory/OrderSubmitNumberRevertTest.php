<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Inventory;

use MiniShop3\Services\Order\OrderSubmitHandler;
use MiniShop3\Tests\RecordingMsOrder;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class OrderSubmitNumberRevertTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 3) . '/stubs/ModxStub.php';
        }
        require_once dirname(__DIR__, 3) . '/RecordingMsOrder.php';
    }

    public function testRevertClearsNumberWhenInventoryEnabled(): void
    {
        $order = new RecordingMsOrder(['id' => 9, 'num' => '2608/1']);
        $handler = $this->handler($this->modx(true));

        $method = new \ReflectionMethod(OrderSubmitHandler::class, 'revertAllocatedNumber');
        $method->invoke($handler, $order);

        self::assertNull($order->get('num'));
        self::assertTrue($order->saved);
    }

    public function testRevertKeepsNumberWhenInventoryDisabled(): void
    {
        $order = new RecordingMsOrder(['id' => 9, 'num' => '2608/1']);
        $handler = $this->handler($this->modx(false));

        $method = new \ReflectionMethod(OrderSubmitHandler::class, 'revertAllocatedNumber');
        $method->invoke($handler, $order);

        self::assertSame('2608/1', $order->get('num'));
    }

    private function handler(modX $modx): OrderSubmitHandler
    {
        $handler = (new ReflectionClass(OrderSubmitHandler::class))->newInstanceWithoutConstructor();
        $prop = new \ReflectionProperty(OrderSubmitHandler::class, 'modx');
        $prop->setValue($handler, $modx);

        return $handler;
    }

    private function modx(bool $enabled): modX
    {
        return new class ($enabled) extends modX {
            public function __construct(private bool $enabled)
            {
                parent::__construct();
            }

            public function getOption(string $key, $options = null, $default = null)
            {
                return $key === 'ms3_inventory_enabled' ? $this->enabled : $default;
            }
        };
    }
}
