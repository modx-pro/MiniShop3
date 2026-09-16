<?php

declare(strict_types=1);

namespace MiniShop3\Services\Payment;

/**
 * Reference HMAC-SHA256 check for async payment callbacks.
 * Providers call this from verifyWebhook() with the raw body and a secret
 * from msPayment.properties (secret / secret_key / webhook_secret).
 */
final class PaymentWebhookHmac
{
    public static function verify(string $rawBody, string $signature, string $secret): bool
    {
        if ($rawBody === '' || $signature === '' || $secret === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature);
    }
}
