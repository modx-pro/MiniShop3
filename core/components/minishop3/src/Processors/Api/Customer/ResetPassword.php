<?php

namespace MiniShop3\Processors\Api\Customer;

use MiniShop3\Controllers\Auth\PasswordAuthProvider;
use MiniShop3\Model\msCustomer;
use MiniShop3\Router\HttpStatus;
use MiniShop3\Services\Customer\AuthManager;
use MiniShop3\Services\Customer\RateLimiter;
use MiniShop3\Services\Customer\RegisterService;
use MODX\Revolution\Processors\Processor;

/**
 * ResetPassword - processor for setting new password via token
 *
 * Validates token from email and sets new password.
 * Protected from bruteforce via RateLimiter (per IP and per reset token).
 *
 * @package MiniShop3\Processors\Api\Customer
 */
class ResetPassword extends Processor
{
    private const MAX_ATTEMPTS = 5;

    private const WINDOW_SECONDS = 900;

    /**
     * @return array|string
     */
    public function process()
    {
        $this->modx->lexicon->load('minishop3:customer');

        $token = trim($this->getProperty('token', ''));
        $password = $this->getProperty('password', '');
        $passwordConfirm = $this->getProperty('password_confirm', '');

        if (empty($token)) {
            return $this->failure($this->modx->lexicon('ms3_customer_err_token_required'));
        }

        if (empty($password)) {
            return $this->failure($this->modx->lexicon('ms3_customer_err_password_required'));
        }

        if ($password !== $passwordConfirm) {
            return $this->failure($this->modx->lexicon('ms3_customer_err_password_mismatch'));
        }

        /** @var RateLimiter $rateLimiter */
        $rateLimiter = $this->modx->services->get('ms3_rate_limiter');

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        if (!$rateLimiter->check('reset_password_ip', $ip, self::MAX_ATTEMPTS, self::WINDOW_SECONDS)) {
            return $this->rateLimitFailure($rateLimiter, 'reset_password_ip', $ip);
        }

        if (!$rateLimiter->check('reset_password_token', $token, self::MAX_ATTEMPTS, self::WINDOW_SECONDS)) {
            return $this->rateLimitFailure($rateLimiter, 'reset_password_token', $token);
        }

        /** @var AuthManager $authManager */
        $authManager = $this->modx->services->get('ms3_auth_manager');

        /** @var msCustomer $customer */
        $customer = $authManager->validateToken($token, 'password_reset');

        if (!$customer) {
            return $this->failure($this->modx->lexicon('ms3_customer_err_token_invalid'));
        }

        /** @var RegisterService $registerService */
        $registerService = $this->modx->services->get('ms3_register_service');

        $validation = $registerService->validatePassword($password);
        if (!$validation['valid']) {
            return $this->failure($validation['message']);
        }

        $hashedPassword = PasswordAuthProvider::hashPassword($password);
        $customer->set('password', $hashedPassword);

        $customer->set('failed_login_attempts', 0);
        $customer->set('is_blocked', false);
        $customer->set('blocked_until', null);

        if (!$customer->save()) {
            return $this->failure($this->modx->lexicon('ms3_customer_err_save'));
        }

        $rateLimiter->reset('reset_password_ip', $ip);
        $rateLimiter->reset('reset_password_token', $token);

        $authManager->revokeTokens($customer);
        $authManager->invalidateLocalSessionForCustomer($customer);

        $this->modx->log(
            \MODX\Revolution\modX::LOG_LEVEL_INFO,
            "[ResetPassword] Password reset for customer #{$customer->id}"
        );

        return $this->success($this->modx->lexicon('ms3_password_reset_complete'));
    }

    /**
     * @return array|string
     */
    private function rateLimitFailure(RateLimiter $rateLimiter, string $action, string $identifier)
    {
        $attempts = $rateLimiter->getAttempts($action, $identifier);

        return $this->failure(
            $this->modx->lexicon('ms3_customer_err_reset_password_rate_limit', [
                'attempts' => $attempts,
                'max' => self::MAX_ATTEMPTS,
                'minutes' => round(self::WINDOW_SECONDS / 60),
            ]),
            ['code' => HttpStatus::TOO_MANY_REQUESTS]
        );
    }
}
