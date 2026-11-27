<?php

namespace MiniShop3\Utils;

use MODX\Revolution\modX;

/**
 * Stores API bearer tokens as SHA-256 hashes outside ms3_customer_tokens (#1058).
 *
 * Order drafts and customer rows historically kept the same bearer token in
 * plain text, so a database dump still contained working credentials. Both
 * columns now keep a prefixed `sha256$<hex>` value. The prefix makes the
 * migration idempotent (it only converts bare 64-hex plaintext tokens) and
 * keeps the legacy plaintext fallback unambiguous.
 */
final class StoredTokenHash
{
    private const PREFIX = 'sha256$';

    public static function hash(string $token): string
    {
        return self::PREFIX . hash('sha256', $token);
    }

    /** Store the hash instead of the request plaintext. */
    public static function set(object $object, string $token, string $field = 'token'): void
    {
        $object->set($field, self::hash($token));
    }

    /**
     * Find a row by token: hash first, bare plaintext as a legacy fallback.
     *
     * No lazy upgrade here: these are read paths (and order save fires events),
     * so rewriting rows happens only in the migration or explicit write paths.
     *
     * @param class-string $className
     * @param array<string, mixed> $criteria
     */
    public static function find(
        modX $modx,
        string $className,
        string $token,
        array $criteria = [],
        string $field = 'token'
    ): ?object {
        if ($token === '') {
            return null;
        }

        return $modx->getObject($className, array_merge($criteria, [
            $field => self::hash($token),
        ])) ?? $modx->getObject($className, array_merge($criteria, [
            $field => $token,
        ]));
    }
}
