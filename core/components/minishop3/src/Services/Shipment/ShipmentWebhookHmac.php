<?php

declare(strict_types=1);

namespace MiniShop3\Services\Shipment;

use MiniShop3\Model\msDelivery;

/**
 * Reference HMAC-SHA256 check for async delivery callbacks.
 * Providers call this from verifyWebhook() with the raw body and a secret
 * from msDelivery.properties (secret / secret_key / webhook_secret).
 */
final class ShipmentWebhookHmac
{
    public static function verify(string $rawBody, string $signature, string $secret): bool
    {
        if ($rawBody === '' || $signature === '' || $secret === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature);
    }

    public static function secretFrom(msDelivery $method): string
    {
        $properties = $method->get('properties');
        if (!is_array($properties)) {
            return '';
        }
        foreach (['secret', 'secret_key', 'webhook_secret'] as $key) {
            $value = $properties[$key] ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return '';
    }
}
