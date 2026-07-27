<?php

namespace MiniShop3\Utils;

/**
 * Pure helpers for API token expiry checks (no MODX/xPDO).
 */
final class ApiTokenExpiry
{
    /**
     * Whether expires_at is strictly before $timestamp.
     *
     * Invalid/unparseable dates are treated as expired.
     */
    public static function isExpiresAtBefore(string $expiresAt, int $timestamp): bool
    {
        $expires = strtotime($expiresAt);
        if ($expires === false) {
            return true;
        }

        return $expires < $timestamp;
    }
}
