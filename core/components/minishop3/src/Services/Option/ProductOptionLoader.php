<?php

declare(strict_types=1);

namespace MiniShop3\Services\Option;

use MiniShop3\Model\msCategoryMember;
use MiniShop3\Model\msOption;
use MiniShop3\Model\msOptionGroup;
use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductOption;
use xPDO\Om\xPDOQuery;
use xPDO\xPDO;

/**
 * Loads product option values for storefront/catalog (with caption/description overlays).
 */
class ProductOptionLoader
{
    /** @var xPDO */
    protected $xpdo;

    /** @var CaptionOverlayResolver */
    protected $captionOverlayResolver;

    public function __construct(xPDO $xpdo, CaptionOverlayResolver $captionOverlayResolver)
    {
        $this->xpdo = $xpdo;
        $this->captionOverlayResolver = $captionOverlayResolver;
    }

    /**
     * Load options for single product (for frontend templates)
     *
     * Replaces: msProductOption::loadOptions()
     *
     * @param int $productId Product ID
     * @param bool $includeMetadata Include option group metadata (default: true for backward compatibility)
     * @return array Option data with keys like ['color' => ['Red'], 'color.caption' => 'Color']
     */
    public function loadForProduct(int $productId, bool $includeMetadata = true): array
    {
        $c = $this->buildProductOptionsQuery($productId, $includeMetadata);

        /** @var array<string, mixed> $data */
        $data = [];
        $lastRowByOptionKey = [];
        if ($c->prepare() && $c->stmt->execute()) {
            while ($option = $c->stmt->fetch(\PDO::FETCH_ASSOC)) {
                $this->appendOptionRow($data, $option);
                $lastRowByOptionKey[$option['key']] = $option;
            }
        }

        $overlay = $this->captionOverlayResolver->buildCaptionDescriptionOverlayForProduct(
            $productId,
            $lastRowByOptionKey
        );
        $this->applyOverlayToOptionData($data, $overlay);

        return $data;
    }

    /**
     * Load options for multiple products (batch loading for catalog pages)
     *
     * NEW METHOD - prevents N+1 problem in catalog listings
     *
     * @param array $productIds Array of product IDs
     * @param bool $includeMetadata Include option group metadata
     * @return array Nested array: [product_id => option_data]
     */
    public function loadForProducts(array $productIds, bool $includeMetadata = false): array
    {
        if ($productIds === []) {
            return [];
        }

        $c = $this->buildProductOptionsQuery($productIds, $includeMetadata);

        /** @var array<int, array<string, mixed>> $result */
        $result = [];
        $lastRowByProductAndKey = [];
        if ($c->prepare() && $c->stmt->execute()) {
            while ($option = $c->stmt->fetch(\PDO::FETCH_ASSOC)) {
                $productId = (int)$option['product_id'];
                if (!isset($result[$productId])) {
                    $result[$productId] = [];
                }
                $this->appendOptionRow($result[$productId], $option);
                $lastRowByProductAndKey[$productId][$option['key']] = $option;
            }
        }

        $this->applyCaptionDescriptionOverlaysForManyProducts($result, $lastRowByProductAndKey);

        return $result;
    }

    /**
     * Batch-load category membership + parent for many products (2 queries vs 2N).
     *
     * @param int[] $productIds
     * @return array<int, array{category_ids: int[], parent_category_id: int}>
     */
    protected function batchPrefetchProductCategoriesContexts(array $productIds): array
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        if ($productIds === []) {
            return [];
        }

        $out = [];
        foreach ($productIds as $pid) {
            $out[$pid] = [
                'category_ids' => [],
                'parent_category_id' => 0,
            ];
        }

        $q = $this->xpdo->newQuery(msCategoryMember::class, ['product_id:IN' => $productIds]);
        $q->select('product_id,category_id');
        if ($q->prepare() && $q->stmt->execute()) {
            while ($row = $q->stmt->fetch(\PDO::FETCH_ASSOC)) {
                $pid = (int)$row['product_id'];
                $cid = (int)$row['category_id'];
                if ($cid > 0 && isset($out[$pid])) {
                    $out[$pid]['category_ids'][] = $cid;
                }
            }
        }

