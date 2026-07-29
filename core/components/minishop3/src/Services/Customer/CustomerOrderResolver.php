<?php

namespace MiniShop3\Services\Customer;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msCustomer;
use MODX\Revolution\modX;

/**
 * Customer Order Resolver
 *
 * Resolves or creates customers during checkout from order data.
 */
class CustomerOrderResolver
{
    protected modX $modx;
    protected MiniShop3 $ms3;
    protected CustomerFieldManager $fieldManager;

    public function __construct(modX $modx, MiniShop3 $ms3, CustomerFieldManager $fieldManager)
    {
        $this->modx = $modx;
        $this->ms3 = $ms3;
        $this->fieldManager = $fieldManager;
    }

    /**
     * Get or create customer for order
     *
     * @param object $customer Customer facade (event/create payload BC)
     */
    public function getOrCreate(object $customer, string $token, ?array $orderData = null): int
    {
        $response = $this->ms3->utils->invokeEvent('msOnBeforeGetOrderCustomer', [
            'controller' => $this->ms3->getOrder(),
            'msCustomer' => null,
        ]);
        if (!$response['success']) {
            return 0;
        }

        $msCustomer = ($response['data']['msCustomer'] ?? null) instanceof msCustomer
            ? $response['data']['msCustomer']
            : $this->getByToken($token);

        if (!$msCustomer) {
            if ($orderData === null) {
                $orderResponse = $this->ms3->getOrder()->get();
                $orderData = $orderResponse['data']['order'] ?? [];
            }

            $email = $orderData['address_email'] ?? '';
            if ($email !== '') {
                // Link order to existing account by email only.
                // Never overwrite msCustomer.token — that hands the guest session the victim's account.
                $msCustomer = $this->findByEmail($email);
            }

            if (!$msCustomer) {
                $msCustomer = $this->createFromOrderData($customer, $token, $orderData);
            }
        }

        $response = $this->ms3->utils->invokeEvent('msOnGetOrderCustomer', [
            'controller' => $this->ms3->getOrder(),
            'msCustomer' => $msCustomer,
        ]);
        if (!$response['success']) {
            return 0;
        }

        return $msCustomer ? (int) $msCustomer->get('id') : 0;
    }

    /**
     * Create customer from order data
     *
     * @param object $customer Customer facade (create payload BC)
     */
    protected function createFromOrderData(object $customer, string $token, array $orderData): ?msCustomer
    {
        $email = $orderData['address_email'] ?? '';

        if ($email === '') {
            return null;
        }

        $autoRegister = (bool) $this->modx->getOption('ms3_customer_auto_register_on_order', null, true);
        $autoLogin = (bool) $this->modx->getOption('ms3_customer_auto_login_on_order', null, true);
        $msCustomer = null;

        if ($autoRegister) {
            /** @var RegisterService $registerService */
            $registerService = $this->modx->services->get('ms3_register_service');

            if ($registerService) {
                $registerResult = $registerService->register([
                    'first_name' => $orderData['address_first_name'] ?? '',
                    'last_name' => $orderData['address_last_name'] ?? '',
                    'phone' => $orderData['address_phone'] ?? '',
                    'email' => $email,
                    'token' => $token,
                    'privacy_accepted' => true,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                ]);

                if ($registerResult['success']) {
                    $msCustomer = $registerResult['customer'];
                } else {
                    // Email already registered: attach order only — no token/session takeover
                    $msCustomer = $this->findByEmail($email);
                }
            }
        }

        if (!$msCustomer) {
            $msCustomer = $this->fieldManager->create($customer, [
                'first_name' => $orderData['address_first_name'] ?? '',
                'last_name' => $orderData['address_last_name'] ?? '',
                'phone' => $orderData['address_phone'] ?? '',
                'email' => $email,
                'token' => $token,
            ]);
        }

        if ($msCustomer && $autoLogin) {
            $this->establishSession($msCustomer);
        }

        return $msCustomer;
    }

    protected function establishSession(msCustomer $msCustomer): void
    {
        /** @var AuthManager|null $authManager */
        $authManager = $this->modx->services->get('ms3_auth_manager');
        if (!$authManager instanceof AuthManager || !$authManager->establishCustomerSession($msCustomer)) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                "[CustomerOrderResolver] establishCustomerSession failed for customer #{$msCustomer->id}"
            );
        }
    }

    protected function findByEmail(string $email): ?msCustomer
    {
        $normalized = AuthManager::normalizeEmail($email);
        if ($normalized === '') {
            return null;
        }

        /** @var msCustomer|null $customer */
        $customer = $this->modx->getObject(msCustomer::class, ['email' => $normalized]);
        if ($customer) {
            return $customer;
        }

        $raw = trim($email);
        if ($raw !== '' && $raw !== $normalized) {
            return $this->modx->getObject(msCustomer::class, ['email' => $raw]) ?: null;
        }

        return null;
    }

    protected function getByToken(string $token): ?msCustomer
    {
        if ($token === '') {
            return null;
        }

        return $this->modx->getObject(msCustomer::class, ['token' => $token]) ?: null;
    }
}
