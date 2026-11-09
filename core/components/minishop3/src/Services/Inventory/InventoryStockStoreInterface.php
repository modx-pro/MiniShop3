<?php

namespace MiniShop3\Services\Inventory;

/**
 * Persistence for available stock and per-order reservation ledger.
 *
 * Stock methods receive the whole {@see InventoryKey}; the core PDO store
 * reads/writes `ms3_products.stock` by product id and ignores the variant
 * part (variant stock lives in addons — see ProductStockInventory).
 *
 * Ledger methods are keyed by (order_id, InventoryKey): the `variant_key`
 * column distinguishes SKU-level holds of the same product. '' (empty
 * string in storage) means product-level, i.e. variantKey = null.
 *
 * @phpstan-type ReservationRow array{order_id: int, product_id: int, variant_key: ?string, qty: float, state: string}
 */
interface InventoryStockStoreInterface
{
    public function getAvailable(InventoryKey $key): float;

    /**
     * Atomic decrement. Returns false when stock is insufficient or the product row is missing.
     */
    public function tryDecrement(InventoryKey $key, float $qty): bool;

    /**
     * Decrement even when stock would become negative. False only when the product row is missing.
     */
    public function decrement(InventoryKey $key, float $qty): bool;

    public function increment(InventoryKey $key, float $qty): void;

    /**
     * @return list<ReservationRow>
     */
    public function listReservations(int $orderId): array;

    /**
     * @return ReservationRow|null
     */
    public function findReservation(int $orderId, InventoryKey $key): ?array;

    /**
     * Same as findReservation, but locks the row for the open transaction.
     *
     * @return ReservationRow|null
     */
    public function lockReservation(int $orderId, InventoryKey $key): ?array;

    public function saveReservation(int $orderId, InventoryKey $key, float $qty, string $state): void;

    /**
     * Insert a new ledger row. False when (order_id, key) already exists.
     */
    public function insertReservation(int $orderId, InventoryKey $key, float $qty, string $state): bool;

    /**
     * Move the ledger row from $fromState to $toState.
     * True only when this call updated the row. Stock changes must follow a true result.
     */
    public function transitionReservation(
        int $orderId,
        InventoryKey $key,
        string $fromState,
        string $toState,
        ?float $qty = null
    ): bool;

    public function deleteReservation(int $orderId, InventoryKey $key): void;

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
