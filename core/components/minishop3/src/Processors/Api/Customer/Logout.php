<?php

namespace MiniShop3\Processors\Api\Customer;

use MiniShop3\Services\Customer\AuthManager;
use MODX\Revolution\Processors\Processor;

/**
 * Logout - customer logout processor
 *
 * Revokes all customer API tokens and creates a fresh anonymous token.
 * New cookie is set — guest cart starts fresh (security: old token invalidated).
 *
 * @package MiniShop3\Processors\Api\Customer
 */
class Logout extends Processor
{
    /**
     * @return array|string
     */
    public function process()
    {
        $this->modx->lexicon->load('minishop3:customer');

        /** @var AuthManager $authManager */
        $authManager = $this->modx->services->get('ms3_auth_manager');

        if (!$authManager->logoutCurrentCustomer()) {
            return $this->failure($this->modx->lexicon('ms3_customer_err_token_create'));
        }

        return $this->success($this->modx->lexicon('ms3_customer_logout_success'));
    }
}
