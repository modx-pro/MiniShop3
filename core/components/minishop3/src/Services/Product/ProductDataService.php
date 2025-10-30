<?php

namespace MiniShop3\Services\Product;

use MiniShop3\Model\msCategoryMember;
use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductData;
use MiniShop3\Model\msProductLink;
use MiniShop3\Model\msProductOption;
use MiniShop3\Processors\RemoveCatalogs;
use MODX\Revolution\modX;

/**
 * Сервис для работы с данными товара
 *
 * Обрабатывает сохранение, удаление и модификацию данных msProductData,
 * включая категории, опции, связи и плагин-модификаторы
 */
class ProductDataService
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
     * Подготовка объекта перед сохранением
     *
     * Выполняет комплексную подготовку данных товара:
     * - Подготовка array полей (tags, color, size и т.д.) - удаление дубликатов, пустых значений
     * - Установка source_id для новых товаров
     * - Приведение числовых полей (price, old_price, weight) к типу float
     *
     * @param msProductData $productData
     * @return void
     */
    public function prepareObject(msProductData $productData): void
    {
        // Подготовка array полей (tags, color, size и т.д.)
        foreach ($productData->getArraysValues() as $name => $array) {
            $array = $productData->prepareOptionValues($array);
            $productData->set($name, $array);
        }

        // Установка source_id для новых товаров
        if ($productData->isNew()) {
            $productData->set('source_id', $this->modx->getOption('ms3_product_source_default', null, 1));
        }

        // Приведение числовых полей к типу float
        $productData->set('price', (float)$productData->get('price'));
        $productData->set('old_price', (float)$productData->get('old_price'));
        $productData->set('weight', (float)$productData->get('weight'));
    }

    /**
     * Сохранение дополнительных категорий товара
     *
     * Синхронизирует таблицу msCategoryMember с массивом категорий из поля 'categories'
     * Формат ожидаемых данных: JSON массив [3,4,27]
     *
     * ВАЖНО: msProductData::get('categories') переопределён и читает из БД,
     * поэтому используем рефлексию для получения значения из $_fields (POST данные)
     *
     * @param msProductData $productData
     * @return void
     */
    public function saveCategories(msProductData $productData): void
    {
        $productId = $productData->get('id');

        // Получаем значение напрямую из $_fields через рефлексию
        $reflection = new \ReflectionClass($productData);
        $property = $reflection->getProperty('_fields');
        $property->setAccessible(true);
        $fields = $property->getValue($productData);
        $categories = $fields['categories'] ?? null;

        // Преобразуем в массив ID
        if (is_string($categories)) {
            // JSON массив: "[3,4,27]"
            $categories = json_decode($categories, true);
            if (!is_array($categories)) {
                $categories = [];
            }
        } elseif (!is_array($categories)) {
            $categories = [];
        }

        // Удаляем все старые связи
        $this->modx->removeCollection(msCategoryMember::class, ['product_id' => $productId]);

        // Создаем новые связи
        foreach ($categories as $categoryId) {
            if (!empty($categoryId) && is_numeric($categoryId)) {
                /** @var msCategoryMember $member */
                $member = $this->modx->newObject(msCategoryMember::class);
                $member->set('product_id', $productId);
                $member->set('category_id', (int)$categoryId);
                $member->save();
            }
        }
    }

    /**
     * Сохранение опций товара
     *
     * Синхронизирует данные из JSON полей с таблицей msProductOption
     * через метод msProductOption::saveProductOptions()
     *
     * @param msProductData $productData
     * @param array|null $options Опции для сохранения (если null - собираются из JSON полей)
     * @return void
     */
    public function saveOptions(msProductData $productData, ?array $options = null): void
    {
        $productId = $productData->get('id');

        // Если опции не переданы, собираем из JSON полей msProductData
        if ($options === null) {
            $options = [];
            foreach ($productData->_fieldMeta as $key => $value) {
                if ($value['phptype'] === 'json' && !empty($productData->get($key))) {
                    $options = array_merge($options, $productData->get($key));
                }
            }
        }

        // Синхронизируем с таблицей опций
        /** @var msProductOption $optionInstance */
        $optionInstance = $this->modx->newObject(msProductOption::class);
        $optionInstance->saveProductOptions($productId, $options);
    }

    /**
     * Сохранение связей товара
     *
     * Синхронизирует таблицу msProductLink с массивом связей из поля 'links'
     * Связи бывают master->slave (товар является мастером) и slave->master (товар зависимый)
     *
     * @param msProductData $productData
     * @return void
     */
    public function saveLinks(msProductData $productData): void
    {
        $productId = $productData->get('id');
        $links = $productData->get('links');

        if (!is_array($links)) {
            return;
        }

        // Удаляем все старые связи где товар - master
        $this->modx->removeCollection(msProductLink::class, ['master' => $productId]);

        // Создаем новые связи
        foreach ($links as $link) {
            if (!empty($link['slave']) && !empty($link['link'])) {
                /** @var msProductLink $productLink */
                $productLink = $this->modx->newObject(msProductLink::class);
                $productLink->set('master', $productId);
                $productLink->set('slave', $link['slave']);
                $productLink->set('link', $link['link']);
                $productLink->save();
            }
        }
    }

    /**
     * Удаление товара со всеми связанными данными
     *
     * Удаляет опции, категории, связи, файлы и каталоги медиа-источников
     * Очищает базу от всех следов товара
     *
     * @param msProductData $productData
     * @param array $ancestors
     * @return bool
     */
    public function removeProduct(msProductData $productData, array $ancestors = []): bool
    {
        $productId = $productData->get('id');

        // Удаляем опции товара
        $this->modx->removeCollection(msProductOption::class, ['product_id' => $productId]);

        // Удаляем привязки к дополнительным категориям
        $this->modx->removeCollection(msCategoryMember::class, ['product_id' => $productId]);

        // Удаляем связи товара (где товар и master, и slave)
        $this->modx->removeCollection(msProductLink::class, [
            'master' => $productId,
            'OR:slave:=' => $productId
        ]);

        // Удаляем файлы товара (изображения, документы и т.д.)
        if ($productData->xpdo->getCount('msProductFile', ['product_id' => $productId]) > 0) {
            $source = $productData->initializeMediaSource($productData->Product->get('context_key'));
            if ($source) {
                $files = $productData->xpdo->getIterator('msProductFile', ['product_id' => $productId]);
                /** @var \msProductFile $file */
                foreach ($files as $file) {
                    $file->remove();
                }
            }
        }

        // Удаляем каталоги медиа-источников
        RemoveCatalogs::process($productData->xpdo, $productId);

        return true;
    }

    /**
     * Получить цену товара с учетом модификаторов плагинов
     *
     * Вызывает событие msOnGetProductPrice для модификации цены
     *
     * @param msProductData $productData
     * @param array $data Дополнительные данные товара
     * @return mixed|string
     */
    public function getModifiedPrice(msProductData $productData, array $data = [])
    {
        $price = !empty($data['price'])
            ? $data['price']
            : $productData->get('price');

        $response = $this->modx->invokeEvent('msOnGetProductPrice', [
            'price' => $price,
            'data' => $data,
        ]);

        if (is_array($response) && count($response) > 0) {
            foreach ($response as $value) {
                if (is_numeric($value)) {
                    $price = $value;
                }
            }
        }

        return $price;
    }

    /**
     * Получить вес товара с учетом модификаторов плагинов
     *
     * Вызывает событие msOnGetProductWeight для модификации веса
     *
     * @param msProductData $productData
     * @param array $data Дополнительные данные товара
     * @return mixed|string
     */
    public function getModifiedWeight(msProductData $productData, array $data = [])
    {
        $weight = !empty($data['weight'])
            ? $data['weight']
            : $productData->get('weight');

        $response = $this->modx->invokeEvent('msOnGetProductWeight', [
            'weight' => $weight,
            'data' => $data,
        ]);

        if (is_array($response) && count($response) > 0) {
            foreach ($response as $value) {
                if (is_numeric($value)) {
                    $weight = $value;
                }
            }
        }

        return $weight;
    }

    /**
     * Модифицировать поля товара через плагины
     *
     * Вызывает событие msOnGetProductFields для кастомной обработки полей товара
     *
     * @param msProductData $productData
     * @param array $data Поля товара
     * @return array Модифицированные поля
     */
    public function getModifiedFields(msProductData $productData, array $data = []): array
    {
        $response = $this->modx->invokeEvent('msOnGetProductFields', ['data' => $data]);

        if (is_array($response) && count($response) > 0) {
            foreach ($response as $fields) {
                if (is_array($fields)) {
                    $data = array_merge($data, $fields);
                }
            }
        }

        return $data;
    }

    /**
     * Получить ключи опций товара
     *
     * Делегирует вызов к msProductOption для получения списка ключей всех опций
     *
     * @param msProductData $productData
     * @return array
     */
    public function getOptionKeys(msProductData $productData): array
    {
        $productId = $productData->get('id');

        /** @var msProductOption $option */
        $option = $this->modx->newObject(msProductOption::class);
        $option->set('product_id', $productId);

        $result = $option->getOptionKeys($productId);

        return is_array($result) ? $result : [];
    }

    /**
     * Получить поля опций товара
     *
     * Делегирует вызов к msProductOption для получения полей опций
     * с текущими значениями и ExtJS метаданными
     *
     * @param msProductData $productData
     * @param array $keys Фильтр по ключам опций
     * @return array
     */
    public function getOptionFields(msProductData $productData, array $keys = []): array
    {
        /** @var msProductOption $option */
        $option = $this->modx->newObject(msProductOption::class);
        $option->set('product_id', $productData->get('id'));
        $result = $option->getOptionFields($productData->get('id'));
        return is_array($result) ? $result : [];
    }

    /**
     * Получить данные товара по ID
     *
     * Загружает msProduct и msProductData, объединяет их поля в один массив
     * Используется в API контроллерах для получения полных данных товара
     *
     * @param int $productId ID товара
     * @return array|null Массив данных или null если не найдено
     */
    public function getProductData(int $productId): ?array
    {
        // Загружаем товар
        /** @var msProduct $product */
        $product = $this->modx->getObject(msProduct::class, $productId);

        if (!$product) {
            return null;
        }

        // Загружаем данные товара (msProductData)
        $productData = $product->loadData();

        if (!$productData) {
            return null;
        }

        // Собираем все поля товара (объединяем msProduct и msProductData)
        $data = array_merge(
            $product->toArray(),
            $productData->toArray()
        );

        return $data;
    }

    /**
     * Обновить данные товара
     *
     * Загружает товар по ID, обновляет поля msProductData и сохраняет
     * Используется в API контроллерах для обновления данных товара
     *
     * @param int $productId ID товара
     * @param array $data Данные для обновления
     * @return array|null Обновленные данные или null при ошибке
     */
    public function updateProductData(int $productId, array $data): ?array
    {
        // Загружаем товар
        /** @var msProduct $product */
        $product = $this->modx->getObject(msProduct::class, $productId);

        if (!$product) {
            return null;
        }

        // Загружаем данные товара
        /** @var msProductData $productData */
        $productData = $product->loadData();

        if (!$productData) {
            return null;
        }

        // Обновляем поля
        $productData->fromArray($data);

        // Сохраняем
        if ($productData->save()) {
            return $productData->toArray();
        }

        return null;
    }
}
