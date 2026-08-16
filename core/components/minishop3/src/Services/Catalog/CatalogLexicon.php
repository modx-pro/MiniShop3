<?php

declare(strict_types=1);

namespace MiniShop3\Services\Catalog;

use MODX\Revolution\modX;

/**
 * Shared lexicon helpers for public Web API catalogs.
 */
final class CatalogLexicon
{
    public static function translateMs3Name(modX $modx, string $name): string
    {
        if ($name === '' || !str_starts_with($name, 'ms3_')) {
            return $name;
        }

        return $modx->lexicon($name);
    }
}
