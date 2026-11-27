<?php

declare(strict_types=1);

namespace MiniShop3\Services\Payment;

/**
 * Expiry metadata for stored payment links (#1063).
 *
 * Providers expose invoice expiry under different key spellings; all of them
 * are normalized to a Unix timestamp before a stored link is reused.
 */
final class PaymentLinkExpiry
{
    /** @var list<string> */
    private const KEYS = [
        'expirationDate',
        'expiration_date',
        'expiresAt',
        'expires_at',
        'payment_link_expiration_date',
        'payment_link_expires_at',
    ];

    /**
     * Pick expiry fields out of a provider response before it is persisted.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function extract(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            foreach (self::KEYS as $known) {
                if (strcasecmp($key, $known) === 0) {
                    $result[$key] = $value;
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $payload
     * @return int|null Unix timestamp, or null when the provider gave no expiry
     */
    public static function timestamp(array $payload): ?int
    {
        foreach (self::extract($payload) as $value) {
            $timestamp = self::parse($value);
            if ($timestamp !== null) {
                return $timestamp;
            }
        }

        return null;
    }

    private static function parse(mixed $value): ?int
    {
        if (is_int($value) || is_float($value)) {
            return (int) $value;
        }
        if (!is_string($value) || $value === '') {
            return null;
        }
        if (ctype_digit($value)) {
            return (int) $value;
        }
        $parsed = strtotime($value);

        // An unparseable expiry must not be treated as "valid forever".
        return $parsed === false ? 0 : $parsed;
    }
}
