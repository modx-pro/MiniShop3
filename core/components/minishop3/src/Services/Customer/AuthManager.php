<?php

namespace MiniShop3\Services\Customer;

use MiniShop3\Controllers\Auth\AuthProviderInterface;
use MiniShop3\Controllers\Auth\PasswordAuthProvider;
use MiniShop3\Model\msCustomer;
use MiniShop3\Model\msCustomerToken;
use MiniShop3\Services\Order\OrderDraftManager;
use MiniShop3\Services\TokenService;
use MiniShop3\Utils\CookieHelper;
use MiniShop3\Utils\SessionHelper;
use MODX\Revolution\modX;

/**
 * AuthManager - central authentication service
 *
 * Manages registration and usage of authentication providers.
 * Pattern: Strategy + Registry.
 *
 * Example usage:
 * ```php
 * $authManager = $modx->services->get('ms3_auth_manager');
 *
 * // Register provider
 * $authManager->registerProvider(new PasswordAuthProvider($modx));
 * $authManager->registerProvider(new SmsAuthProvider($modx));
 *
 * // Authenticate (automatically selects suitable provider)
 * $customer = $authManager->authenticate([
 *     'email' => 'user@example.com',
 *     'password' => 'secret123'
 * ]);
 *
 * if ($customer) {
 *     $token = $authManager->createToken($customer, 'api', 86400);
 * }
 * ```
 *
 * @package MiniShop3\Services
 */
class AuthManager
{
    /** @var modX */
    protected modX $modx;

    /** @var AuthProviderInterface[] Registered authentication providers */
    protected array $providers = [];

    /** Last authenticate() failure: invalid_credentials|blocked|inactive|none */
    protected string $lastAuthFailure = 'none';

    /**
     * @param modX $modx
     */
    public function __construct(modX $modx)
    {
        $this->modx = $modx;

        $this->registerProvider(new PasswordAuthProvider($modx));
    }

    /**
     * Reason of the last failed authenticate() call.
     */
    public function getLastAuthFailure(): string
    {
        return $this->lastAuthFailure;
    }

    /**
     * Register authentication provider
     *
     * @param AuthProviderInterface $provider
     * @return void
     */
    public function registerProvider(AuthProviderInterface $provider): void
    {
        $name = $provider->getName();
        $this->providers[$name] = $provider;

        $this->modx->log(
            modX::LOG_LEVEL_INFO,
            "[AuthManager] Registered auth provider: {$name}"
        );
    }

    /**
     * Get provider by name
     *
     * @param string $name Provider name (password, sms, oauth_google, etc.)
     * @return AuthProviderInterface|null
     */
    public function getProvider(string $name): ?AuthProviderInterface
    {
        return $this->providers[$name] ?? null;
    }

    /**
     * Get all registered providers
     *
     * @return AuthProviderInterface[]
     */
    public function getProviders(): array
    {
        return $this->providers;
    }

    /**
     * Authenticate with automatic provider selection
     *
     * Iterates through registered providers, finds the first one that
     * supports the provided credentials (via supports() method), and invokes it.
     *
     * @param array $credentials Authentication data
     * @return msCustomer|null Customer on success, null on error
     */
    public function authenticate(array $credentials): ?msCustomer
    {
        $this->lastAuthFailure = 'invalid_credentials';

        foreach ($this->providers as $provider) {
            if ($provider->supports($credentials)) {
                $this->modx->log(
                    modX::LOG_LEVEL_DEBUG,
                    "[AuthManager] Using provider: {$provider->getName()}"
                );

                $customer = $provider->authenticate($credentials);

                if ($customer) {
                    if ($customer->get('is_blocked')) {
                        $blockedUntil = $customer->get('blocked_until');
                        if ($blockedUntil && strtotime($blockedUntil) > time()) {
                            $this->lastAuthFailure = 'blocked';
                            $this->modx->log(
                                modX::LOG_LEVEL_WARN,
                                "[AuthManager] Customer #{$customer->id} is blocked until {$blockedUntil}"
                            );
                            return null;
                        }
                        $customer->set('is_blocked', false);
                        $customer->set('blocked_until', null);
                        $customer->set('failed_login_attempts', 0);
                        if (!$customer->save()) {
                            $this->modx->log(
                                modX::LOG_LEVEL_ERROR,
                                "[AuthManager] Failed to clear block flags for customer #{$customer->id}"
                            );
                        }
                    }

                    if (!$customer->get('is_active')) {
                        $this->lastAuthFailure = 'inactive';
                        $this->modx->log(
                            modX::LOG_LEVEL_WARN,
                            "[AuthManager] Customer #{$customer->id} is not active"
                        );
                        return null;
                    }

                    $customer->set('last_login_at', date('Y-m-d H:i:s'));
                    $customer->set('failed_login_attempts', 0);
                    if (!$customer->save()) {
                        $this->modx->log(
                            modX::LOG_LEVEL_ERROR,
                            "[AuthManager] Failed to persist last_login for customer #{$customer->id}"
                        );
                    }

                    $this->lastAuthFailure = 'none';
                    $this->modx->log(
                        modX::LOG_LEVEL_INFO,
                        "[AuthManager] Customer #{$customer->id} authenticated via {$provider->getName()}"
                    );

                    return $customer;
                }
            }
        }

        $this->modx->log(
            modX::LOG_LEVEL_WARN,
            "[AuthManager] No provider found for credentials or authentication failed"
        );

        return null;
    }

