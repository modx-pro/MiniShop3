<?php

namespace MiniShop3\Services\Option;

use MiniShop3\Model\msCategoryMember;
use MiniShop3\Model\msCategoryOption;
use MiniShop3\Model\msOption;
use MiniShop3\Model\msOptionGroup;
use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductOption;
use xPDO\Om\xPDOQuery;
use xPDO\xPDO;

/**
 * Service for loading options data
 *
 * Extracted from msProductOption model to separate concerns
 * Handles all option loading operations including:
 * - Loading options for products (frontend)
 * - Building option fields configuration (admin forms)
 * - Getting available option keys
 */
class OptionLoaderService
{
    /** @var xPDO */
    protected $xpdo;

    /**
     * @param xPDO $xpdo
     */
    public function __construct(xPDO $xpdo)
    {
        $this->xpdo = $xpdo;
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
        $c = $this->xpdo->newQuery(msProductOption::class);
        $c->rightJoin(msOption::class, 'msOption', 'msProductOption.key=msOption.key');

        if ($includeMetadata) {
            $c->leftJoin(msOptionGroup::class, 'OptionGroup', 'OptionGroup.id=msOption.option_group_id');
            $c->select('OptionGroup.name AS group_name');
        }

        $c->where(['msProductOption.product_id' => $productId]);
        $c->select($this->xpdo->getSelectColumns(msOption::class, 'msOption'));
        $c->select($this->xpdo->getSelectColumns(msProductOption::class, 'msProductOption', '', ['key'], true));

        $data = [];
        $lastRowByOptionKey = [];
        if ($c->prepare() && $c->stmt->execute()) {
            while ($option = $c->stmt->fetch(\PDO::FETCH_ASSOC)) {
                // If the option is repeated, its value will be an array
                if (isset($data[$option['key']])) {
                    $data[$option['key']][] = $option['value'];
                } else {
                    $data[$option['key']] = [$option['value']];
                }

                // Add option metadata as separate keys (color.caption, color.description, etc.)
                foreach ($option as $key => $value) {
                    $data[$option['key'] . '.' . $key] = $value;
                }

                $lastRowByOptionKey[$option['key']] = $option;
            }
        }

        $overlay = $this->buildCaptionDescriptionOverlayForProduct($productId, $lastRowByOptionKey);
        foreach ($overlay as $optionKey => $meta) {
            $data[$optionKey . '.caption'] = $meta['caption'];
            $data[$optionKey . '.description'] = $meta['description'];
        }

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
        if (empty($productIds)) {
            return [];
        }

        $c = $this->xpdo->newQuery(msProductOption::class);
        $c->rightJoin(msOption::class, 'msOption', 'msProductOption.key=msOption.key');

        if ($includeMetadata) {
            $c->leftJoin(msOptionGroup::class, 'OptionGroup', 'OptionGroup.id=msOption.option_group_id');
            $c->select('OptionGroup.name AS group_name');
        }

        $c->where(['msProductOption.product_id:IN' => $productIds]);
        $c->select($this->xpdo->getSelectColumns(msOption::class, 'msOption'));
        $c->select($this->xpdo->getSelectColumns(msProductOption::class, 'msProductOption', '', ['key'], true));

        $result = [];
        $lastRowByProductAndKey = [];
        if ($c->prepare() && $c->stmt->execute()) {
            while ($option = $c->stmt->fetch(\PDO::FETCH_ASSOC)) {
                $productId = (int)$option['product_id'];

                if (!isset($result[$productId])) {
                    $result[$productId] = [];
                }

                // Same logic as loadForProduct()
                if (isset($result[$productId][$option['key']])) {
                    $result[$productId][$option['key']][] = $option['value'];
                } else {
                    $result[$productId][$option['key']] = [$option['value']];
                }

                foreach ($option as $key => $value) {
                    $result[$productId][$option['key'] . '.' . $key] = $value;
                }

                $lastRowByProductAndKey[$productId][$option['key']] = $option;
            }
        }

        $this->applyCaptionDescriptionOverlaysForManyProducts($result, $lastRowByProductAndKey);

        return $result;
    }

