<?php

declare(strict_types=1);

namespace MiniShop3\Services;

/**
 * Shared MODX context-key charset rules for Web API (#722).
 *
 * Used by catalog query sanitization and api.php context switch.
 * Callers own trim / empty / error vs fallback policy.
 */
final class ContextKey
{
    public const MAX_LENGTH = 100;

    /**
     * Whether a non-empty trimmed key is allowed (length, charset, no mgr*).
     */
    public static function isValid(string $key): bool
    {
        if ($key === '' || strlen($key) > self::MAX_LENGTH) {
            return false;
        }

        return preg_match('/^[a-zA-Z0-9_-]+$/', $key) === 1
            && !str_starts_with(strtolower($key), 'mgr');
    }
}
