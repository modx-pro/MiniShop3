<?php

namespace MiniShop3\Controllers\Api\Web;

use MiniShop3\Model\msCustomer;
use MiniShop3\Services\Api\WebApiContextResolver;
use MiniShop3\Services\TokenService;

/**
 * Trait for Web API controllers that need to resolve the authorized customer.
 *
 * ServiceCheckMiddleware guarantees has('ms3') before controller is invoked.
 */
trait AuthorizedCustomerTrait
{
    /**
     * Get authorized customer via validated API token only.
     *
     * Method 1: API token (ms3_token in request or session customer_token cache).
     *
     * @return msCustomer|null
     */
    protected function getAuthorizedCustomer(): ?msCustomer
    {
        $ms3 = $this->modx->services->get('ms3');
        $ctx = WebApiContextResolver::liveContextKey($this->modx);
        $ms3->initialize($ctx);

        $tokenString = $_REQUEST['ms3_token'] ?? $_SESSION['ms3']['customer_token'] ?? '';
        if ($tokenString === '') {
            return null;
        }

        /** @var TokenService $tokenService */
        $tokenService = $this->modx->services->get('ms3_token_service');
        $resolved = $tokenService->resolveApiToken($tokenString);

        if ($resolved['reason'] !== 'ok') {
            return null;
        }

        $customerId = (int) $resolved['token']->get('customer_id');
        if ($customerId <= 0) {
            return null;
        }

        return $this->modx->getObject(msCustomer::class, $customerId) ?: null;
    }
}
