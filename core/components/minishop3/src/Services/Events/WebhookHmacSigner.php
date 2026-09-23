<?php

declare(strict_types=1);

namespace MiniShop3\Services\Events;

/**
 * HMAC-SHA256 signing for outbound MiniShop3 webhooks (addon HTTP clients).
 *
 * Request headers (document for receivers):
 * - X-MS3-Signature: lowercase hex digest of HMAC-SHA256 over "{timestamp}.{rawBody}"
 * - X-MS3-Timestamp: Unix timestamp in seconds; must be included in the signed payload
 *
 * Receivers should verify with {@see verify()} and dedupe deliveries by envelope event_id.
 */
final class WebhookHmacSigner
{
    public static function sign(string $rawBody, string $secret, int $timestamp): string
    {
        return hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);
    }

    public static function verify(
        string $rawBody,
        string $signature,
        string $secret,
        int $timestamp,
        int $maxSkewSeconds = 300
    ): bool {
        if ($rawBody === '' || $signature === '' || $secret === '') {
            return false;
        }

        if (abs(time() - $timestamp) > $maxSkewSeconds) {
            return false;
        }

        $expected = self::sign($rawBody, $secret, $timestamp);

        return hash_equals($expected, $signature);
    }
}
