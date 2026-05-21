<?php

namespace MiniShop3\Utils;

use MiniShop3\Model\msProductFile;
use MODX\Revolution\modX;

/**
 * Builds SQL ON conditions for joining a product thumbnail (msProductFile child row).
 */
final class ProductThumbnailJoin
{
    /**
     * LEFT JOIN ON: one thumbnail per product — child of the main gallery image
     * (parent_id = 0, lowest position), path contains the given size folder.
     */
    public static function buildLeftJoinOn(
        modX $modx,
        string $thumbAlias,
        string $thumbSize,
        string $productAlias = 'msProduct'
    ): string {
        $thumbAlias = self::sanitizeSqlIdentifier($thumbAlias);
        $productAlias = self::sanitizeSqlIdentifier($productAlias);
        $thumbSize = preg_replace('#[^\w\-/]#', '', $thumbSize);

        if ($thumbAlias === '' || $productAlias === '' || $thumbSize === '') {
            return '1 = 0';
        }

        $filesTable = $modx->getTableName(msProductFile::class);

        return sprintf(
            '`%1$s`.product_id = `%2$s`.id'
            . ' AND `%1$s`.parent_id != 0'
            . ' AND `%1$s`.path LIKE \'%%/%3$s/%%\''
            . ' AND `%1$s`.parent_id = ('
            . 'SELECT `main`.`id` FROM `%4$s` `main`'
            . ' WHERE `main`.`product_id` = `%2$s`.`id`'
            . ' AND `main`.`parent_id` = 0'
            . ' AND `main`.`type` = \'image\''
            . ' ORDER BY `main`.`position` ASC, `main`.`id` ASC'
            . ' LIMIT 1'
            . ')',
            $thumbAlias,
            $productAlias,
            $thumbSize,
            $filesTable
        );
    }

    private static function sanitizeSqlIdentifier(string $name): string
    {
        return preg_replace('#[^\w]#', '', $name);
    }
}
