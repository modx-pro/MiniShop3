<?php

namespace MiniShop3\Services\Grid;

/**
 * Whitelist for Manager API filter_* query params on list endpoints.
 *
 * Blocks filtering on secret/integration columns (token, password, properties, …).
 */
final class ManagerListFilterPolicy
{
    /** @var list<string> */
    public const BLOCKED_FILTER_FIELDS = [
        'token',
        'password',
        'properties',
        'class',
        'validation_rules',
    ];

    /**
     * UI filter key => msCustomer column or virtual handler key.
     *
     * @var array<string, string>
     */
    public const CUSTOMER_FILTER_MAP = [
        'customer_name' => 'customer_name',
        'email' => 'email',
        'phone' => 'phone',
        'is_active' => 'is_active',
        'active' => 'is_active',
    ];

    /**
     * UI filter key => msDelivery column.
     *
     * @var array<string, string>
     */
    public const DELIVERY_FILTER_MAP = [
        'name' => 'name',
        'active' => 'active',
    ];

    /**
     * UI filter key => msPayment column.
     *
     * @var array<string, string>
     */
    public const PAYMENT_FILTER_MAP = [
        'name' => 'name',
        'active' => 'active',
    ];

    /**
     * UI filter key => order filter handler (column or virtual key).
     *
     * @var array<string, string>
     */
    public const ORDER_FILTER_MAP = [
        'num' => 'num',
        'customer' => 'customer',
        'status' => 'status_id',
        'status_id' => 'status_id',
        'status_name' => 'status_id',
        'delivery' => 'delivery_id',
        'delivery_id' => 'delivery_id',
        'payment' => 'payment_id',
        'payment_id' => 'payment_id',
        'context' => 'context',
        'email' => 'email',
        'phone' => 'phone',
    ];

    public static function isAllowedFilter(string $fieldName, array $allowedMap): bool
    {
        if ($fieldName === '' || in_array($fieldName, self::BLOCKED_FILTER_FIELDS, true)) {
            return false;
        }

        return array_key_exists($fieldName, $allowedMap);
    }

    /**
     * @param \xPDO\Om\xPDOQuery $query
     */
    public static function applyCustomerFilter($query, string $fieldName, mixed $value): void
    {
        if (!self::isAllowedFilter($fieldName, self::CUSTOMER_FILTER_MAP)) {
            return;
        }

        $column = self::CUSTOMER_FILTER_MAP[$fieldName];

        if ($column === 'customer_name') {
            $query->where([
                'first_name:LIKE' => "%{$value}%",
                'OR:last_name:LIKE' => "%{$value}%",
            ]);

            return;
        }

        if ($column === 'is_active') {
            $query->where(['is_active' => (int)$value]);

            return;
        }

        $query->where([$column . ':LIKE' => "%{$value}%"]);
    }

    /**
     * @param \xPDO\Om\xPDOQuery $query
     */
    public static function applyOrderFilter($query, string $fieldName, mixed $value): void
    {
        if (!self::isAllowedFilter($fieldName, self::ORDER_FILTER_MAP)) {
            return;
        }

        switch (self::ORDER_FILTER_MAP[$fieldName]) {
            case 'status_id':
                $query->where(['status_id' => (int)$value]);
                break;
            case 'delivery_id':
                $query->where(['delivery_id' => (int)$value]);
                break;
            case 'payment_id':
                $query->where(['payment_id' => (int)$value]);
                break;
            case 'context':
                $query->where(['context' => $value]);
                break;
            case 'customer':
                $query->where([
                    'Address.first_name:LIKE' => "%{$value}%",
                    'OR:Address.last_name:LIKE' => "%{$value}%",
                ]);
                break;
            case 'email':
                $query->where(['Address.email:LIKE' => "%{$value}%"]);
                break;
            case 'phone':
                $query->where(['Address.phone:LIKE' => "%{$value}%"]);
                break;
            case 'num':
                $query->where(['num:LIKE' => "{$value}%"]);
                break;
        }
    }

    /**
     * @param array<string, mixed> $criteria
     */
    public static function applyCriteriaFilter(array &$criteria, string $fieldName, mixed $value, array $allowedMap): void
    {
        if (!self::isAllowedFilter($fieldName, $allowedMap)) {
            return;
        }

        $column = $allowedMap[$fieldName];

        if ($column === 'active') {
            $criteria['active'] = (int)$value;

            return;
        }

        $criteria[$column . ':LIKE'] = "%{$value}%";
    }
}
