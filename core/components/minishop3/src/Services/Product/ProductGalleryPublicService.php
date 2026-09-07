<?php

declare(strict_types=1);

namespace MiniShop3\Services\Product;

use MiniShop3\Model\msProductFile;
use MODX\Revolution\modX;

/**
 * Batch-load public gallery rows for product get/list/images (#566).
 *
 * Only active top-level images (parent_id=0, type=image). Child thumbs are
 * joined in a second IN-query, not per file. Preferred `thumb` matches
 * ms3_product_thumbnail_size (same path folder as mgr Gallery GetList).
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
        return $this->loadForProducts(
            [$productId => ['pagetitle' => $pagetitle, 'preview_file_id' => $previewFileId]],
            ProductGalleryPublicSerializer::MAX_IMAGES,
        )[$productId] ?? [];
    }

    /**
     * @param array<int, array{pagetitle?: string, preview_file_id?: int}> $products
     * @return array<int, list<array<string, mixed>>>
     */
    public function loadForProducts(array $products, int $perProductLimit): array
    {
        $ids = [];
        foreach (array_keys($products) as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        if ($ids === []) {
            return [];
        }

        $limit = max(1, $perProductLimit);
        $grouped = $this->groupOriginals($this->fetchOriginalsForProducts($ids), $limit);
        $parentIds = [];
        foreach ($grouped as $rows) {
            foreach ($rows as $row) {
                $parentIds[] = (int) ($row['id'] ?? 0);
            }
        }
        $thumbs = $this->fetchThumbsByParent(array_values(array_filter($parentIds)));

        $out = [];
        foreach ($products as $id => $meta) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }
            $out[$id] = ProductGalleryPublicSerializer::serializeGallery(
                $grouped[$id] ?? [],
                $thumbs,
                (string) ($meta['pagetitle'] ?? ''),
                (int) ($meta['preview_file_id'] ?? 0),
            );
        }

        return $out;
    }

    /**
     * @param list<int> $productIds
     * @return list<array<string, mixed>>
     */
    private function fetchOriginalsForProducts(array $productIds): array
    {
        $c = $this->modx->newQuery(msProductFile::class);
        $c->where([
            'product_id:IN' => $productIds,
            'parent_id' => 0,
            'type' => 'image',
            'active' => 1,
        ]);
        $c->select('id, product_id, url, name, description, position');
        $c->sortby('product_id', 'ASC');
        $c->sortby('position', 'ASC');
        $c->sortby('id', 'ASC');

        if (!$c->prepare() || !$c->stmt->execute()) {
            return [];
        }

        return $c->stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<int, list<array<string, mixed>>>
     */
    private function groupOriginals(array $rows, int $perProductLimit): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }
            if (!isset($grouped[$productId])) {
                $grouped[$productId] = [];
            }
            if (count($grouped[$productId]) >= $perProductLimit) {
                continue;
            }
            $grouped[$productId][] = $row;
        }

        return $grouped;
    }

    /**
     * @param list<int> $parentIds
     * @return array<int, array{thumb: string, thumbs: array<string, string>}>
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
        $c->select('id, parent_id, product_id, url, path');
        $c->sortby('id', 'ASC');

        if (!$c->prepare() || !$c->stmt->execute()) {
            return [];
        }

        $preferred = $this->preferredThumbSize();
        $out = [];
        foreach ($c->stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
            $parentId = (int) ($row['parent_id'] ?? 0);
            if ($parentId <= 0) {
                continue;
            }
            $url = (string) ($row['url'] ?? '');
            $size = ProductGalleryPublicSerializer::sizeKeyFromChild(
                (string) ($row['path'] ?? ''),
                $url,
                (int) ($row['product_id'] ?? 0),
            );
            if (!isset($out[$parentId])) {
                $out[$parentId] = ['thumb' => '', 'thumbs' => []];
            }
            if ($size !== '' && !isset($out[$parentId]['thumbs'][$size])) {
                $out[$parentId]['thumbs'][$size] = $url;
            }
            if ($size === $preferred) {
                $out[$parentId]['thumb'] = $url;
            } elseif ($out[$parentId]['thumb'] === '') {
                $out[$parentId]['thumb'] = $url;
            }
        }

        return $out;
    }

    private function preferredThumbSize(): string
    {
        $size = trim((string) $this->modx->getOption('ms3_product_thumbnail_size', null, 'small'));

        return $size !== '' ? $size : 'small';
    }
}
