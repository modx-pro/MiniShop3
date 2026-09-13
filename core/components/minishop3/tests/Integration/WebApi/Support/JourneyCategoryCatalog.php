<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Integration\WebApi\Support;

use MiniShop3\Services\Catalog\CatalogQuery;
use MiniShop3\Services\Category\CategoryCatalogService;
use MODX\Revolution\modX;

/**
 * Stub CategoryCatalogService for public catalog HTTP journey (#705).
 */
final class JourneyCategoryCatalog extends CategoryCatalogService
{
    public const FIXTURE_CATEGORY_ID = 21;

    public function __construct(modX $modx)
    {
        parent::__construct($modx);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    public function getById(int $categoryId, array $params = []): ?array
    {
        CatalogQuery::resolveContext($params, 'web');

        if ($categoryId !== self::FIXTURE_CATEGORY_ID) {
            return null;
        }

        return [
            'id' => self::FIXTURE_CATEGORY_ID,
            'pagetitle' => 'Journey Category',
            'published' => 1,
        ];
    }
}
