<?php

declare(strict_types=1);

namespace MiniShop3\Services\Catalog;

/**
 * Shared query-param helpers for public Web API catalog services (product / category).
 */
final class CatalogQuery
{
    public const DEFAULT_LIMIT = 20;
    public const MAX_LIMIT = 100;
    public const DEFAULT_DEPTH = 5;
    public const MAX_DEPTH = 10;
    public const MAX_CONTEXT_LENGTH = 100;

    /**
     * @param array<string, mixed> $params
     */
    public static function resolveLimit(array $params): int
    {
        $limit = (int) ($params['limit'] ?? self::DEFAULT_LIMIT);
        if ($limit < 1) {
            return self::DEFAULT_LIMIT;
        }

        return min($limit, self::MAX_LIMIT);
    }

    /**
     * @param array<string, mixed> $params
     */
    public static function resolveOffset(array $params, int $limit): int
    {
        if (isset($params['offset']) && $params['offset'] !== '') {
            return max(0, (int) $params['offset']);
        }

        $page = max(1, (int) ($params['page'] ?? 1));

        return ($page - 1) * $limit;
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, string> $sortMap request key => SQL expression
     * @return array{0: string, 1: string} SQL field, ASC|DESC
     */
    public static function resolveSort(array $params, array $sortMap, string $defaultKey = 'menuindex'): array
    {
        $sortKey = strtolower(trim((string) ($params['sort'] ?? $defaultKey)));
        $sortField = $sortMap[$sortKey] ?? $sortMap[$defaultKey] ?? reset($sortMap);
        if (!is_string($sortField) || $sortField === '') {
            $sortField = $defaultKey;
        }

        $dir = strtoupper(trim((string) ($params['dir'] ?? $params['sortdir'] ?? 'ASC')));
        if ($dir !== 'DESC') {
            $dir = 'ASC';
        }

        return [$sortField, $dir];
    }

    /**
     * @param array<string, mixed> $params
     */
    public static function resolveDepth(array $params): int
    {
        $depth = (int) ($params['depth'] ?? self::DEFAULT_DEPTH);
        if ($depth < 1) {
            return self::DEFAULT_DEPTH;
        }

        return min($depth, self::MAX_DEPTH);
    }

    /**
     * Sanitize a MODX context key for catalog queries.
     *
     * Empty / whitespace input is treated as absent (returns null).
     * Non-empty invalid input throws CatalogContextException.
     *
     * @throws CatalogContextException
     */
    public static function sanitizeContext(?string $key): ?string
    {
        if ($key === null) {
            return null;
        }

        $key = trim($key);
        if ($key === '') {
            return null;
        }

        if (!self::isValidContextKey($key)) {
            throw CatalogContextException::invalid();
        }

        return $key;
    }

    /**
     * Empty / whitespace / absent context falls back (avoids unscoped cross-context reads).
     * Explicit non-empty invalid values throw CatalogContextException (HTTP 400).
     *
     * @param array<string, mixed> $params
     *
     * @throws CatalogContextException
     */
    public static function resolveContext(array $params, string $fallback = 'web'): string
    {
        if (!array_key_exists('context', $params)) {
            return self::resolveContextFallback($fallback);
        }

        $sanitized = self::sanitizeContextParam($params['context']);

        return $sanitized ?? self::resolveContextFallback($fallback);
    }

    /**
     * @throws CatalogContextException
     */
    private static function sanitizeContextParam(mixed $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        if (is_array($raw) || is_object($raw) || is_bool($raw)) {
            throw CatalogContextException::invalid();
        }

        if (!is_string($raw) && !is_int($raw) && !is_float($raw)) {
            throw CatalogContextException::invalid();
        }

        return self::sanitizeContext((string) $raw);
    }

    private static function isValidContextKey(string $key): bool
    {
        if (strlen($key) > self::MAX_CONTEXT_LENGTH) {
            return false;
        }

        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $key)) {
            return false;
        }

        return !str_starts_with(strtolower($key), 'mgr');
    }

    private static function resolveContextFallback(string $fallback): string
    {
        $fallback = trim($fallback);
        if ($fallback !== '' && self::isValidContextKey($fallback)) {
            return $fallback;
        }

        return 'web';
    }

    public static function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * @param array<string, mixed> $params
     */
    public static function resolveBool(array $params, string $key, bool $default): bool
    {
        if (!array_key_exists($key, $params)) {
            return $default;
        }

        return self::toBool($params[$key]);
    }
}