    /**
     * Get option fields configuration for product form (admin)
     *
     * Replaces: msProductOption::getOptionFields()
     * Already optimized: N+1 Problem solved with preloading
     *
     * @param int $productId Product ID
     * @param int|null $parentId Parent category ID (optional)
     * @return array Array of field configurations for ExtJS
     */
    public function getFieldsForProduct(int $productId, ?int $parentId = null): array
    {
        $fields = [];

        /** @var xPDOQuery $c */
        $c = $this->prepareOptionListCriteria($productId, $parentId);
        $c->sortby('msCategoryOption.position');

        // Join msOptionGroup for group_name (for grouping in admin UI)
        $c->leftJoin(msOptionGroup::class, '`OptionGroup`', '`OptionGroup`.id = `msOption`.option_group_id');

        // Exclude msCategoryOption.caption/description from the select — after PR #203 these
        // columns shadow msOption.caption/description during xPDO hydration and leave the option
        // labels empty. The per-category override is layered on top via the overlay below.
        $c->select([
            $this->xpdo->getSelectColumns(msOption::class, '`msOption`'),
            $this->xpdo->getSelectColumns(
                msCategoryOption::class,
                '`msCategoryOption`',
                '',
                ['id', 'option_id', 'category_id', 'caption', 'description'],
                true
            ),
            '`OptionGroup`.name AS `group_name`',
        ]);

        // Preload ALL option values with single query (fixes N+1 Problem)
        $preloadedValues = $this->getValuesForProduct($productId);

        // Materialize iterator to an array: we need two passes (globals map, then field building).
        // Same rows as getCollection would load; iterator avoids duplicating the query.
        $options = [];
        foreach ($this->xpdo->getIterator(msOption::class, $c) as $option) {
            $options[] = $option;
        }

        $globalsByOptionId = [];
        foreach ($options as $option) {
            $oid = (int)$option->get('id');
            $globalsByOptionId[$oid] = [
                'caption' => (string)$option->get('caption'),
                'description' => (string)($option->get('description') ?? ''),
            ];
        }
        $effectiveByOptionId = $this->resolveEffectiveCaptionDescriptionByOptionIds(
            $productId,
            $globalsByOptionId,
            $parentId
        );

        /** @var msOption $option */
        foreach ($options as $option) {
            $field = $option->toArray();
            $key = $option->get('key');
            $optionType = $option->get('type');
            $oid = (int)$option->get('id');
            if (isset($effectiveByOptionId[$oid])) {
                $field['caption'] = $effectiveByOptionId[$oid]['caption'];
                $field['description'] = $effectiveByOptionId[$oid]['description'];
            }

            // Get value from preloaded data instead of querying DB
            if (isset($preloadedValues[$key])) {
                $value = $this->convertPreloadedValue($preloadedValues[$key], $optionType);
            } else {
                $value = null;
            }

            $field['value'] = !is_null($value) ? $value : $field['value'];
            $field['ext_field'] = $option->getManagerField($field);
            $fields[] = $field;
        }

        return $fields;
    }

    /**
     * Get available option keys for product
     *
     * Replaces: msProductOption::getOptionKeys()
     *
     * @param int $productId Product ID
     * @param int|null $parentId Parent category ID (optional)
     * @return array Array of option keys
     */
    public function getOptionKeys(int $productId, ?int $parentId = null): array
    {
        /** @var xPDOQuery $c */
        $c = $this->prepareOptionListCriteria($productId, $parentId);
        $c->select('msOption.key');

        if (!$c->prepare()) {
            return [];
        }

        if (!$c->stmt->execute()) {
            return [];
        }

        $result = $c->stmt->fetchAll(\PDO::FETCH_COLUMN);

        return $result;
    }

    /**
     * Get option values for product (without metadata)
     *
     * Helper method for preloading values
     *
     * @param int $productId Product ID
     * @param array $keys Optional: filter by specific keys
     * @return array ['color' => ['Red', 'Blue'], 'size' => ['L']]
     */
    protected function getValuesForProduct(int $productId, array $keys = []): array
    {
        $c = $this->xpdo->newQuery(msProductOption::class, ['product_id' => $productId]);
        $c->select('key,value');
        $c->sortby('value');

        if (!empty($keys)) {
            $c->where(['key:IN' => $keys]);
        }

        $values = [];
        if ($c->prepare() && $c->stmt->execute()) {
            while ($row = $c->stmt->fetch(\PDO::FETCH_ASSOC)) {
                if (isset($values[$row['key']])) {
                    $values[$row['key']][] = $row['value'];
                } else {
                    $values[$row['key']] = [$row['value']];
                }
            }
        }

        return $values;
    }

