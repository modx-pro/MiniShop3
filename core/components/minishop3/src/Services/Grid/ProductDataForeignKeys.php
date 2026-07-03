<?php

declare(strict_types=1);

namespace MiniShop3\Services\Grid;

/**
 * Foreign keys on msProductData that reference other tables by id (local column → remote.id).
 *
 * Mirrors {@see \MiniShop3\Model\mysql\msProductData} aggregates where foreign = id.
 * Update when new msProductData aggregate FK is added.
 */
final class ProductDataForeignKeys
{
    /** @var list<string> */
    private const KEYS = [
        'vendor_id',
    ];

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return self::KEYS;
    }

    public static function contains(string $foreignKey): bool
    {
        return in_array($foreignKey, self::KEYS, true);
    }
}
