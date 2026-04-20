<?php

namespace MiniShop3\Services\Option;

use MiniShop3\Model\msCategoryMember;
use MiniShop3\Model\msCategoryOption;
use MiniShop3\Model\msOption;
use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductOption;
use MODX\Revolution\modCategory;
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
     * @param bool $includeMetadata Include category metadata (default: true for backward compatibility)
     * @return array Option data with keys like ['color' => ['Red'], 'color.caption' => 'Color']
     */
    public function loadForProduct(int $productId, bool $includeMetadata = true): array
    {
        $c = $this->xpdo->newQuery(msProductOption::class);
        $c->rightJoin(msOption::class, 'msOption', 'msProductOption.key=msOption.key');

        if ($includeMetadata) {
            $c->leftJoin(modCategory::class, 'Category', 'Category.id=msOption.modcategory_id');
            $c->select('Category.category AS category_name');
        }

        $c->where(['msProductOption.product_id' => $productId]);
        $c->select($this->xpdo->getSelectColumns(msOption::class, 'msOption'));
        $c->select($this->xpdo->getSelectColumns(msProductOption::class, 'msProductOption', '', ['key'], true));

        $data = [];
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
            }
        }

        return $data;
    }

    /**
     * Load options for multiple products (batch loading for catalog pages)
     *
     * NEW METHOD - prevents N+1 problem in catalog listings
     *
     * @param array $productIds Array of product IDs
     * @param bool $includeMetadata Include category metadata
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
            $c->leftJoin(modCategory::class, 'Category', 'Category.id=msOption.modcategory_id');
            $c->select('Category.category AS category_name');
        }

        $c->where(['msProductOption.product_id:IN' => $productIds]);
        $c->select($this->xpdo->getSelectColumns(msOption::class, 'msOption'));
        $c->select($this->xpdo->getSelectColumns(msProductOption::class, 'msProductOption', '', ['key'], true));

        $result = [];
        if ($c->prepare() && $c->stmt->execute()) {
            while ($option = $c->stmt->fetch(\PDO::FETCH_ASSOC)) {
                $productId = $option['product_id'];

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
            }
        }

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

        // Join MODX category for category_name (for grouping in admin UI)
        $c->leftJoin(modCategory::class, '`Category`', '`Category`.id = `msOption`.modcategory_id');

        $c->select([
            $this->xpdo->getSelectColumns(msOption::class, '`msOption`'),
            $this->xpdo->getSelectColumns(
                msCategoryOption::class,
                '`msCategoryOption`',
                '',
                ['id', 'option_id', 'category_id'],
                true
            ),
            '`Category`.category AS `category_name`',
        ]);

        // Preload ALL option values with single query (fixes N+1 Problem)
        $preloadedValues = $this->getValuesForProduct($productId);

        $options = $this->xpdo->getIterator(msOption::class, $c);

        /** @var msOption $option */
        foreach ($options as $option) {
            $field = $option->toArray();
            $key = $option->get('key');
            $optionType = $option->get('type');

            // Get value from preloaded data instead of querying DB
            if (isset($preloadedValues[$key])) {
                $value = $this->convertPreloadedValue($preloadedValues[$key], $optionType);
            } else {
                $value = null;
            }

            $field['value'] = !is_null($value) ? $value : $field['value'];
            $field['ext_field'] = $option->getManagerField($field);
            $field['schema'] = $option->getSchema($field);
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
}
