<?php

declare(strict_types=1);

namespace MiniShop3\Services\Catalog;

use MODX\Revolution\modX;

/**
 * Lookup param parsing for public Web API catalog resolve (alias OR uri + context).
 */
final class CatalogResolve
{
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
        // Table alias for namespaced models is the short class name; FQCN breaks SELECT.
        $query->select('id');
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
     *
     * @throws CatalogContextException
     */
    public static function parseLookup(array $params, string $contextFallback = 'web'): array
    {
        foreach (['alias', 'uri'] as $key) {
            if (array_key_exists($key, $params) && !self::isScalarLookupParam($params[$key])) {
                return ['ok' => false, 'error' => 'invalid'];
            }
        }

        $alias = array_key_exists('alias', $params) ? trim((string) $params['alias']) : null;
        $uri = array_key_exists('uri', $params) ? trim((string) $params['uri']) : null;

        $hasAlias = $alias !== null && $alias !== '';
        $hasUri = $uri !== null && $uri !== '';

        if ($hasAlias && $hasUri) {
            return ['ok' => false, 'error' => 'conflict'];
        }

        if (!$hasAlias && !$hasUri) {
            return ['ok' => false, 'error' => 'required'];
        }

        $context = CatalogQuery::resolveContext($params, $contextFallback);

        if ($hasAlias) {
            if (self::containsUriRejectPattern($alias)) {
                return ['ok' => false, 'error' => 'invalid'];
            }

            return [
                'ok' => true,
                'field' => 'alias',
                'value' => $alias,
                'context' => $context,
            ];
        }

        $normalizedUri = self::normalizeUri($uri ?? '');
        if ($normalizedUri === null) {
            return ['ok' => false, 'error' => 'invalid'];
        }

        return [
            'ok' => true,
            'field' => 'uri',
            'value' => $normalizedUri,
            'context' => $context,
        ];
    }

    /**
     * Lexicon key for a failed {@see parseLookup()} result.
     */
    public static function lookupErrorLexiconKey(string $error): string
    {
        return match ($error) {
            'required' => 'ms3_err_catalog_lookup_required',
            'conflict' => 'ms3_err_catalog_lookup_conflict',
            'invalid' => 'ms3_err_catalog_lookup_invalid',
        };
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

    private static function isScalarLookupParam(mixed $value): bool
    {
        return is_string($value) || is_int($value);
    }

    private static function containsUriRejectPattern(string $value): bool
    {
        return str_contains($value, '..')
            || str_contains($value, '://')
            || str_contains($value, "\0")
            || str_contains($value, '//');
    }
}
