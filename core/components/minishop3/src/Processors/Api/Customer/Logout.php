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
 * Detaches customer from current token (session continues as guest).
 * Cookie is preserved — guest cart remains accessible.
 * Other API tokens for this customer are revoked.
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

        // Get current token from cookie/session
        $currentTokenString = CookieHelper::getTokenFromCookie();
        if (empty($currentTokenString)) {
            $currentTokenString = $_SESSION['ms3']['customer_token'] ?? '';
        }

        /** @var msCustomer $customer */
        $customer = $this->modx->getObject(msCustomer::class, $customerId);

        if ($customer) {
            /** @var AuthManager $authManager */
            $authManager = $this->modx->services->get('ms3_auth_manager');

            // Revoke all OTHER api tokens for this customer
            $authManager->revokeTokens($customer, 'api');

            // Re-create current token as anonymous (customer_id = 0)
            // This keeps the same token string valid for guest cart
            if (!empty($currentTokenString)) {
                $tokenObj = $this->modx->getObject(msCustomerToken::class, [
                    'token' => $currentTokenString,
                    'type' => msCustomerToken::TYPE_API,
                ]);

                // Token was revoked by revokeTokens above — re-create it as anonymous
                if (!$tokenObj) {
                    $ttl = (int)$this->modx->getOption('ms3_customer_token_ttl', null, 86400);
                    $tokenObj = $this->modx->newObject(msCustomerToken::class);
                    $tokenObj->set('token', $currentTokenString);
                    $tokenObj->set('type', msCustomerToken::TYPE_API);
                    $tokenObj->set('customer_id', 0);
                    $tokenObj->set('expires_at', date('Y-m-d H:i:s', time() + $ttl));
                    $tokenObj->set('created_at', date('Y-m-d H:i:s'));
                    $tokenObj->save();
                } else {
                    $tokenObj->set('customer_id', 0);
                    $tokenObj->save();
                }
            }

            $this->modx->log(
                \MODX\Revolution\modX::LOG_LEVEL_INFO,
                "[Logout] Customer #{$customer->id} logged out"
            );
        }

        // Clear customer data from session, keep token
        unset($_SESSION['ms3']['customer_id']);
        unset($_SESSION['ms3']['customer_token_expires']);

        // Cookie stays — guest cart preserved

        return $this->success($this->modx->lexicon('ms3_customer_logout_success'));
    }
}
