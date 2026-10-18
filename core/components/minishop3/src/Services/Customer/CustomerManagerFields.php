<?php

declare(strict_types=1);

namespace MiniShop3\Services\Customer;

/**
 * Customer fields a manager-side processor may return (#908).
 *
 * Allowlist, not a denylist: a column added to ms3_customers later stays out of
 * responses until it is listed here — same fail-closed rule as CustomerPublicDto
 * for the Web API. The set mirrors CustomersController::formatCustomer(), which is
 * what the Vue manager already treats as the full customer payload.
 */
final class CustomerManagerFields
{
    /**
     * @var list<string>
     */
    public const FIELDS = [
        'id',
        'user_id',
        'customer_group_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'email_verified_at',
        'is_active',
        'is_blocked',
        'blocked_until',
        'created_at',
        'updated_at',
        'last_login_at',
        'orders_count',
        'total_spent',
        'last_order_at',
    ];

    /**
     * Keep only the allowed fields, preserving the order of the source row.
     *
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    public static function pick(array $row): array
    {
        return array_intersect_key($row, array_flip(self::FIELDS));
    }

    /**
     * Columns of msCustomer that must not even be read from the database.
     *
     * Passed to getSelectColumns() with $exclude = true, so the password hash and the
     * session token never reach the loaded object — not only the response.
     *
     * @param array<string, array<string, mixed>> $fieldMeta msCustomer field meta
     *
     * @return list<string>
     */
    public static function excludedColumns(array $fieldMeta): array
    {
        $allowed = array_flip(self::FIELDS);
        $excluded = [];

        foreach (array_keys($fieldMeta) as $column) {
            if (!isset($allowed[$column])) {
                $excluded[] = $column;
            }
        }

        return $excluded;
    }
}
