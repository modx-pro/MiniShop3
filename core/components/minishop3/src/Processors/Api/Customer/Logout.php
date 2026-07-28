<?php

namespace MiniShop3\Processors\Api\Customer;

use MiniShop3\Model\msCustomer;
use MiniShop3\Services\Customer\AuthManager;
use MiniShop3\Services\TokenService;
use MiniShop3\Utils\CookieHelper;
use MiniShop3\Utils\SessionHelper;
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

        $customer = $this->resolveCustomerForLogout();
        if ($customer) {
            // Ensure AuthManager sees Bearer/cookie identity when PHP session is empty.
            SessionHelper::ensureActive();
            if (!isset($_SESSION['ms3']) || !is_array($_SESSION['ms3'])) {
                $_SESSION['ms3'] = [];
            }
            $_SESSION['ms3']['customer_id'] = (int) $customer->id;
        }

        /** @var AuthManager $authManager */
        $authManager = $this->modx->services->get('ms3_auth_manager');

        if (!$authManager->logoutCurrentCustomer()) {
            return $this->failure($this->modx->lexicon('ms3_customer_err_token_create'));
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        return $this->success($this->modx->lexicon('ms3_customer_logout_success'));
    }

    /**
     * Resolve customer from session (after TokenMiddleware) or validated API token.
     */
    protected function resolveCustomerForLogout(): ?msCustomer
    {
        $customerId = (int) ($_SESSION['ms3']['customer_id'] ?? 0);
        if ($customerId > 0) {
            $customer = $this->modx->getObject(msCustomer::class, $customerId);
            if ($customer) {
                return $customer;
            }
        }

        $tokenString = $this->resolveLogoutTokenString();
        if ($tokenString === '') {
            return null;
        }

        /** @var TokenService $tokenService */
        $tokenService = $this->modx->services->get('ms3_token_service');
        $resolved = $tokenService->resolveApiToken($tokenString);
        if ($resolved['reason'] !== 'ok') {
            return null;
        }

        $tokenCustomerId = (int) $resolved['token']->get('customer_id');
        if ($tokenCustomerId <= 0) {
            return null;
        }

        return $this->modx->getObject(msCustomer::class, $tokenCustomerId) ?: null;
    }

    protected function resolveLogoutTokenString(): string
    {
        $cookieToken = CookieHelper::getTokenFromCookie();
        if ($cookieToken !== '') {
            return $cookieToken;
        }

        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (str_starts_with($authHeader, 'Bearer ')) {
            $bearer = trim(substr($authHeader, 7));
            if ($bearer !== '') {
                return $bearer;
            }
        }

        $legacyHeader = $_SERVER['HTTP_MS3TOKEN'] ?? '';
        if ($legacyHeader !== '') {
            return $legacyHeader;
        }

        return $_REQUEST['ms3_token'] ?? $_SESSION['ms3']['customer_token'] ?? '';
    }
}
