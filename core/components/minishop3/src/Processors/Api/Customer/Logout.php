<?php

namespace MiniShop3\Processors\Api\Customer;

use MiniShop3\Model\msCustomer;
use MiniShop3\Model\msCustomerToken;
use MiniShop3\Services\Customer\AuthManager;
use MiniShop3\Utils\CookieHelper;
use MODX\Revolution\Processors\Processor;

/**
 * Logout - customer logout processor
 *
 * Revokes all customer tokens and creates a fresh anonymous token.
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
        $customerId = $_SESSION['ms3']['customer_id'] ?? null;

        if (!$customerId) {
            return $this->success($this->modx->lexicon('ms3_customer_logout_success'));
        }

        /** @var msCustomer $customer */
        $customer = $this->modx->getObject(msCustomer::class, $customerId);

        if ($customer) {
            /** @var AuthManager $authManager */
            $authManager = $this->modx->services->get('ms3_auth_manager');

            // Revoke ALL api tokens for this customer (including current)
            $authManager->revokeTokens($customer, 'api');

            $this->modx->log(
                \MODX\Revolution\modX::LOG_LEVEL_INFO,
                "[Logout] Customer #{$customer->id} logged out"
            );
        }

        // Generate fresh anonymous token
        $ttl = (int)$this->modx->getOption('ms3_customer_token_ttl', null, 604800);
        $newToken = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + $ttl);

        $tokenObj = $this->modx->newObject(msCustomerToken::class);
        $tokenObj->set('token', $newToken);
        $tokenObj->set('type', msCustomerToken::TYPE_API);
        $tokenObj->set('customer_id', 0);
        $tokenObj->set('expires_at', $expiresAt);
        $tokenObj->set('created_at', date('Y-m-d H:i:s'));
        $tokenObj->save();

        // Update session with new anonymous token
        if (!isset($_SESSION['ms3'])) {
            $_SESSION['ms3'] = [];
        }
        unset($_SESSION['ms3']['customer_id']);
        $_SESSION['ms3']['customer_token'] = $newToken;
        $_SESSION['ms3']['customer_token_expires'] = time() + $ttl;

        // Set new cookie
        CookieHelper::setTokenCookie($this->modx, $newToken);

        return $this->success($this->modx->lexicon('ms3_customer_logout_success'));
    }
}