    /**
     * Prepare criteria for querying available options for product categories
     *
     * Finds all categories where product belongs (direct parent + additional categories)
     * and returns query for active options in those categories
     *
     * @param int $productId Product ID
     * @param int|null $parentId Parent category ID (fallback if product not found)
     * @return xPDOQuery Query for msOption with active category options
     */
    protected function prepareOptionListCriteria(int $productId, ?int $parentId = null): xPDOQuery
    {
        $categories = [];

        // Get additional categories from ms3_category_members
        $q = $this->xpdo->newQuery(msCategoryMember::class, ['product_id' => $productId]);
        $q->select('category_id');
        if ($q->prepare() && $q->stmt->execute()) {
            $categories = $q->stmt->fetchAll(\PDO::FETCH_COLUMN);
        }

        // Get direct parent category
        $product = $this->xpdo->getObject(msProduct::class, $productId);
        if ($product) {
            $categories[] = $product->get('parent');
        } elseif ($parentId !== null) {
            $categories[] = (int)$parentId;
        }

        $categories = array_unique($categories);

        // Build query for active options in these categories
        $c = $this->xpdo->newQuery(msOption::class);

        $c->leftJoin(msCategoryOption::class, '`msCategoryOption`', '`msCategoryOption`.option_id = `msOption`.id');

        $c->where(['msCategoryOption.active' => 1]);

        if (!empty($categories[0])) {
            $c->where(['`msCategoryOption`.category_id:IN' => $categories]);
        }

        $c->groupby('`msOption`.id');

        return $c;
    }

    /**
     * Convert preloaded values to format expected by option type.
     *
     * - Multi-value types (comboMultiple, comboColors, comboOptions): return all values
     *   as [['value' => 'S'], ['value' => 'M']]
     * - Scalar types (textfield, numberfield, combobox, comboBoolean, etc.): return first value
     *
     * msOption.type is stored lowerCamelCase (numberfield, comboMultiple, …), so match
     * case-insensitively — the legacy 'ComboMultiple' string match dropped all but the first
     * value when reloading the product form.
     *
     * @param array $values Array of values from getValuesForProduct()
     * @param string $optionType Option type
     * @return mixed Converted value
     */
    protected function convertPreloadedValue(array $values, string $optionType)
    {
        $multiTypes = ['combomultiple', 'combocolors', 'combooptions'];
        if (in_array(strtolower($optionType), $multiTypes, true)) {
            $result = [];
            foreach ($values as $val) {
                if ($val !== '') {
                    $result[] = ['value' => $val];
                }
            }
            return $result;
        }

        return !empty($values) ? $values[0] : null;
    }

    /**
     * Product category membership and parent id in one pass (single msProduct load).
     *
     * Policy (issue #200): overrides are resolved using links in these categories; tie-breaking
     * for the same option across categories uses {@see pickWinningCategoryOptionLink()}.
     *
     * @return array{category_ids: int[], parent_category_id: int}
     */
    protected function getProductCategoriesContext(
        int $productId,
        ?int $fallbackParentId = null,
        ?int $knownParentCategoryId = null
    ): array {
        $memberIds = [];
        $q = $this->xpdo->newQuery(msCategoryMember::class, ['product_id' => $productId]);
        $q->select('category_id');
        if ($q->prepare() && $q->stmt->execute()) {
            $memberIds = $q->stmt->fetchAll(\PDO::FETCH_COLUMN);
        }

        $parentCategoryId = 0;
        if ($knownParentCategoryId !== null && $knownParentCategoryId > 0) {
            $parentCategoryId = (int)$knownParentCategoryId;
        } else {
            $product = $this->xpdo->getObject(msProduct::class, $productId);
            if ($product) {
                $parentCategoryId = (int)$product->get('parent');
            } elseif ($fallbackParentId !== null) {
                $parentCategoryId = (int)$fallbackParentId;
            }
        }

        $ids = $memberIds;
        if ($parentCategoryId > 0) {
            $ids[] = $parentCategoryId;
        }

        $categoryIds = array_values(array_unique(array_filter(array_map('intval', $ids), static function ($id) {
            return $id > 0;
        })));

        return [
            'category_ids' => $categoryIds,
            'parent_category_id' => $parentCategoryId,
        ];
    }

    /**
     * Category ids where the product belongs: additional members plus direct parent.
     *
     * @return int[]
     */
    protected function getProductCategoryIdList(int $productId, ?int $fallbackParentId = null): array
    {
        return $this->getProductCategoriesContext($productId, $fallbackParentId)['category_ids'];
    }

    /**
     * When the same option is linked in several product categories with different overrides,
     * pick one row: parent category first, then lowest msCategoryOption.position among remaining,
     * then lowest category_id (stable tie-break; aligns with issue #200).
     *
     * @param array<int, array<string, mixed>> $linkRows
     * @return array<string, mixed>|null
     */
    protected function pickWinningCategoryOptionLink(array $linkRows, int $parentCategoryId): ?array
    {
        if ($linkRows === []) {
            return null;
        }

        usort($linkRows, static function ($a, $b) use ($parentCategoryId) {
            $aParent = ((int)$a['category_id'] === $parentCategoryId) ? 0 : 1;
            $bParent = ((int)$b['category_id'] === $parentCategoryId) ? 0 : 1;
            if ($aParent !== $bParent) {
                return $aParent <=> $bParent;
            }
            if ((int)$a['position'] !== (int)$b['position']) {
                return (int)$a['position'] <=> (int)$b['position'];
            }

            return (int)$a['category_id'] <=> (int)$b['category_id'];
        });

        return $linkRows[0];
    }

