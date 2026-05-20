<?php

namespace MiniShop3\Services\Category;

use MiniShop3\Model\msCategory;
use MiniShop3\Model\msCategoryOption;
use MiniShop3\Model\msOption;
use MiniShop3\Model\msOptionGroup;
use MODX\Revolution\modX;
use xPDO\Om\xPDOQuery;

/**
 * Service for working with category options
 *
 * Handles loading, processing and caching of product options
 * linked to categories
 */
class CategoryOptionService
{
    /** @var modX */
    protected $modx;

    /** @var array Cache of option keys by categories */
    protected $optionKeysCache = [];

    /**
     * @param modX $modx
     */
    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * Get option keys for category
     *
     * @param msCategory $category
     * @param bool $force Force cache refresh
     * @return array
     */
    public function getOptionKeys(msCategory $category, bool $force = false): array
    {
        $categoryId = $category->get('id');

        if (!isset($this->optionKeysCache[$categoryId]) || $force) {
            $query = $this->buildOptionQuery($category);
            $query->groupby('msOption.id');
            $query->select('msOption.key');

            $this->optionKeysCache[$categoryId] = $query->prepare() && $query->stmt->execute()
                ? $query->stmt->fetchAll(\PDO::FETCH_COLUMN)
                : [];
        }

        return $this->optionKeysCache[$categoryId];
    }

    /**
     * Build query for selecting category options
     *
     * @param msCategory $category
     * @return xPDOQuery
     */
    public function buildOptionQuery(msCategory $category): xPDOQuery
    {
        $query = $this->modx->newQuery(msOption::class);
        $query->leftJoin(msCategoryOption::class, 'msCategoryOption', 'msCategoryOption.option_id = msOption.id');
        $query->leftJoin(msOptionGroup::class, 'OptionGroup', 'OptionGroup.id = msOption.option_group_id');
        $query->sortby('msCategoryOption.position');
        $query->where(['msCategoryOption.active' => 1]);

        $categoryId = $category->get('id');
        if (!empty($categoryId)) {
            $query->where(['msCategoryOption.category_id:IN' => [$categoryId]]);
        }

        return $query;
    }

    /**
     * Get option fields for category
     *
     * Returns array of options with all their parameters,
     * including current values and ExtJS fields for editing
     *
     * @param msCategory $category
     * @param array $keys Filter by option keys (empty for all options)
     * @return array
     */
    public function getOptionFields(msCategory $category, array $keys = []): array
    {
        $fields = [];
        $query = $this->buildOptionQuery($category);

        $query->select([
            $this->modx->getSelectColumns(msOption::class, 'msOption'),
            $this->modx->getSelectColumns(
                msCategoryOption::class,
                'msCategoryOption',
                '',
                ['id', 'option_id', 'category_id'],
                true
            ),
            'OptionGroup.name AS group_name',
        ]);

        if (!empty($keys)) {
            $query->where(['msOption.key:IN' => $keys]);
        }

        $options = $this->modx->getIterator(msOption::class, $query);

        /** @var msOption $option */
        foreach ($options as $option) {
            $field = $option->toArray();

            $value = $option->getValue($category->get('id'));
            $field['value'] = !is_null($value) ? $value : $field['value'];

            $field['ext_field'] = $option->getManagerField($field);

            $fields[] = $field;
        }

        return $fields;
    }

    /**
     * Clear option cache for category
     *
     * @param int|null $categoryId Category ID or null to clear all cache
     * @return void
     */
    public function clearCache(?int $categoryId = null): void
    {
        if ($categoryId === null) {
            $this->optionKeysCache = [];
        } else {
            unset($this->optionKeysCache[$categoryId]);
        }
    }
}
