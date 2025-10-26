<?php

namespace MiniShop3\Services\Category;

use MiniShop3\Model\msCategory;
use MiniShop3\Model\msCategoryOption;
use MiniShop3\Model\msProduct;
use MODX\Revolution\modX;
use xPDO\Om\xPDOQuery;

/**
 * Сервис для работы с категориями
 *
 * Выносим бизнес-логику из модели msCategory в отдельный сервис
 * для улучшения тестируемости и разделения ответственности
 */
class CategoryService
{
    /** @var modX */
    protected $modx;

    /**
     * @param modX $modx
     */
    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * Обработка сохранения категории
     *
     * Если категория меняет тип (была не msCategory, стала msCategory),
     * то показываем все дочерние товары в дереве
     *
     * @param msCategory $category
     * @param string $oldClassKey Старый class_key до сохранения
     * @return bool
     */
    public function handleCategorySave(msCategory $category, string $oldClassKey): bool
    {
        // Если категория была другим типом ресурса и стала msCategory
        if (!$category->isNew() && $oldClassKey !== 'msCategory') {
            // Показываем дочерние элементы в дереве
            $category->set('hide_children_in_tree', false);

            // Обновляем все дочерние товары - показываем их в дереве
            $this->showChildProductsInTree($category->get('id'));
        }

        return true;
    }

    /**
     * Показать дочерние товары категории в дереве
     *
     * @param int $categoryId
     * @return bool
     */
    public function showChildProductsInTree(int $categoryId): bool
    {
        /** @var xPDOQuery $query */
        $query = $this->modx->newQuery(msProduct::class);
        $query->command('UPDATE');
        $query->where([
            'parent' => $categoryId,
            'class_key' => 'msProduct',
        ]);
        $query->set([
            'show_in_tree' => true,
        ]);

        if ($query->prepare()) {
            return $query->stmt->execute();
        }

        return false;
    }

    /**
     * Дублирование категории со всеми связанными данными
     *
     * @param msCategory $category Исходная категория
     * @param msCategory $newCategory Новая категория (уже дублированная родителем)
     * @return msCategory
     */
    public function duplicateCategory(msCategory $category, msCategory $newCategory): msCategory
    {
        // Копируем опции категории
        $this->duplicateCategoryOptions($category, $newCategory);

        return $newCategory;
    }

    /**
     * Копирование опций категории
     *
     * @param msCategory $sourceCategory
     * @param msCategory $targetCategory
     * @return bool
     */
    protected function duplicateCategoryOptions(msCategory $sourceCategory, msCategory $targetCategory): bool
    {
        $options = $sourceCategory->getMany('CategoryOptions');

        /** @var msCategoryOption $option */
        foreach ($options as $option) {
            /** @var msCategoryOption $newOption */
            $newOption = $this->modx->newObject(msCategoryOption::class);
            $newOption->fromArray($option->toArray(), '', true, true);
            $newOption->set('category_id', $targetCategory->get('id'));

            if (!$newOption->save()) {
                $this->modx->log(
                    modX::LOG_LEVEL_ERROR,
                    '[CategoryService] Failed to duplicate category option: ' . print_r($option->toArray(), true)
                );
                return false;
            }
        }

        return true;
    }

    /**
     * Получить соседние категории (левые и правые)
     *
     * Используется для навигации по категориям одного уровня
     *
     * @param msCategory $category
     * @return array ['left' => [id1, id2, ...], 'right' => [id3, id4, ...]]
     */
    public function getNeighborCategories(msCategory $category): array
    {
        $query = $this->modx->newQuery(msCategory::class, [
            'parent' => $category->get('parent'),
            'class_key' => 'msCategory'
        ]);
        $query->sortby('menuindex', 'ASC');
        $query->select('id');

        if (!$query->prepare() || !$query->stmt->execute()) {
            return ['left' => [], 'right' => []];
        }

        $ids = $query->stmt->fetchAll(\PDO::FETCH_COLUMN);
        $currentIndex = array_search($category->get('id'), $ids);

        if ($currentIndex === false) {
            return ['left' => [], 'right' => []];
        }

        $left = [];
        $right = [];

        foreach ($ids as $index => $id) {
            if ($index < $currentIndex) {
                $left[] = $id;
            } elseif ($index > $currentIndex) {
                $right[] = $id;
            }
        }

        return [
            'left' => array_reverse($left),
            'right' => $right,
        ];
    }
}
