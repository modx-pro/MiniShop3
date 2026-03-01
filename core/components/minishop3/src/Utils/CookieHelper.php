<?php

namespace MiniShop3\Utils;

use MODX\Revolution\modX;

/**
 * Helper for httpOnly cookie management
 *
 * Provides secure cookie operations for ms3_token.
 * Uses MODX system settings for domain/path/secure/samesite.
 * httpOnly is always true (security requirement, not configurable).
 */
class CookieHelper
{
    private const COOKIE_NAME = 'ms3_token';

    /**
     * Set token cookie
     *
     * Uses MODX session_cookie_* settings for domain, path, secure, samesite.
     * httpOnly is always true — token must not be accessible from JS.
     * Lifetime from ms3_customer_token_ttl (default 604800 = 7 days).
     *
     * @param modX $modx MODX instance (for reading system settings)
     * @param string $token Token value
     * @param int|null $maxAge Max age in seconds (null = from ms3_customer_token_ttl)
     */
    public static function setTokenCookie(modX $modx, string $token, ?int $maxAge = null): void
    {
        if (empty($token)) {
            return;
        }

        if ($maxAge === null) {
            $maxAge = (int)$modx->getOption('ms3_customer_token_ttl', null, 604800);
        }

        $domain = (string)$modx->getOption('session_cookie_domain', null, '');
        $path = (string)$modx->getOption('session_cookie_path', null, '/');
        $secure = (bool)$modx->getOption('session_cookie_secure', null, false);
        $samesite = (string)$modx->getOption('session_cookie_samesite', null, 'Lax');

        setcookie(self::COOKIE_NAME, $token, [
            'expires' => time() + $maxAge,
            'path' => $path ?: '/',
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => true,
            'samesite' => $samesite ?: 'Lax',
        ]);

        // Make cookie available in current request immediately
        $_COOKIE[self::COOKIE_NAME] = $token;
    }

    /**
     * Clear token cookie
     *
     * @param modX $modx MODX instance
     */
    public static function clearTokenCookie(modX $modx): void
    {
        $domain = (string)$modx->getOption('session_cookie_domain', null, '');
        $path = (string)$modx->getOption('session_cookie_path', null, '/');
        $secure = (bool)$modx->getOption('session_cookie_secure', null, false);
        $samesite = (string)$modx->getOption('session_cookie_samesite', null, 'Lax');

        setcookie(self::COOKIE_NAME, '', [
            'expires' => time() - 3600,
            'path' => $path ?: '/',
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => true,
            'samesite' => $samesite ?: 'Lax',
        ]);

        unset($_COOKIE[self::COOKIE_NAME]);
    }

    /**
     * Get token from cookie
     *
     * @return string Token or empty string
     */
    public static function getTokenFromCookie(): string
    {
        return $_COOKIE[self::COOKIE_NAME] ?? '';
    }
}
