<?php

namespace MiniShop3\Services;

use MODX\Revolution\modX;
use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductData;

/**
 * Сервис для работы с данными товара (msProductData)
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
     * Получить данные товара по ID
     *
     * @param int $productId ID товара
     * @return array|null Массив данных или null если не найдено
     */
    public function getProductData(int $productId): ?array
    {
        // Загружаем товар
        /** @var msProduct $product */
        $product = $this->modx->getObject('MiniShop3\\Model\\msProduct', $productId);

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
     * @param int $productId ID товара
     * @param array $data Данные для обновления
     * @return array|null Обновленные данные или null при ошибке
     */
    public function updateProductData(int $productId, array $data): ?array
    {
        // Загружаем товар
        /** @var msProduct $product */
        $product = $this->modx->getObject('MiniShop3\\Model\\msProduct', $productId);

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
