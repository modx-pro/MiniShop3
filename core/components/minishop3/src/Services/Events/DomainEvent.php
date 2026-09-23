<?php

declare(strict_types=1);

namespace MiniShop3\Services\Events;

use JsonSerializable;
use Ramsey\Uuid\Uuid;

/**
 * Immutable outbound domain event envelope for webhooks and listeners.
 */
final readonly class DomainEvent implements JsonSerializable
{
    private function __construct(
        private string $eventId,
        private string $eventType,
        private string $createdAt,
        private array $data,
    ) {
    }

    public static function create(string $eventType, array $data): self
    {
        return new self(
            Uuid::uuid4()->toString(),
            $eventType,
            gmdate('Y-m-d\TH:i:s\Z'),
            $data,
        );
    }

    /**
     * Allowlisted payload for {@see DomainEventCatalog::ORDER_STATUS_CHANGED}.
     * Callers must not pass payment/delivery secrets, tokens, or raw order rows.
     */
    public static function orderStatusChanged(
        int $orderId,
        string $orderUuid,
        ?int $oldStatusId,
        int $newStatusId,
        float $cost,
        float $cartCost,
        float $deliveryCost,
    ): self {
        return self::create(DomainEventCatalog::ORDER_STATUS_CHANGED, [
            'order_id' => $orderId,
            'order_uuid' => $orderUuid,
            'old_status_id' => $oldStatusId,
            'new_status_id' => $newStatusId,
            'cost' => $cost,
            'cart_cost' => $cartCost,
            'delivery_cost' => $deliveryCost,
        ]);
    }

    public function eventId(): string
    {
        return $this->eventId;
    }

    public function eventType(): string
    {
        return $this->eventType;
    }

    public function createdAt(): string
    {
        return $this->createdAt;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->data;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'event_type' => $this->eventType,
            'created_at' => $this->createdAt,
            'data' => $this->data,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
