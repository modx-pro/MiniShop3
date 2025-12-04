<?php

namespace MiniShop3\Processors\Api\Customer;

use MiniShop3\Model\msCustomer;
use MiniShop3\Services\Customer\AuthManager;
use MODX\Revolution\Processors\Processor;

/**
 * Logout - customer logout processor
 *
 * Removes API tokens and clears session.
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
        $customerId = $_SESSION['ms3']['customer_id'] ?? null;

        if (!$customerId) {
            return $this->success($this->modx->lexicon('ms3_customer_logout_success'));
        }

        /** @var msCustomer $customer */
        $customer = $this->modx->getObject(msCustomer::class, $customerId);

        if ($customer) {
            /** @var AuthManager $authManager */
            $authManager = $this->modx->services->get('ms3_auth_manager');

            $authManager->revokeTokens($customer, 'api');

            $this->modx->log(
                \MODX\Revolution\modX::LOG_LEVEL_INFO,
                "[Logout] Customer #{$customer->id} logged out"
            );
        }

        unset($_SESSION['ms3']['customer_id']);
        unset($_SESSION['ms3']['customer_token']);

        return $this->success($this->modx->lexicon('ms3_customer_logout_success'));
    }
}
