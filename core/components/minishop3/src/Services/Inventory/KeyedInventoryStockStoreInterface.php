<?php

namespace MiniShop3\Services\Inventory;

/**
 * Optional variant-aware extension of the original store contract (#1001).
 *
 * Implement this instead of only InventoryStockStoreInterface when the store
 * can distinguish SKU rows in the reservation ledger. Legacy stores keep
 * implementing the original interface and are adapted automatically by
 * InventoryStockStoreKeyAdapter; they simply cannot split stock by variant.
 *
 * @phpstan-import-type ReservationRow from InventoryStockStoreInterface
 */
interface KeyedInventoryStockStoreInterface extends InventoryStockStoreInterface
{
    public function getAvailableByKey(InventoryKey $key): float;

    public function tryDecrementByKey(InventoryKey $key, float $qty): bool;

    public function decrementByKey(InventoryKey $key, float $qty): bool;

    public function incrementByKey(InventoryKey $key, float $qty): void;

    /**
     * @return list<ReservationRow>
     */
    public function listReservations(int $orderId): array;

    /**
     * @return ReservationRow|null
     */
    public function findReservationByKey(int $orderId, InventoryKey $key): ?array;

    /**
     * @return ReservationRow|null
     */
    public function lockReservationByKey(int $orderId, InventoryKey $key): ?array;

    public function saveReservationByKey(int $orderId, InventoryKey $key, float $qty, string $state): void;

    public function insertReservationByKey(int $orderId, InventoryKey $key, float $qty, string $state): bool;

    public function transitionReservationByKey(
        int $orderId,
        InventoryKey $key,
        string $fromState,
        string $toState,
        ?float $qty = null
    ): bool;

    public function deleteReservationByKey(int $orderId, InventoryKey $key): void;

    /**
     * Adopt a legacy product-level hold on first variant use. Stores that do
     * not support variants return false (see InventoryStockStoreKeyAdapter).
     */
    public function rekeyReservation(int $orderId, InventoryKey $from, InventoryKey $to): bool;
}
