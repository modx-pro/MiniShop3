<?php

namespace MiniShop3\Services\Product;

use MiniShop3\Model\msProductData;
use MiniShop3\Model\msProductFile;
use MODX\Revolution\modMediaSource;
use MODX\Revolution\modX;

/**
 * Сервис для работы с изображениями товара
 *
 * Отвечает за генерацию превью, управление медиа-источниками,
 * ранжирование изображений и установку главного изображения
 */
class ProductImageService
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
     * Генерация всех превью для всех изображений товара
     *
     * Проходит по всем файлам товара и генерирует thumbnail'ы
     * согласно настройкам медиа-источника
     *
     * @param msProductData $productData
     * @return void
     */
    public function generateAllThumbnails(msProductData $productData): void
    {
        $productId = $productData->get('id');
        $contextKey = $productData->Product->get('context_key');

        if (!$source = $this->initializeMediaSource($productData, $contextKey)) {
            return;
        }

        $files = $this->modx->getIterator(msProductFile::class, ['product_id' => $productId]);

        /** @var msProductFile $file */
        foreach ($files as $file) {
            $file->generateThumbnails($source);
        }
    }

    /**
     * Инициализация медиа-источника для товара
     *
     * Находит и инициализирует источник изображений товара,
     * может быть как стандартный файловый источник, так и кастомный
     *
     * @param msProductData $productData
     * @param string $contextKey Ключ контекста (web, mgr и т.д.)
     * @return bool|modMediaSource|null
     */
    public function initializeMediaSource(msProductData $productData, string $contextKey = 'web')
    {
        $productId = $productData->get('id');

        // Получаем source_id из товара или из настроек по умолчанию
        $sourceId = (int)$productData->get('source_id');
        if (!$sourceId) {
            $sourceId = (int)$this->modx->getOption('ms3_product_source_default', null, 1);
        }

        // MODX имеет встроенный метод для получения источников
        /** @var modMediaSource $source */
        $source = $this->modx->getObject('sources.modMediaSource', $sourceId);

        if (!$source) {
            return false;
        }

        // Инициализируем источник (НЕ перезаписываем basePath/baseUrl - используем настройки источника)
        if (!$source->initialize($contextKey)) {
            return false;
        }

        // Создаем основную директорию товара
        $source->createContainer($productId . '/', '/');

        return $source;
    }

    /**
     * Ранжирование изображений товара
     *
     * Устанавливает позиции (rank) для изображений согласно массиву file_id
     * Используется при перетаскивании изображений в галерее
     *
     * @param msProductData $productData
     * @param array $ranks Массив вида [file_id => position]
     * @return bool
     */
    public function rankProductImages(msProductData $productData, array $ranks): bool
    {
        if (empty($ranks)) {
            return false;
        }

        $productId = $productData->get('id');

        foreach ($ranks as $fileId => $position) {
            /** @var msProductFile $file */
            if ($file = $this->modx->getObject(msProductFile::class, [
                'id' => $fileId,
                'product_id' => $productId
            ])) {
                $file->set('rank', $position);
                $file->save();
            }
        }

        return true;
    }

    /**
     * Обновление главного изображения товара
     *
     * Находит первое изображение товара (с наименьшим rank) и устанавливает его
     * как главное (поля image и thumb в msProductData)
     *
     * @param msProductData $productData
     * @return bool|mixed
     */
    public function updateProductImage(msProductData $productData)
    {
        $productId = $productData->get('id');

        // Ищем первое изображение товара
        /** @var msProductFile $file */
        $file = $this->modx->getObject(msProductFile::class, [
            'product_id' => $productId,
            'parent_id' => 0,
            'type' => 'image'
        ], ['sortby' => 'rank']);

        if ($file) {
            $thumb = $file->get('thumbnail') ?: $file->get('url');
            $productData->set('image', $file->get('url'));
            $productData->set('thumb', $thumb);

            return $productData->save();
        } else {
            // Если нет изображений - очищаем поля
            $productData->set('image', '');
            $productData->set('thumb', '');

            return $productData->save();
        }
    }

    /**
     * Удаление пустого каталога товара
     *
     * Поддерживает любые Media Sources (локальные файлы, S3, CDN, Cloudinary и т.д.)
     * Проверяет наличие файлов перед удалением
     *
     * @param msProductData $productData
     * @return bool true если каталог удалён, false если есть файлы или ошибка
     */
    public function removeProductCatalog(msProductData $productData): bool
    {
        $productId = $productData->get('id');

        // Проверяем есть ли файлы у товара
        $filesCount = $this->modx->getCount(\MiniShop3\Model\msProductFile::class, [
            'product_id' => $productId,
            'parent_id' => 0  // Только родительские файлы (не thumbnails)
        ]);

        if ($filesCount > 0) {
            $this->modx->log(
                \MODX\Revolution\modX::LOG_LEVEL_INFO,
                "[ProductImageService] Cannot remove catalog for product #{$productId} - has {$filesCount} files"
            );
            return false;
        }

        // Инициализируем Media Source
        $contextKey = $productData->Product->get('context_key');
        $source = $this->initializeMediaSource($productData, $contextKey);

        if (!$source) {
            $this->modx->log(
                \MODX\Revolution\modX::LOG_LEVEL_ERROR,
                "[ProductImageService] Cannot initialize media source for product #{$productId}"
            );
            return false;
        }

        // Удаляем каталог через Media Source API (работает с любым типом источника!)
        $containerPath = $productId . '/';
        $result = $source->removeContainer($containerPath, '/');

        if ($result) {
            $this->modx->log(
                \MODX\Revolution\modX::LOG_LEVEL_INFO,
                "[ProductImageService] Successfully removed catalog for product #{$productId}"
            );
        } else {
            $errors = $source->getErrors();
            $this->modx->log(
                \MODX\Revolution\modX::LOG_LEVEL_ERROR,
                "[ProductImageService] Failed to remove catalog for product #{$productId}: " . print_r($errors, true)
            );
        }

        return $result;
    }
}