    /**
     * Non-empty override wins; null or whitespace-only string inherits global.
     */
    public function mergeCaptionDescription(?string $override, string $global): string
    {
        if ($override === null) {
            return $global;
        }
        $trimmed = trim($override);

        return $trimmed !== '' ? $trimmed : $global;
    }

    /**
     * @param array<int, array{caption: string, description: string}> $globalsByOptionId
     * @return array<int, array{caption: string, description: string}>
     */
    protected function resolveEffectiveCaptionDescriptionByOptionIds(
        int $productId,
        array $globalsByOptionId,
        ?int $knownParentCategoryId = null
    ): array {
        if ($globalsByOptionId === []) {
            return [];
        }

        $ctx = $this->getProductCategoriesContext($productId, null, $knownParentCategoryId);
        if ($ctx['category_ids'] === []) {
            return $globalsByOptionId;
        }

        $linksByOptionId = $this->loadCategoryOptionLinksGrouped(
            array_keys($globalsByOptionId),
            $ctx['category_ids']
        );

        return $this->resolveEffectiveCaptionDescriptionWithContext($globalsByOptionId, $ctx, $linksByOptionId);
    }

    /**
     * @param array<int, array{caption: string, description: string}> $globalsByOptionId
     * @param array{category_ids: int[], parent_category_id: int} $context
     * @param array<int, array<int, array<string, mixed>>> $linksByOptionId
     * @return array<int, array{caption: string, description: string}>
     */
    protected function resolveEffectiveCaptionDescriptionWithContext(
        array $globalsByOptionId,
        array $context,
        array $linksByOptionId
    ): array {
        if ($globalsByOptionId === []) {
            return [];
        }

        $categories = $context['category_ids'];
        $parentCategoryId = (int)$context['parent_category_id'];
        if ($categories === []) {
            return $globalsByOptionId;
        }

        $out = [];
        foreach ($globalsByOptionId as $optionId => $global) {
            $caption = $global['caption'];
            $description = $global['description'];
            if (!empty($linksByOptionId[$optionId])) {
                $winner = $this->pickWinningCategoryOptionLink($linksByOptionId[$optionId], $parentCategoryId);
                if ($winner !== null) {
                    $caption = $this->mergeCaptionDescription(
                        isset($winner['caption']) ? (string)$winner['caption'] : null,
                        $caption
                    );
                    $description = $this->mergeCaptionDescription(
                        isset($winner['description']) ? (string)$winner['description'] : null,
                        $description
                    );
                }
            }
            $out[(int)$optionId] = [
                'caption' => $caption,
                'description' => $description,
            ];
        }

        return $out;
    }

