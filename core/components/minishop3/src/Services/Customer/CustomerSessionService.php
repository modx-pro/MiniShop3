<?php

declare(strict_types=1);

namespace MiniShop3\Services\Customer;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msCustomer;
use MiniShop3\Services\TokenService;
use MODX\Revolution\modX;

/**
 * Session introspection payload for GET /customer/me (#571).
 */
final class CustomerSessionService
{
    public function __construct(
        private modX $modx,
        private TokenService $tokenService,
        private MiniShop3 $ms3,
    ) {
    }

    /**
     * Build GET /customer/me payload from a validated API token string.
     *
     * @return array{
     *     authenticated: bool,
     *     customer: array<string, mixed>|null,
     *     token: array{expires_at: string, customer_id: int}
     * }|null null when token is missing/invalid/expired
     */
    public function buildMePayload(string $tokenString): ?array
    {
        $resolved = $this->tokenService->resolveApiToken($tokenString);
        if ($resolved['reason'] !== 'ok' || $resolved['token'] === null) {
            return null;
        }

        $tokenObj = $resolved['token'];
        $customerId = (int) $tokenObj->get('customer_id');
        $customer = $customerId > 0
            ? $this->modx->getObject(msCustomer::class, $customerId)
            : null;

        return [
            'authenticated' => $customer instanceof msCustomer,
            'customer' => $customer instanceof msCustomer
                ? CustomerPublicDto::fromCustomer($customer, $this->modx, $this->ms3)
                : null,
            'token' => [
                'expires_at' => (string) $tokenObj->get('expires_at'),
                'customer_id' => $customerId,
            ],
        ];
    }
}
