<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Order;

require_once __DIR__ . '/../../../stubs/ModxStub.php';

use MiniShop3\MiniShop3;
use MiniShop3\Model\msOrder;
use MiniShop3\Services\Order\OrderAddressManager;
use MiniShop3\Services\Order\OrderDraftManager;
use MiniShop3\Services\Order\OrderFieldManager;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

/**
 * #862: a zero customer value must fill a blank address field, and a blank
 * customer value must not overwrite an existing zero.
 */
final class OrderAddressManagerZeroTest extends TestCase
{
    public function testZeroCustomerValueIsApplied(): void
    {
        $orderData = ['address_phone' => ''];
        $manager = $this->manager();

        $manager->fillFromCustomer(
            $this->createMock(msOrder::class),
            $orderData,
            ['phone' => 0]
        );

        self::assertSame(0, $orderData['address_phone']);
    }

    public function testBlankCustomerValueDoesNotOverwriteZero(): void
    {
        $orderData = ['address_phone' => 0];
        $manager = $this->manager();

        $manager->fillFromCustomer(
            $this->createMock(msOrder::class),
            $orderData,
            ['phone' => '']
        );

        self::assertSame(0, $orderData['address_phone']);
    }

    private function manager(): OrderAddressManager
    {
        return new OrderAddressManager(
            new class extends modX {
            },
            $this->createMock(MiniShop3::class),
            $this->createMock(OrderDraftManager::class),
            $this->createMock(OrderFieldManager::class)
        );
    }
}
