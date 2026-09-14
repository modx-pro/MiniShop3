<?php

declare(strict_types=1);

namespace MiniShop3\Controllers\Delivery;

final class ShipmentWebhookEvent
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly string $eventType,
        public readonly ?int $orderId = null,
        public readonly ?string $externalId = null,
        public readonly ?string $trackingNumber = null,
        public readonly ?string $providerEventId = null,
        public readonly ?string $carrier = null,
        public readonly array $payload = [],
    ) {
    }
}
