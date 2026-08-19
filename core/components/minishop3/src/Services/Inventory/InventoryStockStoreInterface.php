<?php

namespace MiniShop3\Services\Inventory;

/**
 * Persistence for available stock and per-order reservation ledger.
 *
 * @phpstan-type ReservationRow array{order_id: int, product_id: int, qty: float, state: string}
 */
interface InventoryStockStoreInterface
{
    public function getAvailable(int $productId): float;

    /**
     * Atomic decrement. Returns false when stock is insufficient or the product row is missing.
     */
    public function tryDecrement(int $productId, float $qty): bool;

    public function increment(int $productId, float $qty): void;

    /**
     * @return ReservationRow|null
     */
    public function findReservation(int $orderId, int $productId): ?array;

    public function saveReservation(int $orderId, int $productId, float $qty, string $state): void;

    /**
     * Run $work in a DB transaction. Nested calls join an already open transaction.
     *
     * @param callable(): void $work
     */
    public function runInTransaction(callable $work): void;
}
