<?php

declare(strict_types=1);

namespace MiniShop3\Controllers\Delivery;

use MiniShop3\Model\msDelivery;

/**
 * Optional async fulfillment contract. Cost-only classes keep
 * DeliveryProviderInterface only and do not implement this.
 */
interface ShipmentProviderInterface
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     */
    public function verifyWebhook(string $rawBody, array $payload, array $headers, msDelivery $method): bool;

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     */
    public function parseWebhook(array $payload, array $headers): ?ShipmentWebhookEvent;
}
