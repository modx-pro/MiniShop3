<?php

namespace MiniShop3\Processors\Api\Customer;

use MiniShop3\Controllers\Auth\PasswordAuthProvider;
use MiniShop3\Services\Customer\AuthManager;
use MiniShop3\Services\Customer\EmailVerificationService;
use MiniShop3\Services\Customer\RateLimiter;
use MiniShop3\Services\Customer\RegisterService;
use MODX\Revolution\Processors\Processor;

/**
 * Register - processor for registering a new customer
 *
 * Creates a new customer with validation and optional email verification.
 * On auto-login: binds existing session token to new customer (preserves guest cart).
 * Protected from spam via RateLimiter.
 *
 * @package MiniShop3\Processors\Api\Customer
 */
class Register extends Processor
{
    /**
     * @return array|string
     */
    public function process()
    {
        $this->modx->lexicon->load('minishop3:customer');

        $email = PasswordAuthProvider::normalizeEmail($this->getProperty('email', ''));
        $password = $this->getProperty('password', '');
        $firstName = trim($this->getProperty('first_name', ''));
        $lastName = trim($this->getProperty('last_name', ''));
        $phone = trim($this->getProperty('phone', ''));
        $privacyAccepted = (bool)$this->getProperty('privacy_accepted', false);

        if ($email === '') {
            return $this->failure($this->modx->lexicon('ms3_customer_err_email_required'));
        }

        if ($password === '') {
            return $this->failure($this->modx->lexicon('ms3_customer_err_password_required'));
        }

        $requirePrivacy = (bool)$this->modx->getOption('ms3_customer_require_privacy_consent', null, true);
        if ($requirePrivacy && !$privacyAccepted) {
            return $this->failure($this->modx->lexicon('ms3_customer_err_privacy_required'));
        }

        /** @var RateLimiter $rateLimiter */
        $rateLimiter = $this->modx->services->get('ms3_rate_limiter');

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (!$rateLimiter->check('register', $ip, 3, 3600)) {
            return $this->failure($this->modx->lexicon('ms3_customer_err_register_rate_limit'));
        }

        /** @var RegisterService $registerService */
        $registerService = $this->modx->services->get('ms3_register_service');

        /** @var EmailVerificationService $emailVerification */
        $emailVerification = $this->modx->services->get('ms3_email_verification_service');
        $registerService->setEmailVerification($emailVerification);

        $result = $registerService->register([
            'email' => $email,
            'password' => $password,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone' => $phone,
            'privacy_accepted' => $privacyAccepted,
        ]);

        if (!$result['success']) {
            return $this->failure($result['message']);
        }

        $customer = $result['customer'];

        $autoLogin = (bool)$this->modx->getOption('ms3_customer_auto_login_after_register', null, true);
        $requireEmailVerification = (bool)$this->modx->getOption('ms3_customer_require_email_verification', null, true);

        $tokenString = null;
        $expiresAt = null;

        if ($autoLogin && !$requireEmailVerification) {
            /** @var AuthManager $authManager */
            $authManager = $this->modx->services->get('ms3_auth_manager');
            $session = $authManager->establishCustomerSession($customer);

            if (!$session) {
                return $this->failure($this->modx->lexicon('ms3_customer_err_token_create'));
            }

            $tokenString = $session['token'];
            $expiresAt = $session['expires_at'];
        }

        $rateLimiter->reset('login', $ip);

        $redirectUrl = '';
        if ($autoLogin && !$requireEmailVerification) {
            $redirectPageId = (int)$this->getProperty('redirect_page_id', 0);
            if (!$redirectPageId) {
                $redirectPageId = (int)$this->modx->getOption('ms3_customer_redirect_after_login', null, 0);
            }

            if ($redirectPageId > 0) {
                $redirectUrl = $this->modx->makeUrl($redirectPageId, '', '', 'full');
            }
        }

        return $this->success($this->modx->lexicon('ms3_customer_register_success'), [
            'customer' => [
                'id' => $customer->id,
                'email' => $customer->get('email'),
                'first_name' => $customer->get('first_name'),
                'last_name' => $customer->get('last_name'),
                'phone' => $customer->get('phone'),
                'email_verified' => !empty($customer->get('email_verified_at')),
            ],
            'token' => $tokenString,
            'expires_at' => $expiresAt,
            'email_verification_required' => $requireEmailVerification,
            'redirect_url' => $redirectUrl,
        ]);
    }

}
