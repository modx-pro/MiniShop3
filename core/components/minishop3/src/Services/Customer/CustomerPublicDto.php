<?php

declare(strict_types=1);

namespace MiniShop3\Services\Customer;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msCustomer;
use MODX\Revolution\modX;

/**
 * Public customer payload and profile field allowlist for Web API (#424).
 */
final class CustomerPublicDto
{
    /**
     * Core fields exposed in public customer JSON (fail-closed for new columns).
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
     * Base profile fields editable via Web API (Rakit rules apply in controller).
     *
     * @var list<string>
     */
    public const CORE_PROFILE_EDITABLE_FIELDS = [
        'first_name',
        'last_name',
        'email',
        'phone',
    ];

    /**
     * Never writable from Web customer profile/add endpoints.
     *
     * @var list<string>
     */
    public const SYSTEM_NON_EDITABLE_FIELDS = [
        'id',
        'token',
        'user_id',
        'password',
        'email_verified_at',
        'is_active',
        'is_blocked',
        'failed_login_attempts',
        'blocked_until',
        'created_at',
        'updated_at',
        'last_login_at',
        'orders_count',
        'total_spent',
        'last_order_at',
        'privacy_accepted_at',
        'privacy_ip',
    ];

    /**
     * @param array<string, mixed> $customerFields
     * @return array<string, mixed>
     */
    public static function fromArray(array $customerFields, array $extraPublicKeys = []): array
    {
        $allowed = self::publicFieldKeys($extraPublicKeys);

        return array_intersect_key($customerFields, array_flip($allowed));
    }

    /**
     * @return array<string, mixed>
     */
    public static function fromCustomer(msCustomer $customer, modX $modx, MiniShop3 $ms3): array
    {
        return self::fromArray($customer->toArray(), CustomerExtraFieldRegistry::activeKeys($modx));
    }

    /**
     * @return list<string>
     */
    public static function publicFieldKeys(array $extraFieldKeys = []): array
    {
        $keys = self::PUBLIC_FIELDS;

        foreach ($extraFieldKeys as $key) {
            $key = (string) $key;
            if ($key === '' || in_array($key, self::SYSTEM_NON_EDITABLE_FIELDS, true)) {
                continue;
            }
            if (!in_array($key, $keys, true)) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * @param list<string> $extraFieldKeys Active msExtraField keys for msCustomer
     *
     * @return list<string>
     */
    public static function mergeEditableFieldKeys(array $extraFieldKeys): array
    {
        $allowed = self::CORE_PROFILE_EDITABLE_FIELDS;

        foreach ($extraFieldKeys as $key) {
            $key = (string) $key;
            if ($key === '' || in_array($key, self::SYSTEM_NON_EDITABLE_FIELDS, true)) {
                continue;
            }
            if (!in_array($key, $allowed, true)) {
                $allowed[] = $key;
            }
        }

        return $allowed;
    }

    /**
     * @return list<string>
     */
    public static function editableFieldKeys(modX $modx, MiniShop3 $ms3): array
    {
        $ms3->loadMap();
        $fieldMeta = $modx->getFieldMeta(msCustomer::class);
        if (!is_array($fieldMeta) || $fieldMeta === []) {
            return self::CORE_PROFILE_EDITABLE_FIELDS;
        }

        $extraKeys = CustomerExtraFieldRegistry::activeKeys($modx);
        $merged = self::mergeEditableFieldKeys($extraKeys);

        return array_values(array_filter(
            $merged,
            static fn(string $key): bool => isset($fieldMeta[$key])
        ));
    }

    public static function isEditableField(string $key, modX $modx, MiniShop3 $ms3): bool
    {
        return in_array($key, self::editableFieldKeys($modx, $ms3), true);
    }
}
