<?php

declare(strict_types=1);

namespace MiniShop3\Processors\Product;

/**
 * Allowlisted ORDER BY for the legacy product combo processor.
 *
 * menuindex keeps the historical two-column order. Anything outside the list
 * falls back to that same order so caller input never reaches SQL.
 */
final class ProductComboSort
{
    /** @var list<string> */
    public const COLUMNS = [
        'id',
        'pagetitle',
        'longtitle',
        'alias',
        'menuindex',
        'published',
        'context_key',
        'createdon',
    ];

    /**
     * @return array{0: string, 1: string} SQL expression and ASC|DESC
     */
    public static function resolve(string $sort, string $dir): array
    {
        $direction = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';
        if ($sort === 'menuindex' || !in_array($sort, self::COLUMNS, true)) {
            return ["msProduct.parent {$direction}, msProduct.menuindex", $direction];
        }

        return ["msProduct.`{$sort}`", $direction];
    }
}
