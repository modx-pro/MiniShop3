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
     * Insert a new ledger row. False when (order_id, product_id) already exists.
     */
    public function insertReservation(int $orderId, int $productId, float $qty, string $state): bool;

    /**
     * Move the ledger row from $fromState to $toState.
     * True only when this call updated the row. Stock changes must follow a true result.
     */
    public function transitionReservation(
        int $orderId,
        int $productId,
        string $fromState,
        string $toState,
        ?float $qty = null
    ): bool;

    public function deleteReservation(int $orderId, int $productId): void;

    /**
     * Run $work in a DB transaction.
     *
     * When the PDO connection already has a transaction, $work joins it and this
     * call does not commit or roll back. Construct the PDO store with $modx->pdo:
     * xPDO has beginTransaction() but no inTransaction(), and a nested begin throws.
     *
     * @param callable(): void $work
     */
    public function runInTransaction(callable $work): void;
}
