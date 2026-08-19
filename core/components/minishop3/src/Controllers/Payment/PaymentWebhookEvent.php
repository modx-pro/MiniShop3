<?php

declare(strict_types=1);

namespace MiniShop3\Controllers\Payment;

/**
 * Parsed async callback from a payment provider.
 */
final class PaymentWebhookEvent
{
    /**
     * @param array<string, mixed> $payload Safe meta only (no credentials)
     */
    public function __construct(
        public readonly string $eventType,
        public readonly ?string $externalId = null,
        public readonly ?int $orderId = null,
        public readonly ?string $orderUuid = null,
        public readonly ?float $amount = null,
        public readonly ?string $currency = null,
        public readonly ?string $providerEventId = null,
        public readonly ?float $refundAmount = null,
        public readonly ?string $refundExternalId = null,
        public readonly array $payload = [],
    ) {
    }
}