    /**
     * Bind authenticated customer to API token, session, cookie, and draft order.
     *
     * Reuses guest token from cookie/session when possible so cart is preserved.
     *
     * @return array{token: string, expires_at: string}|null
     */
    public function establishCustomerSession(msCustomer $customer): ?array
    {
        SessionHelper::ensureActive();

        $ttl = (int)$this->modx->getOption('ms3_customer_token_ttl', null, 604800);

        /** @var TokenService $tokenService */
        $tokenService = $this->modx->services->get('ms3_token_service');
        $currentToken = $tokenService->getBindableTokenString();

        $tokenObj = null;
        if ($currentToken !== '') {
            $tokenObj = $this->modx->getObject(msCustomerToken::class, [
                'token' => $currentToken,
                'type' => msCustomerToken::TYPE_API,
            ]);
        }

        // Rebind only guest tokens (preserve cart). Never hijack another customer's token.
        $canReuse = $tokenObj
            && ((int)$tokenObj->get('customer_id') === 0
                || (int)$tokenObj->get('customer_id') === (int)$customer->id);

        if ($canReuse) {
            $tokenObj->set('customer_id', $customer->id);
            $tokenObj->set('expires_at', date('Y-m-d H:i:s', time() + $ttl));
            if (!$tokenObj->save()) {
                return null;
            }
        } else {
            $tokenObj = $this->createToken($customer, msCustomerToken::TYPE_API, $ttl);
            if (!$tokenObj) {
                return null;
            }
        }

        $tokenString = (string)$tokenObj->get('token');

        /** @var OrderDraftManager $draftManager */
        $draftManager = $this->modx->services->get('ms3_order_draft_manager');
        if (!$draftManager->bindDraftToCustomer($tokenString, $customer->id)) {
            $this->modx->log(
                modX::LOG_LEVEL_WARN,
                "[AuthManager] bindDraftToCustomer failed for customer #{$customer->id}"
            );
        }

        $tokenService->syncSessionFromToken($tokenObj);

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        return [
            'token' => $tokenString,
            'expires_at' => $tokenObj->get('expires_at'),
        ];
    }

    /**
     * End storefront session: revoke API tokens, mint guest token, refresh session id.
     *
     * Used by Web API Logout and snippet `?action=logout` so cookie restore cannot re-auth.
     *
     * @return bool false when anonymous token could not be persisted
     */
    public function logoutCurrentCustomer(): bool
    {
        SessionHelper::ensureActive();

        /** @var TokenService $tokenService */
        $tokenService = $this->modx->services->get('ms3_token_service');
        $tokenService->restoreSessionFromCookie();

        $customerId = (int)($_SESSION['ms3']['customer_id'] ?? 0);
        if ($customerId > 0) {
            /** @var msCustomer|null $customer */
            $customer = $this->modx->getObject(msCustomer::class, $customerId);
            if ($customer) {
                $this->revokeTokens($customer, msCustomerToken::TYPE_API);
                $this->modx->log(
                    modX::LOG_LEVEL_INFO,
                    "[AuthManager] Customer #{$customer->id} logged out"
                );
            }
        } else {
            $orphanToken = $tokenService->getBindableTokenString();
            if ($orphanToken !== '') {
                $tokenObj = $this->modx->getObject(msCustomerToken::class, [
                    'token' => $orphanToken,
                    'type' => msCustomerToken::TYPE_API,
                ]);
                if ($tokenObj) {
                    $tokenObj->remove();
                }
            }
        }

        if (isset($_SESSION['ms3'])) {
            unset(
                $_SESSION['ms3']['customer_id'],
                $_SESSION['ms3']['customer_token'],
                $_SESSION['ms3']['customer_token_expires']
            );
        }
        CookieHelper::clearTokenCookie($this->modx);

        $guest = $tokenService->generateCustomerToken();
        if ($guest['token'] === '') {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                '[AuthManager] logoutCurrentCustomer failed to mint anonymous token'
            );
            return false;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        return true;
    }

    /**
     * Create token for customer
     *
     * @param msCustomer $customer
     * @param string $type Token type (api, refresh, magic_link, email_verification)
     * @param int $ttl TTL in seconds (default 24 hours)
     * @return msCustomerToken|null
     */
    public function createToken(msCustomer $customer, string $type = 'api', int $ttl = 86400): ?msCustomerToken
    {
        /** @var msCustomerToken $token */
        $token = $this->modx->newObject(msCustomerToken::class);

        $tokenString = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + $ttl);

        $token->set('customer_id', $customer->id);
        $token->set('token', $tokenString);
        $token->set('type', $type);
        $token->set('expires_at', $expiresAt);

