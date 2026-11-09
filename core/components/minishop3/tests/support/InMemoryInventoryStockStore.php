<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Support;

use MiniShop3\Services\Inventory\InventoryKey;
use MiniShop3\Services\Inventory\InventoryStockStoreInterface;

/**
 * In-process store for unit tests and contract fakes.
 */
final class InMemoryInventoryStockStore implements InventoryStockStoreInterface
{
    /** @var array<int, float> */
    private array $stock = [];

    /**
     * @var array<string, array{order_id: int, product_id: int, variant_key: ?string, qty: float, state: string}>
     */
    private array $reservations = [];

    public function seedStock(int $productId, float $qty): void
    {
        $this->stock[$productId] = round($qty, 3);
    }

    public function getAvailable(InventoryKey $key): float
    {
        return $this->stock[$key->productId] ?? 0.0;
    }

    public function tryDecrement(InventoryKey $key, float $qty): bool
    {
        $available = $this->getAvailable($key);
        if ($available < $qty) {
            return false;
        }
        $this->stock[$key->productId] = round($available - $qty, 3);

        return true;
    }

    public function decrement(InventoryKey $key, float $qty): bool
    {
        if (!array_key_exists($key->productId, $this->stock)) {
            return false;
        }
        $this->stock[$key->productId] = round($this->stock[$key->productId] - $qty, 3);

        return true;
    }

    public function increment(InventoryKey $key, float $qty): void
    {
        $this->stock[$key->productId] = round($this->getAvailable($key) + $qty, 3);
    }

    public function findReservation(int $orderId, InventoryKey $key): ?array
    {
        return $this->reservations[$this->reservationKey($orderId, $key)] ?? null;
    }

    public function lockReservation(int $orderId, InventoryKey $key): ?array
    {
        return $this->findReservation($orderId, $key);
    }

    public function listReservations(int $orderId): array
    {
        $rows = [];
        foreach ($this->reservations as $row) {
            if ($row['order_id'] === $orderId) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    public function saveReservation(int $orderId, InventoryKey $key, float $qty, string $state): void
    {
        $this->reservations[$this->reservationKey($orderId, $key)] = [
            'order_id' => $orderId,
            'product_id' => $key->productId,
            'variant_key' => $key->variantKey,
            'qty' => round($qty, 3),
            'state' => $state,
        ];
    }

    public function insertReservation(int $orderId, InventoryKey $key, float $qty, string $state): bool
    {
        $k = $this->reservationKey($orderId, $key);
        if (isset($this->reservations[$k])) {
            return false;
        }
        $this->saveReservation($orderId, $key, $qty, $state);

        return true;
    }

    public function transitionReservation(
        int $orderId,
        InventoryKey $key,
        string $fromState,
        string $toState,
        ?float $qty = null
    ): bool {
        $k = $this->reservationKey($orderId, $key);
        $row = $this->reservations[$k] ?? null;
        if ($row === null || $row['state'] !== $fromState) {
            return false;
        }
        $row['state'] = $toState;
        if ($qty !== null) {
            $row['qty'] = round($qty, 3);
        }
        $this->reservations[$k] = $row;

        return true;
    }

    public function deleteReservation(int $orderId, InventoryKey $key): void
    {
        unset($this->reservations[$this->reservationKey($orderId, $key)]);
    }

    public function runInTransaction(callable $work): void
    {
        $work();
    }

    private function reservationKey(int $orderId, InventoryKey $key): string
    {
        return $orderId . ':' . $key->identity();
    }
}