    /**
     * @param int[] $optionIds
     * @param int[] $categoryIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    protected function loadCategoryOptionLinksGrouped(array $optionIds, array $categoryIds): array
    {
        if ($optionIds === [] || $categoryIds === []) {
            return [];
        }

        $c = $this->xpdo->newQuery(msCategoryOption::class, [
            'option_id:IN' => $optionIds,
            'category_id:IN' => $categoryIds,
        ]);
        $c->select($this->xpdo->getSelectColumns(msCategoryOption::class, 'msCategoryOption'));

        $linksByOptionId = [];
        if ($c->prepare() && $c->stmt->execute()) {
            while ($row = $c->stmt->fetch(\PDO::FETCH_ASSOC)) {
                $oid = (int)$row['option_id'];
                if (!isset($linksByOptionId[$oid])) {
                    $linksByOptionId[$oid] = [];
                }
                $linksByOptionId[$oid][] = $row;
            }
        }

        return $linksByOptionId;
    }

    /**
     * @param array<int, array<int, array<string, mixed>>> $linksByOptionId
     * @param int[] $categoryIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    protected function filterCategoryOptionLinksByCategories(array $linksByOptionId, array $categoryIds): array
    {
        if ($linksByOptionId === [] || $categoryIds === []) {
            return [];
        }

        $allowed = array_flip($categoryIds);
        $out = [];
        foreach ($linksByOptionId as $oid => $rows) {
            $filtered = [];
            foreach ($rows as $row) {
                if (isset($allowed[(int)$row['category_id']])) {
                    $filtered[] = $row;
                }
            }
            if ($filtered !== []) {
                $out[$oid] = $filtered;
            }
        }

        return $out;
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
        $out = [];
        foreach ($productIds as $pid) {
            $out[$pid] = [
                'category_ids' => [],
                'parent_category_id' => 0,
            ];
        }

        if ($productIds === []) {
            return [];
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
                if (isset($out[$pid])) {
                    $out[$pid]['parent_category_id'] = $parent;
                    if ($parent > 0) {
                        $out[$pid]['category_ids'][] = $parent;
                    }
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
    protected function applyCaptionDescriptionOverlaysForManyProducts(array &$result, array $lastRowByProductAndKey): void
    {
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
        $allOptionIds = array_keys($allOptionIds);

        $unionCategories = [];
        foreach ($batchContexts as $ctx) {
            $unionCategories = array_merge($unionCategories, $ctx['category_ids']);
        }
        $unionCategories = array_values(array_unique(array_filter($unionCategories, static function ($id) {
            return (int)$id > 0;
        })));

        $globalLinksByOptionId = [];
        if ($allOptionIds !== [] && $unionCategories !== []) {
            $globalLinksByOptionId = $this->loadCategoryOptionLinksGrouped($allOptionIds, $unionCategories);
        }

        foreach ($lastRowByProductAndKey as $productId => $rowsByKey) {
            $ctx = $batchContexts[$productId] ?? ['category_ids' => [], 'parent_category_id' => 0];
            $linksForProduct = $this->filterCategoryOptionLinksByCategories(
                $globalLinksByOptionId,
                $ctx['category_ids']
            );
            $overlay = $this->buildCaptionDescriptionOverlayFromRowsAndLinks(
                $rowsByKey,
                $ctx,
                $linksForProduct
            );
            foreach ($overlay as $optionKey => $meta) {
                $result[$productId][$optionKey . '.caption'] = $meta['caption'];
                $result[$productId][$optionKey . '.description'] = $meta['description'];
            }
        }
    }

    /**
     * @param array<string, array<string, mixed>> $lastRowByOptionKey
     * @param array{category_ids: int[], parent_category_id: int} $context
     * @param array<int, array<int, array<string, mixed>>> $linksByOptionIdForProduct
     * @return array<string, array{caption: string, description: string}>
     */
    protected function buildCaptionDescriptionOverlayFromRowsAndLinks(
        array $lastRowByOptionKey,
        array $context,
        array $linksByOptionIdForProduct
    ): array {
        [$globalsByOptionId, $keyByOptionId] = $this->extractGlobalsAndKeyMap($lastRowByOptionKey);

        $effectiveById = $this->resolveEffectiveCaptionDescriptionWithContext(
            $globalsByOptionId,
            $context,
            $linksByOptionIdForProduct
        );

        $out = [];
        foreach ($keyByOptionId as $oid => $key) {
            if (isset($effectiveById[$oid])) {
                $out[$key] = $effectiveById[$oid];
            }
        }

        return $out;
    }

    /**
     * @param array<string, array<string, mixed>> $lastRowByOptionKey Last SQL row per option key (includes msOption.id, caption, description)
     * @return array<string, array{caption: string, description: string}>
     */
    protected function buildCaptionDescriptionOverlayForProduct(int $productId, array $lastRowByOptionKey): array
    {
        [$globalsByOptionId, $keyByOptionId] = $this->extractGlobalsAndKeyMap($lastRowByOptionKey);

        $effectiveById = $this->resolveEffectiveCaptionDescriptionByOptionIds($productId, $globalsByOptionId);
        $out = [];
        foreach ($keyByOptionId as $oid => $key) {
            if (isset($effectiveById[$oid])) {
                $out[$key] = $effectiveById[$oid];
            }
        }

        return $out;
    }

    /**
     * @param array<string, array<string, mixed>> $lastRowByOptionKey
     * @return array{0: array<int, array{caption: string, description: string}>, 1: array<int, string>}
     */
    protected function extractGlobalsAndKeyMap(array $lastRowByOptionKey): array
    {
        $globalsByOptionId = [];
        $keyByOptionId = [];
        foreach ($lastRowByOptionKey as $key => $row) {
            $oid = (int)($row['id'] ?? 0);
            if ($oid < 1) {
                continue;
            }
            $globalsByOptionId[$oid] = [
                'caption' => (string)($row['caption'] ?? ''),
                'description' => (string)($row['description'] ?? ''),
            ];
            $keyByOptionId[$oid] = $key;
        }

        return [$globalsByOptionId, $keyByOptionId];
    }
}

