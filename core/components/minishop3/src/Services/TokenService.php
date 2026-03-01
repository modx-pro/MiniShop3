<?php

namespace MiniShop3\Services;

use MiniShop3\Model\msCustomerToken;
use MiniShop3\Utils\CookieHelper;
use MODX\Revolution\modX;

/**
 * Service for secure token operations
 *
 * Provides:
 * - Cryptographically strong token generation
 * - TTL (Time-To-Live) for tokens
 * - Centralized secrets management
 * - Token validation and verification
 * - httpOnly cookie management for ms3_token
 */
class TokenService
{
    /** @var modX */
    protected modX $modx;

    /** @var string Token type: customer or snippet */
    const TYPE_CUSTOMER = 'customer';
    const TYPE_SNIPPET = 'snippet';

    /**
     * @param modX $modx
     */
    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * Generate cryptographically strong token for customer
     *
     * Works for both authorized customers (with customer_id),
     * and anonymous visitors (customer_id = 0).
     * Anonymous tokens are used for cart before registration/authorization.
     *
     * @param int|null $ttl TTL in seconds (null = from system settings)
     * @return array ['token' => string, 'expires' => int, 'lifetime' => int]
     */
    public function generateCustomerToken(?int $ttl = null): array
    {
        $existingToken = $this->getCustomerToken();
        if ($existingToken) {
            $expires = $_SESSION['ms3']['customer_token_expires'] ?? (time() + 86400);
            $lifetime = max(0, $expires - time());

            // Refresh cookie TTL
            CookieHelper::setTokenCookie($this->modx, $existingToken);

            return [
                'token' => $existingToken,
                'expires' => $expires,
                'lifetime' => $lifetime * 1000,
            ];
        }

        $customerId = (int)($_SESSION['ms3']['customer_id'] ?? 0);

        $token = bin2hex(random_bytes(32));

        if ($ttl === null) {
            $ttl = (int)$this->modx->getOption('ms3_customer_token_ttl', null, 604800);
        }

        $expiresAt = date('Y-m-d H:i:s', time() + $ttl);

        $tokenObj = $this->modx->newObject(\MiniShop3\Model\msCustomerToken::class);
        $tokenObj->set('customer_id', $customerId);
        $tokenObj->set('token', $token);
        $tokenObj->set('type', \MiniShop3\Model\msCustomerToken::TYPE_API);
        $tokenObj->set('expires_at', $expiresAt);
        $tokenObj->set('created_at', date('Y-m-d H:i:s'));

        if (!$tokenObj->save()) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                "[TokenService] Failed to save token to database"
            );
            return ['token' => '', 'expires' => 0, 'lifetime' => 0];
        }

        $_SESSION['ms3']['customer_token'] = $token;
        $_SESSION['ms3']['customer_token_expires'] = time() + $ttl;

        // Set httpOnly cookie
        CookieHelper::setTokenCookie($this->modx, $token);

        $this->modx->log(
            modX::LOG_LEVEL_INFO,
            "[TokenService] Generated customer token for customer_id={$customerId}, expires: " . $expiresAt
        );

        return [
            'token' => $token,
            'expires' => time() + $ttl,
            'lifetime' => $ttl * 1000,
        ];
    }

    /**
     * Resolve existing token or create new one
     *
     * Resolution chain:
     * 1. Session token → if valid, return
     * 2. Cookie token → verify in DB (msCustomerToken type=api), restore session, return
     * 3. Generate new token → set cookie + session, return
     *
     * @return string Token string
     */
    public function resolveOrCreateToken(): string
    {
        // 1. Check session
        $sessionToken = $this->getCustomerToken();
        if ($sessionToken) {
            CookieHelper::setTokenCookie($this->modx, $sessionToken);
            return $sessionToken;
        }

        // 2. Check cookie
        $cookieToken = CookieHelper::getTokenFromCookie();
        if (!empty($cookieToken)) {
            $tokenObj = $this->modx->getObject(msCustomerToken::class, [
                'token' => $cookieToken,
                'type' => msCustomerToken::TYPE_API,
            ]);

            if ($tokenObj) {
                // Auto-renew expired token
                if ($tokenObj->isExpired()) {
                    $ttl = (int)$this->modx->getOption('ms3_customer_token_ttl', null, 604800);
                    $tokenObj->set('expires_at', date('Y-m-d H:i:s', time() + $ttl));
                    $tokenObj->save();
                }

                // Restore session
                if (!isset($_SESSION['ms3'])) {
                    $_SESSION['ms3'] = [];
                }
                $_SESSION['ms3']['customer_token'] = $cookieToken;
                $_SESSION['ms3']['customer_token_expires'] = strtotime($tokenObj->get('expires_at'));

                $customerId = (int)$tokenObj->get('customer_id');
                if ($customerId > 0) {
                    $_SESSION['ms3']['customer_id'] = $customerId;
                }

                // Refresh cookie TTL
                CookieHelper::setTokenCookie($this->modx, $cookieToken);

                return $cookieToken;
            }
        }

        // 3. Generate new token
        $result = $this->generateCustomerToken();
        return $result['token'];
    }

    /**
     * Update existing customer token (extend TTL)
     *
     * @param string $token Existing token
     * @param int|null $ttl TTL in seconds
     * @return array ['token' => string, 'expires' => int]
     */
    public function updateCustomerToken(string $token, ?int $ttl = null): array
    {
        if (empty($token)) {
            return $this->generateCustomerToken($ttl);
        }

        if ($ttl === null) {
            $ttl = (int)$this->modx->getOption('ms3_customer_token_ttl', null, 604800);
        }

        $expires = time() + $ttl;

        $_SESSION['ms3']['customer_token'] = $token;
        $_SESSION['ms3']['customer_token_expires'] = $expires;

        $this->modx->log(
            modX::LOG_LEVEL_DEBUG,
            "[TokenService] Updated customer token, expires: " . date('Y-m-d H:i:s', $expires)
        );

        return [
            'token' => $token,
            'expires' => $expires,
            'lifetime' => $ttl * 1000,
        ];
    }

    /**
     * Get customer token from session (with validity check)
     *
     * @return string|null Token or null if expired/does not exist
     */
    public function getCustomerToken(): ?string
    {
        $token = $_SESSION['ms3']['customer_token'] ?? null;
        $expires = $_SESSION['ms3']['customer_token_expires'] ?? null;

        if (empty($token)) {
            return null;
        }

        if ($expires !== null && $expires < time()) {
            $this->modx->log(
                modX::LOG_LEVEL_INFO,
                "[TokenService] Customer token expired, clearing session"
            );

            unset($_SESSION['ms3']['customer_token']);
            unset($_SESSION['ms3']['customer_token_expires']);

            return null;
        }

        return $token;
    }

    /**
     * Validate customer token
     *
     * @param string $token Token to validate
     * @return bool True if token is valid
     */
    public function validateCustomerToken(string $token): bool
    {
        $sessionToken = $this->getCustomerToken();

        if ($sessionToken === null) {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }

    /**
     * Generate token for snippet (parameters caching)
     *
     * @param array $scriptProperties Snippet parameters
     * @return string Cache token
     */
    public function generateSnippetToken(array $scriptProperties): string
    {
        $secret = $this->getSnippetSecret();

        $token = 'ms3_' . hash('sha256', json_encode($scriptProperties) . $secret);

        return $token;
    }

    /**
     * Get secret for snippet tokens (or generate new one)
     *
     * @return string Secret
     */
    protected function getSnippetSecret(): string
    {
        $secret = $this->modx->getOption('ms3_snippet_token_secret', null, null);

        if (empty($secret)) {
            $secret = bin2hex(random_bytes(32));

            $setting = $this->modx->getObject('modSystemSetting', ['key' => 'ms3_snippet_token_secret']);

            if (!$setting) {
                $setting = $this->modx->newObject('modSystemSetting');
                $setting->set('key', 'ms3_snippet_token_secret');
                $setting->set('namespace', 'minishop3');
                $setting->set('area', 'ms3_main');
                $setting->set('xtype', 'textfield');
            }

            $setting->set('value', $secret);
            $setting->save();

            $this->modx->log(
                modX::LOG_LEVEL_INFO,
                "[TokenService] Generated new snippet token secret"
            );

            $this->modx->cacheManager->refresh([
                'system_settings' => [],
            ]);
        }

        return $secret;
    }

    /**
     * Save snippet data to cache
     *
     * @param string $token Snippet token
     * @param array $data Data to cache
     * @param int|null $ttl TTL in seconds (null = from system settings)
     * @return bool Operation success
     */
    public function cacheSnippetData(string $token, array $data, ?int $ttl = null): bool
    {
        if ($ttl === null) {
            $ttl = (int)$this->modx->getOption('ms3_snippet_cache_ttl', null, 3600);
        }

        $options = [
            \xPDO\xPDO::OPT_CACHE_KEY => 'minishop3/snippets',
        ];

        $result = $this->modx->cacheManager->set($token, $data, $ttl, $options);

        if ($result) {
            $this->modx->log(
                modX::LOG_LEVEL_DEBUG,
                "[TokenService] Cached snippet data, token: {$token}, ttl: {$ttl}s"
            );
        }

        return $result;
    }

    /**
     * Get snippet data from cache
     *
     * @param string $token Snippet token
     * @return array|null Data or null if not found
     */
    public function getSnippetData(string $token): ?array
    {
        $options = [
            \xPDO\xPDO::OPT_CACHE_KEY => 'minishop3/snippets',
        ];

        $data = $this->modx->cacheManager->get($token, $options);

        return $data ?: null;
    }

    /**
     * Clear customer token from session
     *
     * @return void
     */
    public function clearCustomerToken(): void
    {
        unset($_SESSION['ms3']['customer_token']);
        unset($_SESSION['ms3']['customer_token_expires']);

        $this->modx->log(
            modX::LOG_LEVEL_DEBUG,
            "[TokenService] Cleared customer token from session"
        );
    }

    /**
     * Clear snippet cache
     *
     * @param string|null $token Specific token or null to clear all
     * @return bool
     */
    public function clearSnippetCache(?string $token = null): bool
    {
        $options = [
            \xPDO\xPDO::OPT_CACHE_KEY => 'minishop3/snippets',
        ];

        if ($token !== null) {
            return $this->modx->cacheManager->delete($token, $options);
        } else {
            return $this->modx->cacheManager->clean($options);
        }
    }
}
