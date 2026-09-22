<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Inventory;

use MiniShop3\Services\Inventory\InventoryContext;
use MiniShop3\Services\Inventory\InventoryException;
use MiniShop3\Services\Inventory\InventoryKey;
use MiniShop3\Tests\Support\InMemoryInventoryStockStore;
use MiniShop3\Services\Inventory\ProductStockInventory;
use PHPUnit\Framework\TestCase;

final class ProductStockInventoryTest extends TestCase
{
    public function testReserveCommitDecreasesAvailable(): void
    {
        $inventory = $this->makeInventory(5.0);
        $key = new InventoryKey(10);
        $ctx = new InventoryContext(100);

        $inventory->reserve($key, 2, $ctx);
        self::assertSame(3.0, $inventory->getAvailable($key));

        $inventory->commit($key, 2, $ctx);
        self::assertSame(3.0, $inventory->getAvailable($key));
        $inventory->commit($key, 2, $ctx);
        self::assertSame(3.0, $inventory->getAvailable($key));
    }

    public function testInsufficientStockDoesNotChangeAvailable(): void
    {
        $inventory = $this->makeInventory(1.0);
        $key = new InventoryKey(10);
        $ctx = new InventoryContext(100);

        $this->expectException(InventoryException::class);
        try {
            $inventory->reserve($key, 2, $ctx);
        } finally {
            self::assertSame(1.0, $inventory->getAvailable($key));
        }
    }

    public function testAssertAvailableRejectsOverQty(): void
    {
        $inventory = $this->makeInventory(1.0);
        $this->expectException(InventoryException::class);
        $inventory->assertAvailable(new InventoryKey(10), 2);
    }

    public function testReleaseBeforeCommitRestoresAvailable(): void
    {
        $inventory = $this->makeInventory(4.0);
        $key = new InventoryKey(10);
        $ctx = new InventoryContext(100);

        $inventory->reserve($key, 3, $ctx);
        $inventory->release($key, 3, $ctx);
        self::assertSame(4.0, $inventory->getAvailable($key));
        $inventory->release($key, 3, $ctx);
        self::assertSame(4.0, $inventory->getAvailable($key));
    }

    public function testReleaseAfterCommitDoesNotRestore(): void
    {
        $inventory = $this->makeInventory(4.0);
        $key = new InventoryKey(10);
        $ctx = new InventoryContext(100);

        $inventory->reserve($key, 1, $ctx);
        $inventory->commit($key, 1, $ctx);
        $inventory->release($key, 1, $ctx);
        self::assertSame(3.0, $inventory->getAvailable($key));
    }

    public function testReserveIsIdempotentForSameOrder(): void
    {
        $inventory = $this->makeInventory(5.0);
        $key = new InventoryKey(10);
        $ctx = new InventoryContext(100);

        $inventory->reserve($key, 2, $ctx);
        $inventory->reserve($key, 2, $ctx);
        self::assertSame(3.0, $inventory->getAvailable($key));
    }

    public function testCommitWithoutPriorReserveDecrementsOnce(): void
    {
        $inventory = $this->makeInventory(5.0);
        $key = new InventoryKey(10);
        $ctx = new InventoryContext(100);

        $inventory->commit($key, 2, $ctx);
        self::assertSame(3.0, $inventory->getAvailable($key));
        $inventory->commit($key, 2, $ctx);
        self::assertSame(3.0, $inventory->getAvailable($key));
    }

    public function testSecondBuyerLosesLastUnit(): void
    {
        $store = new InMemoryInventoryStockStore();
        $store->seedStock(10, 1);
        $inventory = new ProductStockInventory($store);
        $key = new InventoryKey(10);

        $inventory->reserve($key, 1, new InventoryContext(1));
        $this->expectException(InventoryException::class);
        $inventory->reserve($key, 1, new InventoryContext(2));
    }

    public function testReleaseDoesNotRestoreWhenStateClaimIsLost(): void
    {
        $inner = new InMemoryInventoryStockStore();
        $inner->seedStock(10, 4.0);
        $inner->saveReservation(100, 10, 1.0, 'reserved');
        $store = new class ($inner) implements \MiniShop3\Services\Inventory\InventoryStockStoreInterface {
            public function __construct(private readonly InMemoryInventoryStockStore $inner)
            {
            }

            public function getAvailable(int $productId): float
            {
                return $this->inner->getAvailable($productId);
            }

            public function tryDecrement(int $productId, float $qty): bool
            {
                return $this->inner->tryDecrement($productId, $qty);
            }

            public function increment(int $productId, float $qty): void
            {
                $this->inner->increment($productId, $qty);
            }

            public function findReservation(int $orderId, int $productId): ?array
            {
                return $this->inner->findReservation($orderId, $productId);
            }

            public function saveReservation(int $orderId, int $productId, float $qty, string $state): void
            {
                $this->inner->saveReservation($orderId, $productId, $qty, $state);
            }

            public function insertReservation(int $orderId, int $productId, float $qty, string $state): bool
            {
                return $this->inner->insertReservation($orderId, $productId, $qty, $state);
            }

            public function transitionReservation(
                int $orderId,
                int $productId,
                string $fromState,
                string $toState,
                ?float $qty = null
            ): bool {
                return false;
            }

            public function deleteReservation(int $orderId, int $productId): void
            {
                $this->inner->deleteReservation($orderId, $productId);
            }

            public function runInTransaction(callable $work): void
            {
                $this->inner->runInTransaction($work);
            }
        };
        $inventory = new ProductStockInventory($store);

        $inventory->release(new InventoryKey(10), 1, new InventoryContext(100));

        self::assertSame(4.0, $inventory->getAvailable(new InventoryKey(10)));
    }

    private function makeInventory(float $stock): ProductStockInventory
    {
        $store = new InMemoryInventoryStockStore();
        $store->seedStock(10, $stock);

        return new ProductStockInventory($store);
    }
}
