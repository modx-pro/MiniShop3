<?php

declare(strict_types=1);

namespace MiniShop3\Controllers\Payment;

use MiniShop3\Model\msPayment;

/**
 * Optional webhook contract. Existing PaymentProviderInterface classes stay valid
 * without implementing this; they keep their own callback URLs.
 */
interface PaymentWebhookHandlerInterface
{
    /**
     * Verify provider signature against the raw request body (HMAC needs bytes,
     * not the decoded array).
     *
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     */
    public function verifyWebhook(string $rawBody, array $payload, array $headers, msPayment $method): bool;

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     */
    public function parseWebhook(array $payload, array $headers): ?PaymentWebhookEvent;
}
