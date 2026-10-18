<?php

declare(strict_types=1);

namespace MiniShop3\Services\Grid;

/**
 * Single source of truth for msProductData columns in the category products grid (read + write).
 */
final class ProductDataColumnPolicy
{
    /** @var list<string> */
    public const INTERNAL_COLUMNS = ['id', 'preview_file_id', 'source_id'];

    /** @var list<string> */
    public const GRID_READONLY_COLUMNS = ['image', 'thumb'];

    /**
     * Extra Data.* columns for SELECT: grid model columns not already emitted as builtin row fields.
     *
     * @param array<int, array<string, mixed>> $gridFields
     * @param array<string, array<string, mixed>> $fieldMeta msProductData field meta
     *
     * @return array<string, string> field name => phptype
     */
    public static function extraReadableColumns(array $gridFields, array $fieldMeta): array
    {
        $reserved = array_flip(
            array_map('strtolower', GridColumnRules::RESERVED_CATEGORY_PRODUCT_FIELD_NAMES)
        );
        $internal = array_flip(array_map('strtolower', self::INTERNAL_COLUMNS));
        $columns = [];

        foreach ($gridFields as $field) {
            if (($field['type'] ?? 'model') !== 'model') {
                continue;
            }

            $name = (string) ($field['name'] ?? '');
            if ($name === ''
                || !GridColumnRules::matchesSqlIdentifierPattern($name)
                || !isset($fieldMeta[$name])
            ) {
                continue;
            }

            $lower = strtolower($name);
            if (isset($reserved[$lower]) || isset($internal[$lower])) {
                continue;
            }

            $phptype = (string) ($fieldMeta[$name]['phptype'] ?? 'string');
            $columns[$name] = $phptype;
        }

        return $columns;
    }

    /**
     * Cast a raw PDO value to the xPDO phptype of its column (null stays null).
     */
    public static function castValue(mixed $value, string $phptype): mixed
    {
        if ($value === null) {
            return null;
        }

        return match (strtolower($phptype)) {
            'float' => (float) $value,
            'integer' => (int) $value,
            'boolean' => (bool) $value,
            'json' => json_decode((string) $value, true) ?? (string) $value,
            default => (string) $value,
        };
    }

    /**
     * msProductData field names allowed for inline / API update.
     *
     * @param array<string, array<string, mixed>> $fieldMeta
     *
     * @return list<string>
     */
    public static function writableNames(array $fieldMeta): array
    {
        $exclude = array_flip(array_map(
            'strtolower',
            array_merge(self::INTERNAL_COLUMNS, self::GRID_READONLY_COLUMNS)
        ));
        $names = [];

        foreach (array_keys($fieldMeta) as $name) {
            if (!isset($exclude[strtolower($name)])) {
                $names[] = $name;
            }
        }

        return $names;
    }
}
