<?php

namespace MiniShop3\Services\Inventory;

/**
 * Adapts a pre-#1001 int-product store to the variant-aware store contract.
 *
 * Every keyed call is degraded to product-level: the variant part is dropped,
 * giving exactly the accounting a third-party store provided before variant
 * keys existed. Core stores (PdoInventoryStockStore) implement the keyed
 * interface directly and are never wrapped.
 *
 * @phpstan-import-type ReservationRow from InventoryStockStoreInterface
 */
final class InventoryStockStoreKeyAdapter implements KeyedInventoryStockStoreInterface
{
    public function __construct(private readonly InventoryStockStoreInterface $store)
    {
    }

    public function getAvailable(int $productId): float
    {
        return $this->store->getAvailable($productId);
    }

    public function getAvailableByKey(InventoryKey $key): float
    {
        return $this->store->getAvailable($key->productId);
    }

    public function tryDecrement(int $productId, float $qty): bool
    {
        return $this->store->tryDecrement($productId, $qty);
    }

    public function tryDecrementByKey(InventoryKey $key, float $qty): bool
    {
        return $this->store->tryDecrement($key->productId, $qty);
    }

    public function decrement(int $productId, float $qty): bool
    {
        return $this->store->decrement($productId, $qty);
    }

    public function decrementByKey(InventoryKey $key, float $qty): bool
    {
        return $this->store->decrement($key->productId, $qty);
    }

    public function increment(int $productId, float $qty): void
    {
        $this->store->increment($productId, $qty);
    }

    public function incrementByKey(InventoryKey $key, float $qty): void
    {
        $this->store->increment($key->productId, $qty);
    }

    /**
     * @return list<ReservationRow>
     */
    public function listReservations(int $orderId): array
    {
        return $this->store->listReservations($orderId);
    }

    /**
     * @return ReservationRow|null
     */
    public function findReservation(int $orderId, int $productId): ?array
    {
        return $this->store->findReservation($orderId, $productId);
    }

    /**
     * @return ReservationRow|null
     */
    public function findReservationByKey(int $orderId, InventoryKey $key): ?array
    {
        return $this->store->findReservation($orderId, $key->productId);
    }

    /**
     * @return ReservationRow|null
     */
    public function lockReservation(int $orderId, int $productId): ?array
    {
        return $this->store->lockReservation($orderId, $productId);
    }

    /**
     * @return ReservationRow|null
     */
    public function lockReservationByKey(int $orderId, InventoryKey $key): ?array
    {
        return $this->store->lockReservation($orderId, $key->productId);
    }

    public function saveReservation(int $orderId, int $productId, float $qty, string $state): void
    {
        $this->store->saveReservation($orderId, $productId, $qty, $state);
    }

    public function saveReservationByKey(int $orderId, InventoryKey $key, float $qty, string $state): void
    {
        $this->store->saveReservation($orderId, $key->productId, $qty, $state);
    }

    public function insertReservation(int $orderId, int $productId, float $qty, string $state): bool
    {
        return $this->store->insertReservation($orderId, $productId, $qty, $state);
    }

    public function insertReservationByKey(int $orderId, InventoryKey $key, float $qty, string $state): bool
    {
        return $this->store->insertReservation($orderId, $key->productId, $qty, $state);
    }

    public function transitionReservation(
        int $orderId,
        int $productId,
        string $fromState,
        string $toState,
        ?float $qty = null
    ): bool {
        return $this->store->transitionReservation($orderId, $productId, $fromState, $toState, $qty);
    }

    public function transitionReservationByKey(
        int $orderId,
        InventoryKey $key,
        string $fromState,
        string $toState,
        ?float $qty = null
    ): bool {
        return $this->store->transitionReservation($orderId, $key->productId, $fromState, $toState, $qty);
    }

    public function deleteReservation(int $orderId, int $productId): void
    {
        $this->store->deleteReservation($orderId, $productId);
    }

    public function deleteReservationByKey(int $orderId, InventoryKey $key): void
    {
        $this->store->deleteReservation($orderId, $key->productId);
    }

    public function rekeyReservation(int $orderId, InventoryKey $from, InventoryKey $to): bool
    {
        return false;
    }

    public function runInTransaction(callable $work): void
    {
        $this->store->runInTransaction($work);
    }
}
