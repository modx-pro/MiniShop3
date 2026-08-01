<?php

namespace MiniShop3\Processors\Api\Customer;

use MiniShop3\Controllers\Auth\PasswordAuthProvider;
use MiniShop3\Model\msCustomer;
use MiniShop3\Services\Customer\AuthManager;
use MiniShop3\Services\Customer\RegisterService;
use MODX\Revolution\Processors\Processor;

/**
 * ResetPassword - processor for setting new password via token
 *
 * Validates token from email and sets new password.
 *
 * @package MiniShop3\Processors\Api\Customer
 */
class ResetPassword extends Processor
{
    /**
     * @return array|string
     */
    public function process()
    {
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

        $authManager->revokeTokens($customer);
        $authManager->invalidateLocalSessionForCustomer($customer);

        $this->modx->log(
            \MODX\Revolution\modX::LOG_LEVEL_INFO,
            "[ResetPassword] Password reset for customer #{$customer->id}"
        );

        return $this->success($this->modx->lexicon('ms3_password_reset_complete'));
    }
}
