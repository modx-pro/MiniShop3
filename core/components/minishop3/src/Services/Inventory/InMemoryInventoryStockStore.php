<?php

namespace MiniShop3\Services\Inventory;

/**
 * In-process store for unit tests and contract fakes. Not for production checkouts.
 */
final class InMemoryInventoryStockStore implements InventoryStockStoreInterface
{
    /** @var array<int, float> */
    private array $stock = [];

    /**
     * @var array<string, array{order_id: int, product_id: int, qty: float, state: string}>
     */
    private array $reservations = [];

    public function seedStock(int $productId, float $qty): void
    {
        $this->stock[$productId] = round($qty, 3);
    }

    public function getAvailable(int $productId): float
    {
        return $this->stock[$productId] ?? 0.0;
    }

    public function tryDecrement(int $productId, float $qty): bool
    {
        $available = $this->getAvailable($productId);
        if ($available < $qty) {
            return false;
        }
        $this->stock[$productId] = round($available - $qty, 3);

        return true;
    }

    public function increment(int $productId, float $qty): void
    {
        $this->stock[$productId] = round($this->getAvailable($productId) + $qty, 3);
    }

    public function findReservation(int $orderId, int $productId): ?array
    {
        return $this->reservations[$this->reservationKey($orderId, $productId)] ?? null;
    }

    public function saveReservation(int $orderId, int $productId, float $qty, string $state): void
    {
        $this->reservations[$this->reservationKey($orderId, $productId)] = [
            'order_id' => $orderId,
            'product_id' => $productId,
            'qty' => round($qty, 3),
            'state' => $state,
        ];
    }

    private function reservationKey(int $orderId, int $productId): string
    {
        return $orderId . ':' . $productId;
    }
}