        if ($token->save()) {
            $this->modx->log(
                modX::LOG_LEVEL_INFO,
                "[AuthManager] Created {$type} token for customer #{$customer->id}, expires: {$expiresAt}"
            );
            return $token;
        }

        $this->modx->log(
            modX::LOG_LEVEL_ERROR,
            "[AuthManager] Failed to create token for customer #{$customer->id}"
        );

        return null;
    }

    /**
     * Validate token and get customer
     *
     * @param string $tokenString Token string
     * @param string $type Token type (api, refresh, magic_link, email_verification)
     * @return msCustomer|null
     */
    public function validateToken(string $tokenString, string $type = 'api'): ?msCustomer
    {
        /** @var msCustomerToken $token */
        $token = $this->modx->getObject(msCustomerToken::class, [
            'token' => $tokenString,
            'type' => $type,
        ]);

        if (!$token) {
            $this->modx->log(
                modX::LOG_LEVEL_DEBUG,
                "[AuthManager] Token not found: {$tokenString}"
            );
            return null;
        }

        if ($token->isExpired()) {
            $this->modx->log(
                modX::LOG_LEVEL_DEBUG,
                "[AuthManager] Token expired: {$tokenString}"
            );
            $token->remove();
            return null;
        }

        if (in_array($type, ['magic_link', 'email_verification']) && $token->get('used_at')) {
            $this->modx->log(
                modX::LOG_LEVEL_DEBUG,
                "[AuthManager] Token already used: {$tokenString}"
            );
            return null;
        }

        /** @var msCustomer $customer */
        $customer = $token->getOne('Customer');

        if (!$customer) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                "[AuthManager] Customer not found for token: {$tokenString}"
            );
            return null;
        }

        if (in_array($type, ['magic_link', 'email_verification'])) {
            $token->set('used_at', date('Y-m-d H:i:s'));
            $token->save();
        }

        return $customer;
    }

    /**
     * Revoke all customer tokens of specific type
     *
     * @param msCustomer $customer
     * @param string|null $type Token type (null = all types)
     * @return int Number of revoked tokens
     */
    public function revokeTokens(msCustomer $customer, ?string $type = null): int
    {
        $criteria = ['customer_id' => $customer->id];
        if ($type) {
            $criteria['type'] = $type;
        }

        $tokens = $this->modx->getCollection(msCustomerToken::class, $criteria);
        $count = 0;

        foreach ($tokens as $token) {
            if ($token->remove()) {
                $count++;
            }
        }

        $this->modx->log(
            modX::LOG_LEVEL_INFO,
            "[AuthManager] Revoked {$count} tokens for customer #{$customer->id}" . ($type ? " (type: {$type})" : '')
        );

        return $count;
    }

    /**
     * Clean up expired tokens (for cron)
     *
     * @return int Number of deleted tokens
     */
    public function cleanupExpiredTokens(): int
    {
        $now = date('Y-m-d H:i:s');

        $tokens = $this->modx->getCollection(msCustomerToken::class, [
            'expires_at:<' => $now,
        ]);

        $count = 0;
        foreach ($tokens as $token) {
            if ($token->remove()) {
                $count++;
            }
        }

        if ($count > 0) {
            $this->modx->log(
                modX::LOG_LEVEL_INFO,
                "[AuthManager] Cleaned up {$count} expired tokens"
            );
        }

        return $count;
    }

    /**
     * Handle failed login attempt
     *
     * Increments failed attempts counter, blocks customer when limit exceeded
     *
     * @param msCustomer $customer
     * @return void
     */
    public function handleFailedLogin(msCustomer $customer): void
    {
        $maxAttempts = (int)$this->modx->getOption('ms3_customer_max_login_attempts', null, 5);
        $blockDuration = (int)$this->modx->getOption('ms3_customer_block_duration', null, 3600);

        $attempts = $customer->get('failed_login_attempts') + 1;
        $customer->set('failed_login_attempts', $attempts);

        if ($attempts >= $maxAttempts) {
            $blockedUntil = date('Y-m-d H:i:s', time() + $blockDuration);
            $customer->set('is_blocked', true);
            $customer->set('blocked_until', $blockedUntil);

            $this->modx->log(
                modX::LOG_LEVEL_WARN,
                "[AuthManager] Customer #{$customer->id} blocked until {$blockedUntil} due to {$attempts} failed attempts"
            );
        }

        if (!$customer->save()) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                "[AuthManager] Failed to persist failed_login_attempts for customer #{$customer->id}"
            );
        }
    }

    /**
     * Record failed login by normalized email when the account exists.
     */
    public function handleFailedLoginByEmail(string $email): void
    {
        $email = PasswordAuthProvider::normalizeEmail($email);
        if ($email === '') {
            return;
        }

        /** @var msCustomer|null $customer */
        $customer = $this->modx->getObject(msCustomer::class, ['email' => $email]);
        if ($customer) {
            $this->handleFailedLogin($customer);
        }
    }
}
