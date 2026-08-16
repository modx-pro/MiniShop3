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
     * Empty / whitespace context falls back (avoids unscoped cross-context reads).
     *
     * @param array<string, mixed> $params
     */
    public static function resolveContext(array $params, string $fallback = 'web'): string
    {
        $context = trim((string) ($params['context'] ?? ''));
        if ($context !== '') {
            return $context;
        }

        $fallback = trim($fallback);

        return $fallback !== '' ? $fallback : 'web';
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
