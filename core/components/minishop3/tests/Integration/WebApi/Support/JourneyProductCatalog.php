<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Integration\WebApi\Support;

use MiniShop3\Services\Product\ProductCatalogService;
use MODX\Revolution\modX;

/**
 * Stub ProductCatalogService for public catalog HTTP journey.
 */
final class JourneyProductCatalog extends ProductCatalogService
{
    public const FIXTURE_PRODUCT_ID = 11;

    public function __construct(modX $modx)
    {
        parent::__construct($modx);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    public function getById(int $productId, array $params = []): ?array
    {
        unset($params);
        if ($productId !== self::FIXTURE_PRODUCT_ID) {
            return null;
        }

        return [
            'id' => self::FIXTURE_PRODUCT_ID,
            'pagetitle' => 'Journey Shirt',
            'price' => 100.0,
            'published' => 1,
        ];
    }

    /**
     * @param array<string, mixed> $params
     * @return array{total: int, limit: int, offset: int, results: list<array<string, mixed>>}
     */
    public function getList(array $params = []): array
    {
        $limit = max(1, (int) ($params['limit'] ?? 10));
        $offset = max(0, (int) ($params['offset'] ?? 0));

        $results = [
            [
                'id' => self::FIXTURE_PRODUCT_ID,
                'pagetitle' => 'Journey Shirt',
                'price' => 100.0,
            ],
        ];

        return [
            'total' => 1,
            'limit' => $limit,
            'offset' => $offset,
            'results' => $results,
        ];
    }
}
