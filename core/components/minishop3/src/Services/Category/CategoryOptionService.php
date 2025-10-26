<?php

namespace MiniShop3\Services\Category;

use MiniShop3\Model\msCategory;
use MiniShop3\Model\msCategoryOption;
use MiniShop3\Model\msOption;
use MODX\Revolution\modCategory;
use MODX\Revolution\modX;
use xPDO\Om\xPDOQuery;

/**
 * Сервис для работы с опциями категорий
 *
 * Отвечает за загрузку, обработку и кеширование опций товаров
 * привязанных к категориям
 */
class CategoryOptionService
{
    /** @var modX */
    protected $modx;

    /** @var array Кеш ключей опций по категориям */
    protected $optionKeysCache = [];

    /**
     * @param modX $modx
     */
    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * Получить ключи опций для категории
     *
     * @param msCategory $category
     * @param bool $force Принудительно обновить кеш
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
     * Построить запрос для выборки опций категории
     *
     * @param msCategory $category
     * @return xPDOQuery
     */
    public function buildOptionQuery(msCategory $category): xPDOQuery
    {
        $query = $this->modx->newQuery(msOption::class);
        $query->leftJoin(msCategoryOption::class, 'msCategoryOption', 'msCategoryOption.option_id = msOption.id');
        $query->leftJoin(modCategory::class, 'Category', 'Category.id = msOption.category_id');
        $query->sortby('msCategoryOption.position');
        $query->where(['msCategoryOption.active' => 1]);

        $categoryId = $category->get('id');
        if (!empty($categoryId)) {
            $query->where(['msCategoryOption.category_id:IN' => [$categoryId]]);
        }

        return $query;
    }

    /**
     * Получить поля опций для категории
     *
     * Возвращает массив опций со всеми их параметрами,
     * включая текущие значения и ExtJS поля для редактирования
     *
     * @param msCategory $category
     * @param array $keys Фильтр по ключам опций (если пусто - все опции)
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
            'Category.category AS category_name',
        ]);

        if (!empty($keys)) {
            $query->where(['msOption.key:IN' => $keys]);
        }

        $options = $this->modx->getIterator(msOption::class, $query);

        /** @var msOption $option */
        foreach ($options as $option) {
            $field = $option->toArray();

            // Получаем значение опции для текущей категории
            $value = $option->getValue($category->get('id'));
            $field['value'] = !is_null($value) ? $value : $field['value'];

            // Получаем ExtJS поле для менеджера
            $field['ext_field'] = $option->getManagerField($field);

            $fields[] = $field;
        }

        return $fields;
    }

    /**
     * Очистить кеш опций для категории
     *
     * @param int|null $categoryId ID категории или null для очистки всего кеша
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
