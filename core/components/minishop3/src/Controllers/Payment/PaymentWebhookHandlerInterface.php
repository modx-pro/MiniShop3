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
     * Verify authenticity of the inbound webhook before any payment side effects.
     *
     * The core does not check signatures itself: this method is the gate. A stub
     * that always returns true lets a forged request reach resolveAttempt() and
     * potentially mark an order paid (lookup by external_id or order id from the
     * event). Prefer a per-method secret in msPayment.properties over a single
     * shared ms3_payment_secret when several providers are installed.
     *
     * Verify against the raw request body (HMAC needs bytes, not the decoded array).
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
