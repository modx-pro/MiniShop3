<?php

declare(strict_types=1);

namespace MiniShop3\Services\Customer;

use MiniShop3\Model\msCustomer;

/**
 * Public customer payload for Web API responses (no secrets / lock internals).
 */
final class CustomerPublicDto
{
    /**
     * Fields allowed in public customer JSON (fail-closed for new columns).
     *
     * @var list<string>
     */
    private const PUBLIC_FIELDS = [
        'id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'email_verified_at',
        'is_active',
        'created_at',
        'updated_at',
        'last_login_at',
        'orders_count',
        'total_spent',
        'last_order_at',
        'privacy_accepted_at',
    ];

    /**
     * @param array<string, mixed> $customerFields
     * @return array<string, mixed>
     */
    public static function fromArray(array $customerFields): array
    {
        return array_intersect_key($customerFields, array_flip(self::PUBLIC_FIELDS));
    }

    /**
     * @return array<string, mixed>
     */
    public static function fromCustomer(msCustomer $customer): array
    {
        return self::fromArray($customer->toArray());
    }
}
