<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Order;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msCustomer;
use MiniShop3\Model\msOrder;
use MiniShop3\Services\Order\OrderAddressManager;
use MiniShop3\Services\Order\OrderDraftManager;
use MiniShop3\Services\Order\OrderFieldManager;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

final class OrderAddressManagerProfilePrefillTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 3) . '/stubs/ModxStub.php';
        }
    }

    public function testFillFromCustomerSkipsNonEmptyOrderFields(): void
    {
        $manager = $this->makeManager();
        $draft = $this->createStub(msOrder::class);
        $orderData = [
            'address_first_name' => 'Guest',
            'address_last_name' => '',
            'address_email' => '',
            'address_phone' => '',
        ];

        $manager->fillFromCustomer($draft, $orderData, [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'phone' => '+79990001122',
        ]);

        self::assertSame('Guest', $orderData['address_first_name']);
        self::assertSame('Doe', $orderData['address_last_name']);
        self::assertSame('jane@example.com', $orderData['address_email']);
        self::assertSame('+79990001122', $orderData['address_phone']);
    }

    public function testPrefillProfileFieldsFromCustomerRefusesForeignDraft(): void
    {
        $fieldManager = $this->createMock(OrderFieldManager::class);
        $fieldManager->expects(self::never())->method('add');

        $manager = $this->makeManager($fieldManager);
        $draft = $this->createStub(msOrder::class);
        $draft->method('get')->willReturnMap([
            ['customer_id', 99],
        ]);
        $customer = $this->createStub(msCustomer::class);
        $customer->method('get')->willReturnMap([
            ['id', 42],
            ['first_name', 'Jane'],
            ['last_name', 'Doe'],
            ['email', 'jane@example.com'],
        ]);

        $manager->prefillProfileFieldsFromCustomer($draft, $customer);
    }

    public function testPrefillProfileFieldsFromCustomerFillsEmptyDraftFields(): void
    {
        $fieldManager = $this->createMock(OrderFieldManager::class);
        $fieldManager->expects(self::exactly(3))
            ->method('add')
            ->willReturn(['success' => true, 'message' => '', 'data' => []]);

        $manager = $this->makeManager($fieldManager);
        $draft = $this->createStub(msOrder::class);
        $draft->method('get')->willReturnMap([
            ['customer_id', 42],
        ]);
        $customer = $this->createStub(msCustomer::class);
        $customer->method('get')->willReturnMap([
            ['id', 42],
            ['first_name', 'Jane'],
            ['last_name', 'Doe'],
            ['email', 'jane@example.com'],
            ['password', 'must-not-leak'],
        ]);

        $manager->prefillProfileFieldsFromCustomer($draft, $customer);
    }

    private function makeManager(?OrderFieldManager $fieldManager = null): OrderAddressManager
    {
        $modx = $this->createStub(modX::class);
        $ms3 = $this->createStub(MiniShop3::class);

        $draftManager = $this->createStub(OrderDraftManager::class);
        $draftManager->method('toArray')->willReturn([
            'customer_id' => 42,
            'address_first_name' => '',
            'address_last_name' => '',
            'address_email' => '',
            'address_phone' => '',
        ]);

        $fieldManager ??= $this->createStub(OrderFieldManager::class);
        if (!($fieldManager instanceof \PHPUnit\Framework\MockObject\MockObject)) {
            $fieldManager->method('add')->willReturn(['success' => true, 'message' => '', 'data' => []]);
        }

        return new OrderAddressManager($modx, $ms3, $draftManager, $fieldManager);
    }
}
