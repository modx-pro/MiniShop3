<?php

declare(strict_types=1);

namespace MiniShop3\Services\Payment;

/**
 * Persistence for payment attempts and idempotent webhook events.
 *
 * @phpstan-type PaymentAttemptRow array{
 *     id: int,
 *     order_id: int,
 *     payment_method_id: int,
 *     provider: string,
 *     external_id: ?string,
 *     status: string,
 *     amount: float,
 *     currency: string,
 *     payload: array<string, mixed>,
 *     refunded_amount: float,
 *     refund_external_id: ?string,
 *     refundedon: ?int,
 *     createdon: int,
 *     updatedon: int
 * }
 */
interface PaymentAttemptStoreInterface
{
    /**
     * @param array<string, mixed> $payload
     * @return PaymentAttemptRow
     */
    public function create(
        int $orderId,
        int $paymentMethodId,
        string $provider,
        ?string $externalId,
        string $status,
        float $amount,
        string $currency,
        array $payload,
    ): array;

    /**
     * @param array<string, mixed> $fields
     * @return PaymentAttemptRow
     */
    public function update(int $id, array $fields): array;

    /**
     * @return PaymentAttemptRow|null
     */
    public function findById(int $id): ?array;

    /**
     * @return PaymentAttemptRow|null
     */
    public function findByExternalId(string $provider, string $externalId, ?int $paymentMethodId = null): ?array;

    /**
     * Latest attempt for the order, optionally filtered by method.
     *
     * @return PaymentAttemptRow|null
     */
    public function findLatestForOrder(int $orderId, ?int $paymentMethodId = null): ?array;

    /**
     * @return bool true if this event was recorded, false if it already existed
     */
    public function recordEvent(int $attemptId, string $eventType, string $providerEventId): bool;

    public function hasEvent(int $attemptId, string $eventType, string $providerEventId): bool;

    /**
     * Persist attempt fields and the idempotency event together.
     * If the event already exists, fields are not applied.
     *
     * @param array<string, mixed> $fields
     * @return PaymentAttemptRow
     */
    public function writeWithEvent(int $id, string $eventType, string $providerEventId, array $fields): array;
}
