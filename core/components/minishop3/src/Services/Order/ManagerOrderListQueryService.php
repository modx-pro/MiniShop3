<?php

declare(strict_types=1);

namespace MiniShop3\Services\Order;

/**
 * Orders manager list query helpers (#353): conditional Address JOIN detection.
 */
final class ManagerOrderListQueryService
{
    /** filter_* keys that require Address in WHERE. */
    public const ADDRESS_FILTER_KEYS = [
        'customer',
        'email',
        'phone',
    ];

    /** Grid/sort field names backed by msOrderAddress columns. */
    public const ADDRESS_FIELD_KEYS = [
        'customer',
        'email',
        'phone',
        'first_name',
        'last_name',
    ];

    /**
     * Whether list query must LEFT JOIN msOrderAddress.
     *
     * @param array<string, mixed>        $params     Request params (filter_* keys)
     * @param array<int, array<string,mixed>> $gridFields Grid config rows
     */
    public static function needsAddressJoin(
        array $params,
        string $query,
        array $gridFields,
        string $sort,
    ): bool {
        if (self::hasAddressFilter($params)) {
            return true;
        }

        if ($query !== '') {
            return true;
        }

        if (in_array($sort, self::ADDRESS_FIELD_KEYS, true)) {
            return true;
        }

        return self::gridNeedsAddressColumns($gridFields);
    }

    /**
     * @param array<string, mixed> $params
     */
    public static function hasAddressFilter(array $params): bool
    {
        foreach (self::ADDRESS_FILTER_KEYS as $fieldName) {
            $value = $params['filter_' . $fieldName] ?? null;
            if ($value !== null && $value !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, array<string, mixed>> $gridFields
     */
    public static function gridNeedsAddressColumns(array $gridFields): bool
    {
        foreach ($gridFields as $field) {
            if (($field['visible'] ?? true) === false) {
                continue;
            }

            $name = (string) ($field['name'] ?? '');
            if (in_array($name, self::ADDRESS_FIELD_KEYS, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $params
     */
    public static function shouldIncludeStats(array $params): bool
    {
        if (!array_key_exists('include_stats', $params)) {
            return false;
        }

        $value = $params['include_stats'];
        if ($value === '' || $value === null) {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
