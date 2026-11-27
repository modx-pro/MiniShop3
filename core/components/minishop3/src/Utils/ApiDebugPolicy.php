<?php

declare(strict_types=1);

namespace MiniShop3\Utils;

use MODX\Revolution\modX;

/**
 * Policy for exposing exception details in API responses (#931).
 *
 * The global MODX `debug` setting is not enough: it is often left enabled
 * accidentally and must never disclose file paths, line numbers or traces to
 * anonymous Web API clients. Both the dedicated MiniShop3 setting and an
 * authenticated manager session are required.
 *
 * `isAuthenticated('mgr')` alone can be true via `$_SESSION['mgrValidated']`
 * for a web user sharing a PHP session with a manager, so the manager identity
 * from the context token is checked too.
 */
final class ApiDebugPolicy
{
    public static function allowed(modX $modx): bool
    {
        if (!$modx->getOption('ms3_api_debug', null, false)) {
            return false;
        }

        $user = $modx->user;
        if ($user === null || !$user->isAuthenticated('mgr') || !$user->hasSessionContext('mgr')) {
            return false;
        }

        $managerId = (int) ($_SESSION['modx.user.contextTokens']['mgr'] ?? 0);

        return $managerId > 0 && (int) $user->get('id') === $managerId;
    }
}
