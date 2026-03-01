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

    /** @var string|null Token value set in this PHP request (prevents duplicate Set-Cookie headers, allows token change) */
    private static ?string $lastTokenSet = null;

    /**
     * Set token cookie (sliding expiry)
     *
     * Uses MODX session_cookie_* settings for domain, path, secure, samesite.
     * httpOnly is always true — token must not be accessible from JS.
     * Lifetime from ms3_customer_token_ttl (default 604800 = 7 days).
     *
     * Deduplication: skips if same token was already set in this PHP request.
     * Allows token change (e.g. Logout sets new token after middleware set old one).
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

        // Skip if same token already set in this request (avoids duplicate Set-Cookie headers)
        // Allows token change (e.g. Logout creates new token after middleware set old one)
        if (self::$lastTokenSet === $token) {
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

        self::$lastTokenSet = $token;

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
        self::$lastTokenSet = null;
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
