<?php

namespace MiniShop3\Services;

use MiniShop3\Model\msCustomer;
use MiniShop3\Model\msCustomerToken;
use MiniShop3\Services\Customer\CustomerAccess;
use MiniShop3\Utils\CookieHelper;
use MiniShop3\Utils\SessionHelper;
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
        $existingToken = $this->ensureCustomerTokenLoaded();
        if ($existingToken !== null) {
            $resolved = $this->resolveApiToken($existingToken);
            if ($resolved['reason'] === 'ok') {
                $tokenObj = $resolved['token'];
                $expires = (int) strtotime((string) $tokenObj->get('expires_at'));
                $lifetime = max(0, $expires - time());

                CookieHelper::setTokenCookie($this->modx, $existingToken);

                return [
                    'token' => $existingToken,
                    'expires' => $expires,
                    'lifetime' => $lifetime * 1000,
                ];
            }

            // First real API action with a lazy session token: persist that
            // very token (#1000) instead of minting a different one (which
            // would orphan the draft created under the lazy token). A token
            // that was already persisted (marker cleared) must never be
            // reinserted after revocation.
            if (
                $resolved['reason'] === 'missing'
                && $this->isSessionTokenLazy()
                && empty($_SESSION['ms3']['customer_id'])
            ) {
                $persisted = $this->ensureTokenPersisted($existingToken, 0, $ttl);
                if ($persisted !== null) {
                    $expires = (int) strtotime((string) $persisted->get('expires_at'));

                    return [
                        'token' => $existingToken,
                        'expires' => $expires,
                        'lifetime' => max(0, $expires - time()) * 1000,
                    ];
                }
            }

            $this->discardStaleCustomerSession();
        }

        $customerId = (int)($_SESSION['ms3']['customer_id'] ?? 0);

        $tokenObj = $this->persistApiToken($customerId, null, $ttl);
        if (!$tokenObj) {
            return ['token' => '', 'expires' => 0, 'lifetime' => 0];
        }

        $expires = (int)strtotime((string)$tokenObj->get('expires_at'));

        $this->modx->log(
            modX::LOG_LEVEL_INFO,
            "[TokenService] Generated customer token for customer_id={$customerId}, expires: "
            . $tokenObj->get('expires_at')
        );

        return [
            'token' => (string)$tokenObj->get('token'),
            'expires' => $expires,
            'lifetime' => max(0, $expires - time()) * 1000,
        ];
    }

    /**
     * Get or create an anonymous token held only in session + cookie (#1000).
     *
     * No DB row is written until the first real cart/order action persists
     * the token via {@see ensureTokenPersisted()}. Search crawlers that only
     * render snippets therefore no longer fill `ms3_customer_tokens`.
     *
     * An already persisted token behaves exactly as before (DB-validated).
     * A freshly minted token is marked as lazy in the session; the marker is
     * cleared as soon as a DB row exists.
     *
     * A cookie is never adopted into the session without DB validation.
     * The lazy token is minted server-side only; the cookie remains a hint
     * that must be validated via DB (as on beta).
     */
    public function getOrCreateSessionToken(): string
    {
        SessionHelper::ensureActive();

        $sessionToken = $this->getCustomerToken();
        if ($sessionToken !== null && $sessionToken !== '') {
            CookieHelper::setTokenCookie($this->modx, $sessionToken);

            return $sessionToken;
        }

        // Do not adopt cookie into session without DB validation.
        // Mint a new server-side lazy token.
        $token = bin2hex(random_bytes(32));
        $ttl = (int)$this->modx->getOption('ms3_customer_token_ttl', null, 86400);
        if (!isset($_SESSION['ms3'])) {
            $_SESSION['ms3'] = [];
        }
        // An authenticated session without a token keeps its customer id; the
        // regular minting path binds it to the DB row on first use.
        $customerId = (int)($_SESSION['ms3']['customer_id'] ?? 0);
        $_SESSION['ms3']['customer_token'] = $token;
        $_SESSION['ms3']['customer_token_expires'] = time() + $ttl;
        $_SESSION['ms3']['customer_token_lazy'] = true;
        $_SESSION['ms3']['customer_id'] = $customerId;
        CookieHelper::setTokenCookie($this->modx, $token);

        return $token;
    }

    /**
     * Whether the session token was minted lazily and has no DB row yet (#1000).
     *
     * Only such tokens may be persisted on first use. Once a row exists the
     * marker is cleared by {@see applyTokenToSession()}, so a revoked token
     * cannot be silently reinserted.
     */
    public function isSessionTokenLazy(): bool
    {
        return !empty($_SESSION['ms3']['customer_token_lazy']);
    }

    /**
     * Persist a session-only token on its first real use (#1000).
     *
     * Returns the existing DB row when the token is already persisted (after
     * re-checking its principal), otherwise creates it with the presented
     * token string. Only the SHA-256 hash is stored (#927); the plaintext is
     * kept in memory via {@see msCustomerToken::setPlainToken()}.
     *
     * Security: only server-minted guest tokens still marked as lazy in the
     * session are promoted. Forged tokens, revoked tokens and authenticated
     * sessions must not create a DB row here.
     *
     * @return msCustomerToken|null
     */
    public function ensureTokenPersisted(string $token, int $customerId = 0, ?int $ttl = null): ?msCustomerToken
    {
        if ($token === '') {
            return null;
        }

        // Defense-in-depth: only a server-minted token that is still marked as
        // unpersisted may be promoted. A forged token, an already persisted
        // token that was revoked, or an authenticated session must not create
        // a DB row here.
        $sessionToken = $_SESSION['ms3']['customer_token'] ?? '';
        if (
            empty($sessionToken)
            || !hash_equals($sessionToken, $token)
            || !$this->isSessionTokenLazy()
            || $customerId !== 0
        ) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                '[TokenService] ensureTokenPersisted: rejected token (not a lazy guest session token)'
            );
            return null;
        }

        if ($ttl === null) {
            $ttl = (int)$this->modx->getOption('ms3_customer_token_ttl', null, 86400);
        }

        // A concurrent first call may already have persisted this token.
        // Return that row (hash identity, #927) instead of inserting again.
        $existing = $this->findApiTokenByString($token);
        if ($existing instanceof msCustomerToken) {
            if ($existing->isExpired() || !$this->apiTokenPrincipalAllowed($existing)) {
                $this->modx->log(
                    modX::LOG_LEVEL_ERROR,
                    '[TokenService] ensureTokenPersisted: existing token is not promotable'
                );
                return null;
            }
            $existing->setPlainToken($token);
            $this->syncSessionFromToken($existing);

            return $existing;
        }

        $tokenObj = $this->modx->newObject(msCustomerToken::class);
        $tokenObj->set('customer_id', $customerId);
        $tokenObj->set('token', '');
        $tokenObj->set('token_hash', hash('sha256', $token));
        $tokenObj->set('type', msCustomerToken::TYPE_API);
        $tokenObj->set('expires_at', date('Y-m-d H:i:s', time() + $ttl));
        $tokenObj->set('created_at', date('Y-m-d H:i:s'));

        $duplicate = false;
        try {
            $saved = $tokenObj->save();
        } catch (\Throwable $e) {
            if (!$this->isDuplicateTokenHashError($e)) {
                $this->modx->log(
                    modX::LOG_LEVEL_ERROR,
                    '[TokenService] Failed to persist session token: ' . $e->getMessage()
                );
                return null;
            }
            $saved = true;
            $duplicate = true;
        }
        if (!$saved) {
            // Some drivers report duplicate keys via errorInfo() instead of
            // throwing; the UNIQUE index on token_hash arbitrates either way.
            $errorInfo = method_exists($this->modx, 'errorInfo') ? $this->modx->errorInfo() : null;
            if (!$this->isDuplicateTokenHashError($errorInfo)) {
                $this->modx->log(
                    modX::LOG_LEVEL_ERROR,
                    '[TokenService] Failed to persist session token'
                );
                return null;
            }
            $duplicate = true;
        }

        if ($duplicate) {
            $raced = $this->findApiTokenByString($token);
            if ($raced instanceof msCustomerToken && !$raced->isExpired() && $this->apiTokenPrincipalAllowed($raced)) {
                $raced->setPlainToken($token);
                $this->syncSessionFromToken($raced);

                return $raced;
            }
        }

        $tokenObj->setPlainToken($token);
        $this->syncSessionFromToken($tokenObj);

        return $tokenObj;
    }

    /**
     * Hash-first API token lookup with plaintext fallback for legacy rows (#927).
     */
    private function findApiTokenByString(string $token): ?msCustomerToken
    {
        $tokenObj = $this->modx->getObject(msCustomerToken::class, [
            'token_hash' => hash('sha256', $token),
            'type' => msCustomerToken::TYPE_API,
        ]);
        if (!$tokenObj) {
            $tokenObj = $this->modx->getObject(msCustomerToken::class, [
                'token' => $token,
                'type' => msCustomerToken::TYPE_API,
            ]);
        }

        return $tokenObj instanceof msCustomerToken ? $tokenObj : null;
    }

    /**
     * Whether a failed insert is a UNIQUE(token_hash) collision from a racing
     * first call. Other save failures must stay failures.
     */
    private function isDuplicateTokenHashError(mixed $error): bool
    {
        $message = match (true) {
            $error instanceof \Throwable => $error->getMessage(),
            is_string($error) => $error,
            is_array($error) => implode(' ', array_map(
                static fn($part) => is_scalar($part) ? (string) $part : '',
                $error
            )),
            default => '',
        };
        $message = strtolower($message);
        if ($message === '') {
            return false;
        }

        return (str_contains($message, 'duplicate') || str_contains($message, '1062'))
            && (str_contains($message, 'token_hash') || str_contains($message, 'unique'));
    }

    /**
     * Persist an API token row and hydrate session/cookie from that DB row.
     *
     * Single mint/update path for guest, login bind, and logout anonymous token.
     * Only the SHA-256 hash is stored in `token_hash` (#927); the plaintext is
     * held in memory for this request (get('token'), cookie, session, API response).
     *
     * @param int $customerId 0 = guest
     * @param string|null $reuseToken update this token when present in DB; otherwise mint
     */
    public function persistApiToken(int $customerId, ?string $reuseToken = null, ?int $ttl = null): ?msCustomerToken
    {
        SessionHelper::ensureActive();

        if ($ttl === null) {
            $ttl = (int)$this->modx->getOption('ms3_customer_token_ttl', null, 86400);
        }

        $expiresAt = date('Y-m-d H:i:s', time() + $ttl);
        $tokenObj = null;

        if ($reuseToken !== null && $reuseToken !== '') {
            // Hash-first lookup, then legacy plaintext fallback (transition, #927).
            $tokenObj = $this->modx->getObject(msCustomerToken::class, [
                'token_hash' => hash('sha256', $reuseToken),
                'type' => msCustomerToken::TYPE_API,
            ]);
            if (!$tokenObj) {
                $tokenObj = $this->modx->getObject(msCustomerToken::class, [
                    'token' => $reuseToken,
                    'type' => msCustomerToken::TYPE_API,
                ]);
            }
        }

        if ($tokenObj) {
            $plainToken = (string)$reuseToken;
            $tokenObj->set('customer_id', $customerId);
            $tokenObj->set('expires_at', $expiresAt);
            // Lazy-upgrade legacy rows: keep the hash, drop the plaintext.
            $tokenObj->set('token_hash', hash('sha256', $plainToken));
            $tokenObj->set('token', '');
        } else {
            $plainToken = bin2hex(random_bytes(32));
            $tokenObj = $this->modx->newObject(msCustomerToken::class);
            $tokenObj->set('customer_id', $customerId);
            $tokenObj->set('type', msCustomerToken::TYPE_API);
            $tokenObj->set('expires_at', $expiresAt);
            $tokenObj->set('created_at', date('Y-m-d H:i:s'));
            $tokenObj->set('token_hash', hash('sha256', $plainToken));
            $tokenObj->set('token', '');
        }

        if (!$tokenObj->save()) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                '[TokenService] Failed to persist API token'
            );
            return null;
        }

        $tokenObj->setPlainToken($plainToken);
        $this->syncSessionFromToken($tokenObj);

        return $tokenObj;
    }

    /**
     * Resolve an API token from the database.
     *
     * Looks up by SHA-256 hash first (`token_hash`). If not found, falls back
     * to plaintext `token` column for transition period (old tokens).
     * Expired tokens are removed. Does not extend TTL and does not keep the
     * same token string alive.
     *
     * @return array{token: ?msCustomerToken, reason: 'ok'|'missing'|'expired'|'denied'}
     *         `missing` means no row exists; `denied` means the row exists but
     *         its customer is inactive/blocked (never promote or hydrate it)
     */
    public function resolveApiToken(string $tokenString): array
    {
        if ($tokenString === '') {
            return ['token' => null, 'reason' => 'missing'];
        }

        // 1. Try hash lookup (new tokens)
        $tokenObj = $this->modx->getObject(msCustomerToken::class, [
            'token_hash' => hash('sha256', $tokenString),
            'type' => msCustomerToken::TYPE_API,
        ]);

        // 2. Fallback to plaintext token (transition period / old tokens)
        if (!$tokenObj) {
            $tokenObj = $this->modx->getObject(msCustomerToken::class, [
                'token' => $tokenString,
                'type' => msCustomerToken::TYPE_API,
            ]);
        }

        if (!$tokenObj) {
            return ['token' => null, 'reason' => 'missing'];
        }

        if ($tokenObj->isExpired()) {
            $this->modx->log(
                modX::LOG_LEVEL_INFO,
                '[TokenService] API token expired, removing.'
            );
            $tokenObj->remove();

            return ['token' => null, 'reason' => 'expired'];
        }

        if (!$this->apiTokenPrincipalAllowed($tokenObj)) {
            return ['token' => null, 'reason' => 'denied'];
        }

        // Hold the presented plaintext in memory for cookie/session hydration (#927).
        $tokenObj->setPlainToken($tokenString);

        return ['token' => $tokenObj, 'reason' => 'ok'];
    }

    /**
     * Resolve existing token or create a session-only (lazy) one
     *
     * Resolution chain:
     * 1. Session/cookie token that exists in DB → return it
     * 2. Session/cookie token that was minted lazily and is not in DB yet → return it as is
     * 3. Otherwise mint a new session-only token (no DB write until first API action)
     *
     * #1000: anonymous visitors (including crawlers) that only render
     * snippets no longer fill `ms3_customer_tokens`. The token is held in
     * session + cookie and persisted by TokenMiddleware / generateCustomerToken
     * on the first protected API call.
     *
     * @return string Token string
     */
    public function resolveOrCreateToken(): string
    {
        $sessionToken = $this->getCustomerToken();
        if ($sessionToken) {
            $resolved = $this->resolveApiToken($sessionToken);
            if ($resolved['reason'] === 'ok') {
                CookieHelper::setTokenCookie($this->modx, $sessionToken);

                return $sessionToken;
            }

            // Session-only lazy token (never persisted) — keep it. An already
            // persisted token that was revoked is discarded below.
            if (
                $resolved['reason'] === 'missing'
                && $this->isSessionTokenLazy()
                && empty($_SESSION['ms3']['customer_id'])
            ) {
                CookieHelper::setTokenCookie($this->modx, $sessionToken);

                return $sessionToken;
            }

            $this->discardStaleCustomerSession();
        }

        $cookieToken = CookieHelper::getTokenFromCookie();
        if (!empty($cookieToken)) {
            $resolved = $this->resolveApiToken($cookieToken);

            if ($resolved['reason'] === 'ok') {
                $tokenObj = $resolved['token'];

                if (!isset($_SESSION['ms3'])) {
                    $_SESSION['ms3'] = [];
                }
                $_SESSION['ms3']['customer_token'] = $cookieToken;
                $_SESSION['ms3']['customer_token_expires'] = strtotime($tokenObj->get('expires_at'));
                unset($_SESSION['ms3']['customer_token_lazy']); // DB row exists

                $customerId = (int)$tokenObj->get('customer_id');
                if ($customerId > 0) {
                    $_SESSION['ms3']['customer_id'] = $customerId;
                }

                CookieHelper::setTokenCookie($this->modx, $cookieToken);

                return $cookieToken;
            }

            // Never adopt an unvalidated cookie value: forged, revoked and
            // stale cookies must not be resurrected. A lazy token cannot be
            // restored across sessions — its marker lived in the lost session.
            if ($resolved['reason'] === 'denied') {
                // A blocked/inactive customer must not keep an authenticated id.
                unset($_SESSION['ms3']['customer_id']);
            }
            CookieHelper::clearTokenCookie($this->modx);
        }

        return $this->getOrCreateSessionToken();
    }

    /**
     * Renew expired API token TTL and hydrate $_SESSION from DB row.
     * Cookie/session receive the request plaintext (minted or presented),
     * never the stored hash (#927).
     */
    public function syncSessionFromToken(msCustomerToken $tokenObj): void
    {
        $this->renewTokenIfExpired($tokenObj);
        $this->applyTokenToSession($tokenObj);

        $cookieToken = (string)$tokenObj->get('token');
        if ($cookieToken !== '') {
            CookieHelper::setTokenCookie($this->modx, $cookieToken);
        }
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
            $ttl = (int)$this->modx->getOption('ms3_customer_token_ttl', null, 86400);
        }

        SessionHelper::ensureActive();

        // Try hash lookup first, then plaintext for transition
        $tokenObj = $this->modx->getObject(msCustomerToken::class, [
            'token_hash' => hash('sha256', $token),
            'type' => msCustomerToken::TYPE_API,
        ]);
        if (!$tokenObj) {
            $tokenObj = $this->modx->getObject(msCustomerToken::class, [
                'token' => $token,
                'type' => msCustomerToken::TYPE_API,
            ]);
        }

        if (!$tokenObj) {
            return $this->generateCustomerToken($ttl);
        }

        $tokenObj->setPlainToken($token);

        $expires = time() + $ttl;
        $tokenObj->set('expires_at', date('Y-m-d H:i:s', $expires));
        if (!$tokenObj->save()) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                '[TokenService] Failed to extend token TTL in database'
            );
            return ['token' => '', 'expires' => 0, 'lifetime' => 0];
        }

        $this->applyTokenToSession($tokenObj);
        $cookieToken = (string)$tokenObj->get('token');
        if ($cookieToken !== '') {
            CookieHelper::setTokenCookie($this->modx, $cookieToken);
        }

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

            unset(
                $_SESSION['ms3']['customer_token'],
                $_SESSION['ms3']['customer_token_expires'],
                $_SESSION['ms3']['customer_id']
            );

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
     * Drop revoked session token, cookie, and bound customer id before minting anew.
     */
    private function discardStaleCustomerSession(): void
    {
        $this->clearCustomerToken();
        CookieHelper::clearTokenCookie($this->modx);
        unset($_SESSION['ms3']['customer_id']);
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
        unset($_SESSION['ms3']['customer_token_lazy']);

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

    /**
     * Load valid session/cookie token without minting a new one.
     */
    private function ensureCustomerTokenLoaded(): ?string
    {
        SessionHelper::ensureActive();

        $token = $this->getCustomerToken();
        if ($token !== null) {
            return $token;
        }

        $this->restoreSessionFromCookie();

        return $this->getCustomerToken();
    }

    /**
     * Ensure PHP session is active (public facade for callers outside TokenService).
     */
    public function ensureSessionActive(): void
    {
        SessionHelper::ensureActive();
    }

    /**
     * Resolve opaque API token from trusted sources (same order as TokenMiddleware).
     *
     * 1. Authorization: Bearer
     * 2. HTTP_MS3TOKEN (legacy)
     * 3. httpOnly cookie `ms3_token`
     * 4. $_REQUEST['ms3_token'] when not from query string (cookie inject / POST body)
     * 5. PHP session cache
     *
     * Query-string `ms3_token` is never accepted (#576, #755 public catalog ACL).
     */
    public static function resolveTokenFromRequest(): string
    {
        $fromHeader = self::resolveBearerOrLegacyHeader();
        if ($fromHeader !== '') {
            return $fromHeader;
        }

        $cookieToken = CookieHelper::getTokenFromCookie();
        if ($cookieToken !== '') {
            return $cookieToken;
        }

        $fromRequest = $_REQUEST['ms3_token'] ?? '';
        if ($fromRequest !== '' && !array_key_exists('ms3_token', $_GET)) {
            return (string) $fromRequest;
        }

        return (string) ($_SESSION['ms3']['customer_token'] ?? '');
    }

    /**
     * Token to bind cart on login/register (no mint).
     *
     * Order: valid Bearer/MS3TOKEN → session → cookie.
     * Header only when resolveApiToken is ok (junk Bearer must not hide cookie cart).
     */
    public function getBindableTokenString(): string
    {
        SessionHelper::ensureActive();

        $fromHeader = self::resolveBearerOrLegacyHeader();
        if ($fromHeader !== '' && $this->resolveApiToken($fromHeader)['reason'] === 'ok') {
            return $fromHeader;
        }

        $sessionToken = $this->getCustomerToken();
        if ($sessionToken !== null) {
            return $sessionToken;
        }

        return CookieHelper::getTokenFromCookie();
    }

    /**
     * Bearer or legacy MS3TOKEN header value, empty when absent.
     */
    private static function resolveBearerOrLegacyHeader(): string
    {
        foreach (['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $serverKey) {
            $authHeader = $_SERVER[$serverKey] ?? '';
            if (str_starts_with($authHeader, 'Bearer ')) {
                $token = substr($authHeader, 7);
                if ($token !== '') {
                    return $token;
                }
            }
        }

        return (string) ($_SERVER['HTTP_MS3TOKEN'] ?? '');
    }

    /**
     * Rotate a valid API token: mint new row, move draft, revoke old (headless TTL refresh).
     *
     * @return array{token: string, expires_at: string, customer_id: int}|null
     */
    public function rotateApiToken(string $currentToken): ?array
    {
        $resolved = $this->resolveApiToken($currentToken);
        if ($resolved['reason'] !== 'ok' || $resolved['token'] === null) {
            return null;
        }

        /** @var msCustomerToken $oldToken */
        $oldToken = $resolved['token'];
        $customerId = (int) $oldToken->get('customer_id');

        $newToken = $this->persistApiToken($customerId, null, null);
        if (!$newToken) {
            return null;
        }

        $newTokenString = (string) $newToken->get('token');
        if ($newTokenString === '' || $newTokenString === $currentToken) {
            return null;
        }

        if (!$this->moveOrderDraftOnTokenRotation($currentToken, $newTokenString, $customerId)) {
            $newToken->remove();
            $this->syncSessionFromToken($oldToken);

            return null;
        }

        if (!$oldToken->remove()) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                '[TokenService] rotateApiToken: failed to revoke previous API token after mint'
            );
            $newToken->remove();
            $this->syncSessionFromToken($oldToken);

            return null;
        }

        return [
            'token' => $newTokenString,
            'expires_at' => (string) $newToken->get('expires_at'),
            'customer_id' => $customerId,
        ];
    }

    /**
     * Re-link order draft after API token rotation (guest or authenticated).
     *
     * @return bool false when an existing draft could not be moved (caller must abort rotate)
     */
    private function moveOrderDraftOnTokenRotation(string $oldToken, string $newToken, int $customerId): bool
    {
        if (!$this->modx->services->has('ms3_order_draft_manager')) {
            return true;
        }

        /** @var \MiniShop3\Services\Order\OrderDraftManager $draftManager */
        $draftManager = $this->modx->services->get('ms3_order_draft_manager');

        if ($customerId > 0) {
            if ($draftManager->transferDraftToToken($oldToken, $newToken, $customerId)) {
                return true;
            }
            $this->modx->log(
                modX::LOG_LEVEL_WARN,
                '[TokenService] rotateApiToken: transferDraftToToken failed'
            );

            return false;
        }

        $draft = $draftManager->getDraft($oldToken);
        if (!$draft) {
            return true;
        }

        if ($draftManager->syncToken($draft, $newToken)) {
            return true;
        }

        $this->modx->log(
            modX::LOG_LEVEL_ERROR,
            '[TokenService] rotateApiToken: guest draft syncToken failed'
        );

        return false;
    }

    /**
     * Hydrate $_SESSION from httpOnly cookie token when session has no customer token yet.
     * Tries hash lookup first, then plaintext for transition period.
     */
    public function restoreSessionFromCookie(): void
    {
        SessionHelper::ensureActive();

        if ($this->getCustomerToken() !== null) {
            return;
        }

        $cookieToken = CookieHelper::getTokenFromCookie();
        if ($cookieToken === '') {
            return;
        }

        // Try hash lookup first, then plaintext for transition period
        $tokenObj = $this->modx->getObject(msCustomerToken::class, [
            'token_hash' => hash('sha256', $cookieToken),
            'type' => msCustomerToken::TYPE_API,
        ]);
        if (!$tokenObj) {
            $tokenObj = $this->modx->getObject(msCustomerToken::class, [
                'token' => $cookieToken,
                'type' => msCustomerToken::TYPE_API,
            ]);
        }

        if (!$tokenObj) {
            return;
        }

        if (!$this->apiTokenPrincipalAllowed($tokenObj)) {
            return;
        }

        $tokenObj->setPlainToken($cookieToken);

        if ($tokenObj->isExpired() && !$this->renewTokenIfExpired($tokenObj)) {
            return;
        }

        $this->applyTokenToSession($tokenObj);
    }

    /**
     * Whether session/cookie API token belongs to customer and is not expired.
     * Renews TTL in DB when the row is past expires_at.
     * Tries hash lookup first, then plaintext for transition period.
     */
    public function sessionTokenBelongsToCustomer(int $customerId): bool
    {
        SessionHelper::ensureActive();

        $token = (string)($_SESSION['ms3']['customer_token'] ?? '');
        if ($token === '') {
            $token = CookieHelper::getTokenFromCookie();
        }
        if ($token === '' || $customerId <= 0) {
            return false;
        }

        // Try hash lookup first, then plaintext for transition period
        $tokenObj = $this->modx->getObject(msCustomerToken::class, [
            'token_hash' => hash('sha256', $token),
            'type' => msCustomerToken::TYPE_API,
        ]);
        if (!$tokenObj) {
            $tokenObj = $this->modx->getObject(msCustomerToken::class, [
                'token' => $token,
                'type' => msCustomerToken::TYPE_API,
            ]);
        }

        if (!$tokenObj || (int)$tokenObj->get('customer_id') !== $customerId) {
            return false;
        }

        if (!$this->apiTokenPrincipalAllowed($tokenObj)) {
            return false;
        }

        if ($tokenObj->isExpired() && !$this->renewTokenIfExpired($tokenObj)) {
            return false;
        }

        $tokenObj->setPlainToken($token);
        $this->applyTokenToSession($tokenObj);
        $cookieToken = (string)$tokenObj->get('token');
        if ($cookieToken !== '') {
            CookieHelper::setTokenCookie($this->modx, $cookieToken);
        }

        return true;
    }

    /**
     * Guest API tokens (customer_id = 0) skip the customer row.
     * Missing or denied customers must not hydrate/renew.
     */
    private function apiTokenPrincipalAllowed(msCustomerToken $tokenObj): bool
    {
        $customerId = (int) $tokenObj->get('customer_id');
        if ($customerId <= 0) {
            return true;
        }

        /** @var msCustomer|null $customer */
        $customer = $this->modx->getObject(msCustomer::class, $customerId);

        return $customer instanceof msCustomer && !CustomerAccess::isAccessDenied($customer);
    }

    /**
     * @return bool false when the row is expired and TTL could not be persisted
     */
    private function renewTokenIfExpired(msCustomerToken $tokenObj): bool
    {
        if (!$tokenObj->isExpired()) {
            return true;
        }

        $ttl = (int)$this->modx->getOption('ms3_customer_token_ttl', null, 86400);
        $previousExpires = $tokenObj->get('expires_at');
        $tokenObj->set('expires_at', date('Y-m-d H:i:s', time() + $ttl));

        if (!$tokenObj->save()) {
            $tokenObj->set('expires_at', $previousExpires);
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                '[TokenService] Failed to renew expired token TTL in database'
            );
            return false;
        }

        return true;
    }

    /**
     * Write token row into $_SESSION. Guest tokens clear authenticated customer_id.
     */
    private function applyTokenToSession(msCustomerToken $tokenObj): void
    {
        SessionHelper::ensureActive();

        if (!isset($_SESSION['ms3'])) {
            $_SESSION['ms3'] = [];
        }

        $_SESSION['ms3']['customer_token'] = (string)$tokenObj->get('token');
        $_SESSION['ms3']['customer_token_expires'] = strtotime($tokenObj->get('expires_at'));
        unset($_SESSION['ms3']['customer_token_lazy']); // row now exists in DB

        $tokenCustomerId = (int)$tokenObj->get('customer_id');
        $_SESSION['ms3']['customer_id'] = self::sessionCustomerIdFromTokenRow($tokenCustomerId);
    }

    /**
     * Session customer_id derived from an API token row (guest clears auth).
     */
    public static function sessionCustomerIdFromTokenRow(int $tokenCustomerId): int
    {
        return $tokenCustomerId > 0 ? $tokenCustomerId : 0;
    }
}
