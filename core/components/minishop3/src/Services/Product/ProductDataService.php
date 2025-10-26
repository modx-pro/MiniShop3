<?php

namespace MiniShop3\Services\Product;

use MiniShop3\Model\msCategoryMember;
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
     * Обработка сохранения данных товара
     *
     * Подготавливает объект, сохраняет основные данные,
     * затем сохраняет связанные сущности (категории, опции, связи)
     *
     * @param msProductData $productData
     * @param bool|null $cacheFlag
     * @return bool
     */
    public function handleSave(msProductData $productData, ?bool $cacheFlag = null): bool
    {
        $this->prepareObject($productData);
        $save = $productData->xpdo->call(msProductData::class . '::parent::save', [$productData, $cacheFlag]);

        if ($save) {
            $this->saveCategories($productData);
            $this->saveOptions($productData);
            $this->saveLinks($productData);
        }

        return $save;
    }

    /**
     * Подготовка объекта перед сохранением
     *
     * Обрабатывает поля weight и price - если они пустые,
     * устанавливает значение 0 вместо пустой строки
     *
     * @param msProductData $productData
     * @return void
     */
    public function prepareObject(msProductData $productData): void
    {
        if ($productData->get('weight') === '') {
            $productData->set('weight', 0);
        }
        if ($productData->get('price') === '') {
            $productData->set('price', 0);
        }
    }

    /**
     * Сохранение дополнительных категорий товара
     *
     * Синхронизирует таблицу msCategoryMember с массивом категорий из поля 'categories'
     * Удаляет старые связи и создает новые
     *
     * @param msProductData $productData
     * @return void
     */
    public function saveCategories(msProductData $productData): void
    {
        $productId = $productData->get('id');
        $categories = $productData->get('categories');

        if (!is_array($categories)) {
            $categories = $categories
                ? array_map('trim', explode(',', $categories))
                : [];
        }

        // Удаляем все старые связи
        $this->modx->removeCollection(msCategoryMember::class, ['product_id' => $productId]);

        // Создаем новые связи
        foreach ($categories as $category) {
            if (!empty($category)) {
                /** @var msCategoryMember $member */
                $member = $this->modx->newObject(msCategoryMember::class);
                $member->set('product_id', $productId);
                $member->set('category_id', $category);
                $member->save();
            }
        }
    }

    /**
     * Сохранение опций товара
     *
     * Синхронизирует данные из JSON полей с таблицей msProductOption
     * через статический метод msProductOption::saveOptions()
     *
     * @param msProductData $productData
     * @return void
     */
    public function saveOptions(msProductData $productData): void
    {
        $productId = $productData->get('id');
        $options = [];

        // Собираем все JSON поля в массив опций
        foreach ($productData->_fieldMeta as $key => $value) {
            if ($value['phptype'] === 'json' && !empty($productData->get($key))) {
                $options = array_merge($options, $productData->get($key));
            }
        }

        // Синхронизируем с таблицей опций
        $this->modx->call(msProductOption::class, 'saveOptions', [
            $this->modx,
            $productId,
            $options
        ]);
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
        return $this->modx->call(msProductOption::class, 'getKeys', [
            $this->modx,
            $productData->get('id')
        ]);
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
        return $this->modx->call(msProductOption::class, 'getFields', [
            $this->modx,
            $productData->get('id'),
            $keys
        ]);
    }
}
