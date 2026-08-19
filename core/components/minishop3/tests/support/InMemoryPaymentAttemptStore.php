<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Support;

use MiniShop3\Services\Payment\PaymentAttemptStoreInterface;

/**
 * In-process payment attempt store for unit tests.
 *
 * @phpstan-import-type PaymentAttemptRow from PaymentAttemptStoreInterface
 */
final class InMemoryPaymentAttemptStore implements PaymentAttemptStoreInterface
{
    /** @var array<int, PaymentAttemptRow> */
    private array $attempts = [];

    /** @var array<string, true> */
    private array $events = [];

    private int $nextId = 1;

    public function create(
        int $orderId,
        int $paymentMethodId,
        string $provider,
        ?string $externalId,
        string $status,
        float $amount,
        string $currency,
        array $payload,
    ): array {
        if ($externalId !== null && $this->findByExternalId($provider, $externalId, $paymentMethodId) !== null) {
            throw new \RuntimeException('duplicate external_id');
        }
        $now = time();
        $row = [
            'id' => $this->nextId++,
            'order_id' => $orderId,
            'payment_method_id' => $paymentMethodId,
            'provider' => $provider,
            'external_id' => $externalId,
            'status' => $status,
            'amount' => round($amount, 3),
            'currency' => $currency,
            'payload' => $payload,
            'refunded_amount' => 0.0,
            'refund_external_id' => null,
            'refundedon' => null,
            'createdon' => $now,
            'updatedon' => $now,
        ];
        $this->attempts[$row['id']] = $row;

        return $row;
    }

    public function update(int $id, array $fields): array
    {
        $row = $this->attempts[$id] ?? null;
        if ($row === null) {
            throw new \RuntimeException('attempt not found');
        }
        foreach ($fields as $key => $value) {
            if ($key === 'id' || $key === 'createdon') {
                continue;
            }
            $row[$key] = $value;
        }
        $row['updatedon'] = time();
        $this->attempts[$id] = $row;

        return $row;
    }

    public function findById(int $id): ?array
    {
        return $this->attempts[$id] ?? null;
    }

    public function findByExternalId(string $provider, string $externalId, ?int $paymentMethodId = null): ?array
    {
        foreach ($this->attempts as $row) {
            if ($row['provider'] !== $provider || $row['external_id'] !== $externalId) {
                continue;
            }
            if ($paymentMethodId !== null && $row['payment_method_id'] !== $paymentMethodId) {
                continue;
            }

            return $row;
        }

        return null;
    }

    public function findLatestForOrder(int $orderId, ?int $paymentMethodId = null): ?array
    {
        $latest = null;
        foreach ($this->attempts as $row) {
            if ($row['order_id'] !== $orderId) {
                continue;
            }
            if ($paymentMethodId !== null && $row['payment_method_id'] !== $paymentMethodId) {
                continue;
            }
            if ($latest === null || $row['id'] > $latest['id']) {
                $latest = $row;
            }
        }

        return $latest;
    }

    public function recordEvent(int $attemptId, string $eventType, string $providerEventId): bool
    {
        $key = $attemptId . ':' . $eventType . ':' . $providerEventId;
        if (isset($this->events[$key])) {
            return false;
        }
        $this->events[$key] = true;

        return true;
    }

    public function hasEvent(int $attemptId, string $eventType, string $providerEventId): bool
    {
        return isset($this->events[$attemptId . ':' . $eventType . ':' . $providerEventId]);
    }

    public function writeWithEvent(int $id, string $eventType, string $providerEventId, array $fields): array
    {
        if ($this->hasEvent($id, $eventType, $providerEventId)) {
            $row = $this->findById($id);
            if ($row === null) {
                throw new \RuntimeException('attempt not found');
            }

            return $row;
        }
        $row = $fields === [] ? $this->findById($id) : $this->update($id, $fields);
        if ($row === null) {
            throw new \RuntimeException('attempt not found');
        }
        $this->recordEvent($id, $eventType, $providerEventId);

        return $row;
    }
}
