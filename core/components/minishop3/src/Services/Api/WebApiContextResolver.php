<?php

declare(strict_types=1);

namespace MiniShop3\Services\Api;

/**
 * Resolve a safe front-end context key for Web API bootstrap (api.php).
 *
 * Client-supplied ctx is sanitized (charset, length, no mgr*), then applied via
 * switchContext so lexicon cultureKey matches the page that issued the request.
 * Existence is whatever switchContext accepts after initialize('web').
 */
final class WebApiContextResolver
{
    public const DEFAULT_CONTEXT = 'web';

    private const MAX_KEY_LENGTH = 100;

    /**
     * Sanitize client ctx to a candidate key (does not switch).
     */
    public static function resolve(?string $requested): string
    {
        return self::sanitize($requested) ?? self::DEFAULT_CONTEXT;
    }

    /**
     * Apply resolved context after a default initialize('web') bootstrap.
     *
     * @param object $modx Runtime MODX (duck-typed in unit tests)
     * @return string Context key actually active for the request
     */
    public static function apply(object $modx, ?string $requested): string
    {
        $current = $modx->context->key ?? self::DEFAULT_CONTEXT;
        $ctx = self::resolve($requested);

        if ($ctx === $current) {
            return $current;
        }

        if (!$modx->switchContext($ctx)) {
            return $current;
        }

        return $modx->context->key ?? self::DEFAULT_CONTEXT;
    }

    private static function sanitize(?string $requested): ?string
    {
        if ($requested === null) {
            return null;
        }

        $key = trim($requested);
        if ($key === '' || strlen($key) > self::MAX_KEY_LENGTH) {
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
}
