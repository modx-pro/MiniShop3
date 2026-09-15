<?php

declare(strict_types=1);

namespace MiniShop3\Services\Api;

/**
 * Bootstrap helpers for assets/.../api.php (#720).
 *
 * Do not pass initialize() options that disable sessions: MODX merges them into
 * the context config and writes context.cache.php on a cold cache, which breaks
 * sessions for the whole web context (#725 review).
 */
final class WebApiModxBootstrap
{
    /**
     * Mark the PHP session as already started (SESSION_STATE_EXTERNAL) so
     * modX::_initSession skips creating a MODX DB session on OPTIONS preflight.
     */
    public static function prepareForInitialize(string $requestMethod): void
    {
        if (strtoupper($requestMethod) !== 'OPTIONS') {
            return;
        }

        $_SESSION = [];
    }
}
