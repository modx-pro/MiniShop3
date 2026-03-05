<?php

namespace MiniShop3\Controllers\Api\Web;

use MiniShop3\Model\msCustomer;

/**
 * Trait for Web API controllers that need to resolve the authorized customer.
 *
 * ServiceCheckMiddleware guarantees has('ms3') before controller is invoked.
 */
trait AuthorizedCustomerTrait
{
    /**
     * Get authorized customer (session or API token)
     *
     * Method 1: API token (ms3_token in request or session).
     * Method 2: Session customer_id (set by TokenMiddleware).
     *
     * @return msCustomer|null
     */
    protected function getAuthorizedCustomer(): ?msCustomer
    {
        $ms3 = $this->modx->services->get('ms3');
        $ms3->initialize();

        // Method 1: Try API token
        $tokenString = $_REQUEST['ms3_token'] ?? $_SESSION['ms3']['customer_token'] ?? '';

        if (!empty($tokenString)) {
            $tokenObj = $this->modx->getObject(\MiniShop3\Model\msCustomerToken::class, [
                'token' => $tokenString,
                'type' => \MiniShop3\Model\msCustomerToken::TYPE_API
            ]);

            if ($tokenObj && !$tokenObj->isExpired()) {
                $customer = $this->modx->getObject(msCustomer::class, $tokenObj->get('customer_id'));
                if ($customer) {
                    return $customer;
                }
            }
        }

        // Method 2: Fall back to session customer_id (consistent with TokenMiddleware)
        if (!empty($_SESSION['ms3']['customer_id'])) {
            $customer = $this->modx->getObject(msCustomer::class, (int)$_SESSION['ms3']['customer_id']);
            if ($customer) {
                return $customer;
            }
        }

        return null;
    }
}
