<?php

namespace MiniShop3\Services\Product;

use MiniShop3\Model\msProductData;
use MiniShop3\Model\msProductFile;
use MODX\Revolution\modMediaSource;
use MODX\Revolution\modX;

/**
 * Service for working with product images
 *
 * Handles thumbnail generation, media source management,
 * image ranking and setting the main image
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
     * Generate all thumbnails for all product images
     *
     * Iterates through all product files and generates thumbnails
     * according to media source settings
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
     * Initialize media source for product
     *
     * Finds and initializes the product image source,
     * can be either standard file source or custom
     *
     * @param msProductData $productData
     * @param string $contextKey Context key (web, mgr etc.)
     * @return bool|modMediaSource|null
     */
    public function initializeMediaSource(msProductData $productData, string $contextKey = 'web')
    {
        $productId = $productData->get('id');

        $sourceId = (int)$productData->get('source_id');
        if (!$sourceId) {
            $sourceId = (int)$this->modx->getOption('ms3_product_source_default', null, 1);
        }

        /** @var modMediaSource $source */
        $source = $this->modx->getObject('sources.modMediaSource', $sourceId);

        if (!$source) {
            return false;
        }

        if (!$source->initialize($contextKey)) {
            return false;
        }

        $source->createContainer($productId . '/', '/');

        return $source;
    }

    /**
     * Rank product images
     *
     * Sets positions (rank) for images according to file_id array
     * Used when dragging images in gallery
     *
     * @param msProductData $productData
     * @param array $ranks Array in format [file_id => position]
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
     * Update product main image
     *
     * Finds the first product image (with lowest rank) and sets it
     * as main (image and thumb fields in msProductData)
     *
     * @param msProductData $productData
     * @return bool|mixed
     */
    public function updateProductImage(msProductData $productData)
    {
        $productId = $productData->get('id');

        /** @var msProductFile $file */
        $file = $this->modx->getObject(msProductFile::class, [
            'product_id' => $productId,
            'parent_id' => 0,
            'type' => 'image'
        ], ['sortby' => 'rank']);

        if ($file) {
            // Get thumbnail from child record (generated thumbnail)
            /** @var msProductFile $thumbnailFile */
            $thumbnailFile = $this->modx->getObject(msProductFile::class, [
                'parent_id' => $file->get('id'),
                'type' => 'image',
            ]);
            $thumb = $thumbnailFile ? $thumbnailFile->get('url') : $file->get('url');

            $productData->set('image', $file->get('url'));
            $productData->set('thumb', $thumb);

            return $productData->save();
        } else {
            $productData->set('image', '');
            $productData->set('thumb', '');

            return $productData->save();
        }
    }

    /**
     * Remove empty product catalog
     *
     * Supports any Media Sources (local files, S3, CDN, Cloudinary etc.)
     * Checks for files before deletion
     *
     * @param msProductData $productData
     * @return bool true if catalog removed, false if has files or error
     */
    public function removeProductCatalog(msProductData $productData): bool
    {
        $productId = $productData->get('id');

        $filesCount = $this->modx->getCount(\MiniShop3\Model\msProductFile::class, [
            'product_id' => $productId,
            'parent_id' => 0
        ]);

        if ($filesCount > 0) {
            $this->modx->log(
                \MODX\Revolution\modX::LOG_LEVEL_INFO,
                "[ProductImageService] Cannot remove catalog for product #{$productId} - has {$filesCount} files"
            );
            return false;
        }

        $contextKey = $productData->Product->get('context_key');
        $source = $this->initializeMediaSource($productData, $contextKey);

        if (!$source) {
            $this->modx->log(
                \MODX\Revolution\modX::LOG_LEVEL_ERROR,
                "[ProductImageService] Cannot initialize media source for product #{$productId}"
            );
            return false;
        }

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
