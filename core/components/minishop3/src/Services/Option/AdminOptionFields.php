<?php

declare(strict_types=1);

namespace MiniShop3\Services\Option;

use MiniShop3\Controllers\Options\Types\msOptionType;
use MiniShop3\Model\msCategoryOption;
use MiniShop3\Model\msOption;
use MiniShop3\Model\msOptionGroup;
use MiniShop3\Model\msProductOption;
use xPDO\Om\xPDOQuery;
use xPDO\xPDO;

/**
 * Builds admin form option fields and resolves available option keys for a product.
 */
class AdminOptionFields
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
        /** @var xPDOQuery $c */
        $c = $this->prepareOptionListCriteria($productId, $parentId);
        $c->sortby('msCategoryOption.position');

        // Join msOptionGroup for group_name (for grouping in admin UI)
        $c->leftJoin(msOptionGroup::class, '`OptionGroup`', '`OptionGroup`.id = `msOption`.option_group_id');

        // Exclude msCategoryOption.caption/description from the select — after PR #203 these
        // columns shadow msOption.caption/description during xPDO hydration and leave the option
        // labels empty. The per-category override is layered on top via the overlay below.
        $c->select($this->xpdo->getSelectColumns(msOption::class, '`msOption`'));
        $c->select($this->xpdo->getSelectColumns(
            msCategoryOption::class,
            '`msCategoryOption`',
            '',
            ['id', 'option_id', 'category_id', 'caption', 'description'],
            true
        ));
        $c->select('`OptionGroup`.name AS `group_name`');

        $preloadedValues = $this->getValuesForProduct($productId);

        // Materialize iterator: two passes (globals map, then field building) without re-querying.
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
        $effectiveByOptionId = $this->captionOverlayResolver->resolveEffectiveCaptionDescriptionByOptionIds(
            $productId,
            $globalsByOptionId,
            $parentId
        );

        $fields = [];
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

            $value = isset($preloadedValues[$key])
                ? $this->convertPreloadedValue($preloadedValues[$key], $optionType)
                : null;
            if ($value !== null) {
                $field['value'] = $value;
            }

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

        if (!$c->prepare() || !$c->stmt->execute()) {
            return [];
        }

        return $c->stmt->fetchAll(\PDO::FETCH_COLUMN);
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

        if ($keys !== []) {
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
        $categories = $this->captionOverlayResolver->getProductCategoryIdList($productId, $parentId);

        $c = $this->xpdo->newQuery(msOption::class);
        $c->leftJoin(msCategoryOption::class, '`msCategoryOption`', '`msCategoryOption`.option_id = `msOption`.id');
        $c->where(['msCategoryOption.active' => 1]);

        if ($categories !== []) {
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
        if (msOptionType::isMultiValueType($optionType)) {
            $result = [];
            foreach ($values as $val) {
                if ($val !== '') {
                    $result[] = ['value' => $val];
                }
            }

            return $result;
        }

        return $values !== [] ? $values[0] : null;
    }
}