        $c = $this->xpdo->newQuery(msProduct::class, ['id:IN' => $productIds]);
        $c->select('id,parent');
        if ($c->prepare() && $c->stmt->execute()) {
            while ($row = $c->stmt->fetch(\PDO::FETCH_ASSOC)) {
                $pid = (int)$row['id'];
                $parent = (int)$row['parent'];
                if (!isset($out[$pid])) {
                    continue;
                }
                $out[$pid]['parent_category_id'] = $parent;
                if ($parent > 0) {
                    $out[$pid]['category_ids'][] = $parent;
                }
            }
        }

        foreach ($productIds as $pid) {
            $out[$pid]['category_ids'] = array_values(array_unique(
                array_filter($out[$pid]['category_ids'])
            ));
        }

        return $out;
    }

    /**
     * Apply per-category caption/description overlays for loadForProducts (one link query for the batch).
     *
     * @param array<int, array<string, mixed>> $result Mutated in place
     * @param array<int, array<string, array<string, mixed>>> $lastRowByProductAndKey
     */
    protected function applyCaptionDescriptionOverlaysForManyProducts(
        array &$result,
        array $lastRowByProductAndKey
    ): void {
        $productIds = array_keys($lastRowByProductAndKey);
        if ($productIds === []) {
            return;
        }

        $batchContexts = $this->batchPrefetchProductCategoriesContexts($productIds);

        $allOptionIds = [];
        foreach ($lastRowByProductAndKey as $rows) {
            foreach ($rows as $row) {
                $oid = (int)($row['id'] ?? 0);
                if ($oid > 0) {
                    $allOptionIds[$oid] = true;
                }
            }
        }

        $unionCategories = [];
        foreach ($batchContexts as $ctx) {
            foreach ($ctx['category_ids'] as $cid) {
                $cid = (int)$cid;
                if ($cid > 0) {
                    $unionCategories[$cid] = true;
                }
            }
        }

        $globalLinksByOptionId = [];
        if ($allOptionIds !== [] && $unionCategories !== []) {
            $globalLinksByOptionId = $this->captionOverlayResolver->loadCategoryOptionLinksGrouped(
                array_keys($allOptionIds),
                array_keys($unionCategories)
            );
        }

        foreach ($lastRowByProductAndKey as $productId => $rowsByKey) {
            $ctx = $batchContexts[$productId] ?? ['category_ids' => [], 'parent_category_id' => 0];
            $overlay = $this->captionOverlayResolver->buildCaptionDescriptionOverlayFromRowsAndLinks(
                $rowsByKey,
                $ctx,
                $this->captionOverlayResolver->filterCategoryOptionLinksByCategories(
                    $globalLinksByOptionId,
                    $ctx['category_ids']
                )
            );
            if (!isset($result[$productId])) {
                $result[$productId] = [];
            }
            $this->applyOverlayToOptionData($result[$productId], $overlay);
        }
    }

    /**
     * @param int|int[] $productIdOrIds
     */
    protected function buildProductOptionsQuery($productIdOrIds, bool $includeMetadata): xPDOQuery
    {
        $c = $this->xpdo->newQuery(msProductOption::class);
        $c->rightJoin(msOption::class, 'msOption', 'msProductOption.key=msOption.key');

        if ($includeMetadata) {
            $c->leftJoin(msOptionGroup::class, 'OptionGroup', 'OptionGroup.id=msOption.option_group_id');
            $c->select('OptionGroup.name AS group_name');
        }

        if (is_array($productIdOrIds)) {
            $c->where(['msProductOption.product_id:IN' => $productIdOrIds]);
        } else {
            $c->where(['msProductOption.product_id' => $productIdOrIds]);
        }

        $c->select($this->xpdo->getSelectColumns(msOption::class, 'msOption'));
        $c->select($this->xpdo->getSelectColumns(msProductOption::class, 'msProductOption', '', ['key'], true));

        return $c;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $option
     */
    protected function appendOptionRow(array &$data, array $option): void
    {
        $optionKey = (string) $option['key'];
        if (isset($data[$optionKey])) {
            $data[$optionKey][] = $option['value'];
        } else {
            $data[$optionKey] = [$option['value']];
        }

        foreach ($option as $key => $value) {
            $data[$optionKey . '.' . (string) $key] = $value;
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, array{caption: string, description: string}> $overlay
     */
    protected function applyOverlayToOptionData(array &$data, array $overlay): void
    {
        foreach ($overlay as $optionKey => $meta) {
            $data[$optionKey . '.caption'] = $meta['caption'];
            $data[$optionKey . '.description'] = $meta['description'];
        }
    }
}
