<?php

declare(strict_types=1);

namespace MiniShop3\Services\Product;

use MiniShop3\Model\msProductFile;
use MODX\Revolution\modX;

/**
 * Batch-load public gallery rows for product/get?include_images=1 (#566).
 *
 * Only active top-level images (parent_id=0, type=image). Child thumbs are
 * joined in a second IN-query, not per file.
 */
final class ProductGalleryPublicService
{
    public function __construct(
        private modX $modx,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function loadForProduct(int $productId, string $pagetitle, int $previewFileId): array
    {
        if ($productId <= 0) {
            return [];
        }

        $originals = $this->fetchOriginals($productId);
        if ($originals === []) {
            return [];
        }

        return ProductGalleryPublicSerializer::serializeGallery(
            $originals,
            $this->fetchThumbsByParent(array_map(intval(...), array_column($originals, 'id'))),
            $pagetitle,
            $previewFileId,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchOriginals(int $productId): array
    {
        $c = $this->modx->newQuery(msProductFile::class);
        $c->where([
            'product_id' => $productId,
            'parent_id' => 0,
            'type' => 'image',
            'active' => 1,
        ]);
        $c->select('id, url, name, description, position');
        $c->sortby('position', 'ASC');
        $c->sortby('id', 'ASC');
        $c->limit(ProductGalleryPublicSerializer::MAX_IMAGES);

        if (!$c->prepare() || !$c->stmt->execute()) {
            return [];
        }

        return $c->stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * First child image url per original (lowest id).
     *
     * @param list<int> $parentIds
     * @return array<int, string>
     */
    private function fetchThumbsByParent(array $parentIds): array
    {
        if ($parentIds === []) {
            return [];
        }

        $c = $this->modx->newQuery(msProductFile::class);
        $c->where([
            'parent_id:IN' => $parentIds,
            'type' => 'image',
            'active' => 1,
        ]);
        $c->select('id, parent_id, url');
        $c->sortby('id', 'ASC');

        if (!$c->prepare() || !$c->stmt->execute()) {
            return [];
        }

        $thumbs = [];
        foreach ($c->stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
            $parentId = (int) ($row['parent_id'] ?? 0);
            if ($parentId <= 0 || isset($thumbs[$parentId])) {
                continue;
            }
            $thumbs[$parentId] = (string) ($row['url'] ?? '');
        }

        return $thumbs;
    }
}
