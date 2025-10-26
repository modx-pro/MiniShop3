<?php

namespace MiniShop3\Services\Product;

use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductData;
use MiniShop3\Model\msVendor;
use MiniShop3\MiniShop3;
use MODX\Revolution\modX;
use xPDO\Om\xPDOQuery;

/**
 * Сервис для работы с товарами
 *
 * Выносим бизнес-логику из модели msProduct в отдельный сервис
 * для улучшения тестируемости и разделения ответственности
 */
class ProductService
{
    /** @var modX */
    protected $modx;

    /** @var MiniShop3|null */
    protected $ms3;

    /**
     * @param modX $modx
     */
    public function __construct(modX $modx)
    {
        $this->modx = $modx;

        if ($modx->services->has('ms3')) {
            $this->ms3 = $modx->services->get('ms3');
        }
    }

    /**
     * Обработка сохранения товара
     *
     * Если товар меняет тип (был не msProduct, стал msProduct),
     * то удаляем старые данные msProductData и показываем в дереве
     *
     * @param msProduct $product
     * @param string $oldClassKey Старый class_key до сохранения
     * @return bool
     */
    public function handleProductSave(msProduct $product, string $oldClassKey): bool
    {
        // Если товар был другим типом ресурса и стал msProduct
        if (!$product->isNew() && $oldClassKey !== msProduct::class) {
            // Удаляем старые данные msProductData, если они есть
            $product->loadData()->remove();

            // Показываем товар в дереве
            $product->set('show_in_tree', true);
        } else {
            // Для новых товаров или при сохранении msProduct - просто загружаем Data
            $product->loadData();
        }

        return true;
    }

    /**
     * Дублирование товара со всеми связанными данными
     *
     * @param msProduct $product Исходный товар
     * @param msProduct $newProduct Новый товар (уже дублированный родителем)
     * @return msProduct
     */
    public function duplicateProduct(msProduct $product, msProduct $newProduct): msProduct
    {
        // Копируем категории, опции и связи из Data
        $data = $product->loadData();

        // Устанавливаем данные для дублирования
        $newProduct->set('categories', $data->get('categories'));
        $newProduct->set('options', $data->get('options'));
        $newProduct->set('links', $data->get('links'));

        // Очищаем изображения (они будут скопированы отдельно)
        $newProduct->set('image', '');
        $newProduct->set('thumb', '');

        return $newProduct;
    }

    /**
     * Получить соседние товары (левые и правые)
     *
     * Используется для навигации по товарам одного уровня
     *
     * @param msProduct $product
     * @return array ['left' => [id1, id2, ...], 'right' => [id3, id4, ...]]
     */
    public function getNeighborProducts(msProduct $product): array
    {
        $query = $this->modx->newQuery(msProduct::class, [
            'parent' => $product->get('parent'),
            'class_key' => msProduct::class
        ]);
        $query->sortby('menuindex', 'ASC');
        $query->select('id');

        if (!$query->prepare() || !$query->stmt->execute()) {
            return ['left' => [], 'right' => []];
        }

        $ids = $query->stmt->fetchAll(\PDO::FETCH_COLUMN);
        $currentIndex = array_search($product->get('id'), $ids);

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

    /**
     * Обработка товара для вывода на фронтенд
     *
     * Подготавливает данные товара, форматирует цены, веса,
     * устанавливает плейсхолдеры и загружает лексиконы
     *
     * @param msProduct $product
     * @return void
     */
    public function processForDisplay(msProduct $product): void
    {
        // Обрабатываем данные товара
        /** @var msProductData $data */
        if ($data = $product->getOne('Data')) {
            $placeholders = $data->toArray();

            // Получаем цену с учетом модификаторов
            $originalPrice = $placeholders['price'];
            $placeholders['price'] = $product->getPrice($placeholders);

            // Если цена снизилась - устанавливаем старую цену
            if ($placeholders['price'] < $originalPrice) {
                $placeholders['old_price'] = $originalPrice;
            }

            // Получаем вес с учетом модификаторов
            $placeholders['weight'] = $product->getWeight($placeholders);

            // Модифицируем поля через плагины
            $placeholders = $product->modifyFields($placeholders);

            // Форматируем для вывода
            if ($this->ms3) {
                $placeholders['price'] = $this->ms3->format->price($placeholders['price']);
                $placeholders['old_price'] = $this->ms3->format->price($placeholders['old_price']);
                $placeholders['weight'] = $this->ms3->format->weight($placeholders['weight']);
            }

            // Удаляем id чтобы не перезаписать ID ресурса
            unset($placeholders['id']);

            $this->modx->setPlaceholders($placeholders);

            // Загружаем опции товара
            $product->loadOptions();
            $this->modx->setPlaceholders($product->options ?? []);
        }

        // Обрабатываем данные производителя
        /** @var msVendor $vendor */
        if ($vendor = $product->getOne('Vendor')) {
            $this->modx->setPlaceholders($vendor->toArray('vendor.'));
        }

        // Загружаем необходимые лексиконы
        $this->modx->lexicon->load('minishop3:default');
        $this->modx->lexicon->load('minishop3:cart');
        $this->modx->lexicon->load('minishop3:product');
    }
}
