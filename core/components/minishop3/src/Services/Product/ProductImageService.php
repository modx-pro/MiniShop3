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

        /** @var modMediaSource $source */
        if (!$source = $this->modx->getObject(modMediaSource::class, [
            'class_key' => 'MODX\\Revolution\\Sources\\modFileMediaSource'
        ])) {
            return false;
        }

        $properties = $source->getProperties();
        $properties['basePath']['value'] = $this->modx->getOption('ms3.gallery.files_path', null, '');
        $properties['baseUrl']['value'] = $this->modx->getOption('ms3.gallery.files_url', null, '');

        $source->setProperties($properties);

        if (!$source->initialize($contextKey)) {
            return false;
        }

        $paths = [
            $productId . '/',
            $productId . '/source/',
            $productId . '/thumb/',
        ];

        // Создаем необходимые каталоги
        foreach ($paths as $path) {
            $source->createContainer($path, '/');
        }

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
            'parent' => 0,
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
}
