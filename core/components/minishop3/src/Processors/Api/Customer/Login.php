<?php

namespace MiniShop3\Processors\Api\Customer;

use MiniShop3\Router\HttpStatus;
use MiniShop3\Services\Customer\AuthManager;
use MiniShop3\Services\Customer\RateLimiter;
use MODX\Revolution\Processors\Processor;

/**
 * Login - customer login processor
 *
 * Authenticates customer and establishes a rotated API session token.
 * Guest cart may transfer from the previous token; the previous token is revoked.
 * Protected from brute-force via RateLimiter.
 *
 * @package MiniShop3\Processors\Api\Customer
 */
class Login extends Processor
{
    /**
     * @return array|string
     */
    public function process()
    {
        $this->modx->lexicon->load('minishop3:customer');

        $emailRaw = trim((string)$this->getProperty('email', ''));
        $email = AuthManager::normalizeEmail($emailRaw);
        $password = $this->getProperty('password', '');

        if ($email === '' || $password === '') {
            return $this->failure($this->modx->lexicon('ms3_customer_err_login_required'));
        }

        /** @var RateLimiter $rateLimiter */
        $rateLimiter = $this->modx->services->get('ms3_rate_limiter');

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $maxAttempts = 5;
        $windowSeconds = 300;

        if (!$rateLimiter->check('login', $ip, $maxAttempts, $windowSeconds)) {
            $attempts = $rateLimiter->getAttempts('login', $ip);
            return $this->failure(
                $this->modx->lexicon('ms3_customer_err_login_rate_limit', [
                    'attempts' => $attempts,
                    'max' => $maxAttempts,
                    'minutes' => round($windowSeconds / 60)
                ]),
                ['code' => HttpStatus::TOO_MANY_REQUESTS]
            );
        }

        /** @var AuthManager $authManager */
        $authManager = $this->modx->services->get('ms3_auth_manager');

        // Pass raw email so PasswordAuthProvider can resolve legacy mixed-case rows.
        $customer = $authManager->authenticate([
            'email' => $emailRaw,
            'password' => $password,
        ]);

        if (!$customer) {
            // Only wrong password / unknown email increment lockout counter.
            // blocked / inactive keep lastAuthFailure and must not call handleFailedLoginByEmail.
            $failure = $authManager->getLastAuthFailure();
            if ($failure === 'invalid_credentials') {
                $authManager->handleFailedLoginByEmail($emailRaw);
            }

            $this->modx->log(
                \MODX\Revolution\modX::LOG_LEVEL_WARN,
                "[Login] Failed login attempt for email: {$email} from IP: {$ip} (reason: {$failure})"
            );

            $messageKey = match ($failure) {
                'blocked' => 'ms3_customer_err_login_blocked',
                'inactive' => 'ms3_customer_err_login_inactive',
                default => 'ms3_customer_err_login_invalid',
            };

            return $this->failure(
                $this->modx->lexicon($messageKey),
                ['code' => HttpStatus::UNAUTHORIZED]
            );
        }

        $rateLimiter->reset('login', $ip);

        $session = $authManager->establishCustomerSession($customer);
        if (!$session) {
            return $this->failure(
                $this->modx->lexicon('ms3_customer_err_token_create'),
                ['code' => HttpStatus::INTERNAL_SERVER_ERROR]
            );
        }

        $redirectPageId = (int)$this->getProperty('redirect_page_id', 0);
        if (!$redirectPageId) {
            $redirectPageId = (int)$this->modx->getOption('ms3_customer_redirect_after_login', null, 0);
        }

        $redirectUrl = '';
        if ($redirectPageId > 0) {
            $redirectUrl = $this->modx->makeUrl($redirectPageId, '', '', 'full');
        }

        return $this->success('', [
            'customer' => [
                'id' => $customer->id,
                'email' => $customer->get('email'),
                'first_name' => $customer->get('first_name'),
                'last_name' => $customer->get('last_name'),
                'phone' => $customer->get('phone'),
                'email_verified' => !empty($customer->get('email_verified_at')),
            ],
            'token' => $session['token'],
            'expires_at' => $session['expires_at'],
            'redirect_url' => $redirectUrl,
        ]);
    }

}
