<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Integration\WebApi\Support;

use MiniShop3\Services\Customer\CustomerOrderService;
use MODX\Revolution\modX;

/**
 * Stub CustomerOrderService for cabinet order list/get after submit.
 */
final class JourneyCustomerOrderService extends CustomerOrderService
{
    /** @var array<int, array<string, mixed>> */
    private array $orders = [];

    public function __construct(modX $modx)
    {
        parent::__construct($modx);
    }

    public function seedOrder(int $orderId, int $customerId, array $fields = []): void
    {
        $this->orders[$orderId] = array_merge([
            'id' => $orderId,
            'customer_id' => $customerId,
            'cost' => 150.0,
            'status_id' => 1,
        ], $fields);
    }

    public function getDraftStatusId(): int
    {
        return 0;
    }

    /**
     * @return array{total: int, limit: int, offset: int, results: list<array<string, mixed>>}
     */
    public function listForCustomer(int $customerId, int $limit, int $offset, ?int $statusId = null): array
    {
        unset($statusId);
        $owned = array_values(array_filter(
            $this->orders,
            static fn (array $row): bool => (int) $row['customer_id'] === $customerId
        ));

        return [
            'total' => count($owned),
            'limit' => $limit,
            'offset' => $offset,
            'results' => array_slice($owned, $offset, $limit),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getForCustomer(int $customerId, int $orderId): ?array
    {
        $row = $this->orders[$orderId] ?? null;
        if ($row === null || (int) $row['customer_id'] !== $customerId) {
            return null;
        }

        return $row;
    }
}
