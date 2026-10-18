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
 *
 * Columns the site registered itself through msExtraField are not in that set, so they
 * are passed in explicitly — see CustomerFieldAllowlistTrait.
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
     * Columns an msExtraField registration may never put back into the payload.
     *
     * The admin UI cannot create a field over an existing column, but the allowlist is
     * what keeps these out, so it does not rely on that check holding (#908).
     *
     * @var list<string>
     */
    private const NEVER_FIELDS = ['password', 'token'];

    /**
     * Allowed fields, widened by the site's own msCustomer extra fields.
     *
     * Same shape as CustomerPublicDto::publicFieldKeys(): the base set is fail-closed,
     * and a column the site registered itself travels with it.
     *
     * @param list<string> $extraFieldKeys Active msExtraField keys for msCustomer
     *
     * @return list<string>
     */
    public static function fieldKeys(array $extraFieldKeys = []): array
    {
        $keys = self::FIELDS;

        foreach ($extraFieldKeys as $key) {
            $key = (string) $key;
            if ($key === '' || in_array($key, self::NEVER_FIELDS, true)) {
                continue;
            }
            if (!in_array($key, $keys, true)) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * Keep only the allowed fields, preserving the order of the source row.
     *
     * @param array<string, mixed> $row
     * @param list<string> $extraFieldKeys Active msExtraField keys for msCustomer
     *
     * @return array<string, mixed>
     */
    public static function pick(array $row, array $extraFieldKeys = []): array
    {
        return array_intersect_key($row, array_flip(self::fieldKeys($extraFieldKeys)));
    }

    /**
     * Columns of msCustomer that must not even be read from the database.
     *
     * Passed to getSelectColumns() with $exclude = true, so the password hash and the
     * session token never reach the loaded object — not only the response.
     *
     * @param array<string, array<string, mixed>> $fieldMeta msCustomer field meta
     * @param list<string> $extraFieldKeys Active msExtraField keys for msCustomer
     *
     * @return list<string>
     */
    public static function excludedColumns(array $fieldMeta, array $extraFieldKeys = []): array
    {
        $allowed = array_flip(self::fieldKeys($extraFieldKeys));
        $excluded = [];

        foreach (array_keys($fieldMeta) as $column) {
            if (!isset($allowed[$column])) {
                $excluded[] = $column;
            }
        }

        return $excluded;
    }
}
