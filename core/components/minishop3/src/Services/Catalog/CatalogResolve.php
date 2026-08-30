<?php

declare(strict_types=1);

namespace MiniShop3\Services\Catalog;

use MODX\Revolution\modX;

/**
 * Lookup param parsing for public Web API catalog resolve (alias OR uri + context).
 */
final class CatalogResolve
{
    private const MAX_CONTEXT_LENGTH = 100;

    /**
     * Return resource id when exactly one row matches $criteria; 0 or 2+ rows → null.
     *
     * Used for alias lookup and for each uri variant (unique match among visibility criteria).
     *
     * @param array<string, mixed> $criteria
     */
    public static function findUniqueId(modX $modx, string $class, array $criteria): ?int
    {
        $query = $modx->newQuery($class, $criteria);
        $query->select($modx->getSelectColumns($class, $class, '', ['id']));
        $query->limit(2);

        if (!$query->prepare() || !$query->stmt->execute()) {
            return null;
        }

        $ids = [];
        while ($row = $query->stmt->fetch(\PDO::FETCH_ASSOC)) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return count($ids) === 1 ? $ids[0] : null;
    }

    /**
     * @param array<string, mixed> $params
     * @return array{ok: true, field: 'alias'|'uri', value: string, context: string}|array{ok: false, error: 'required'|'conflict'|'invalid'}
     */
    public static function parseLookup(array $params, string $contextFallback = 'web'): array
    {
        foreach (['alias', 'uri', 'context'] as $key) {
            if (array_key_exists($key, $params) && !self::isScalarLookupParam($params[$key])) {
                return ['ok' => false, 'error' => 'invalid'];
            }
        }

        $hasAlias = array_key_exists('alias', $params);
        $hasUri = array_key_exists('uri', $params);

        $aliasRaw = $hasAlias ? trim((string) $params['alias']) : '';
        $uriRaw = $hasUri ? trim((string) $params['uri']) : '';

        $aliasProvided = $hasAlias && $aliasRaw !== '';
        $uriProvided = $hasUri && $uriRaw !== '';

        if ($aliasProvided && $uriProvided) {
            return ['ok' => false, 'error' => 'conflict'];
        }

        if (!$aliasProvided && !$uriProvided) {
            return ['ok' => false, 'error' => 'required'];
        }

        $context = self::resolveLookupContext($params, $contextFallback);
        if ($context === null) {
            return ['ok' => false, 'error' => 'invalid'];
        }

        if ($aliasProvided) {
            if (!self::isValidAlias($aliasRaw)) {
                return ['ok' => false, 'error' => 'invalid'];
            }

            return [
                'ok' => true,
                'field' => 'alias',
                'value' => $aliasRaw,
                'context' => $context,
            ];
        }

        $uri = self::normalizeUri($uriRaw);
        if ($uri === null) {
            return ['ok' => false, 'error' => 'invalid'];
        }

        return [
            'ok' => true,
            'field' => 'uri',
            'value' => $uri,
            'context' => $context,
        ];
    }

    /**
     * Trim, strip leading slash, reject unsafe path segments.
     */
    public static function normalizeUri(string $uri): ?string
    {
        $uri = trim($uri);
        if ($uri === '') {
            return null;
        }

        $uri = ltrim($uri, '/');
        if ($uri === '' || self::containsUriRejectPattern($uri)) {
            return null;
        }

        return $uri;
    }

    /**
     * Exact uri first, then slash variant (with or without trailing slash).
     *
     * @return list<string>
     */
    public static function uriLookupVariants(string $normalizedUri): array
    {
        $variants = [$normalizedUri];

        if (str_ends_with($normalizedUri, '/')) {
            $trimmed = rtrim($normalizedUri, '/');
            if ($trimmed !== '') {
                $variants[] = $trimmed;
            }
        } else {
            $variants[] = $normalizedUri . '/';
        }

        return array_values(array_unique($variants));
    }

    /**
     * @param array<string, mixed> $params
     */
    private static function resolveLookupContext(array $params, string $fallback): ?string
    {
        if (array_key_exists('context', $params)) {
            $raw = trim((string) $params['context']);
            if ($raw !== '') {
                return self::sanitizeContext($raw);
            }
        }

        $resolved = CatalogQuery::resolveContext($params, $fallback);

        return self::sanitizeContext($resolved);
    }

    private static function isScalarLookupParam(mixed $value): bool
    {
        return is_string($value) || is_int($value);
    }

    private static function sanitizeContext(string $key): ?string
    {
        $key = trim($key);
        if ($key === '' || strlen($key) > self::MAX_CONTEXT_LENGTH) {
            return null;
        }

        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $key)) {
            return null;
        }

        if (str_starts_with(strtolower($key), 'mgr')) {
            return null;
        }

        return $key;
    }

    private static function isValidAlias(string $alias): bool
    {
        return $alias !== '' && !self::containsUriRejectPattern($alias);
    }

    private static function containsUriRejectPattern(string $value): bool
    {
        return str_contains($value, '..')
            || str_contains($value, '://')
            || str_contains($value, "\0")
            || str_contains($value, '//');
    }
}
