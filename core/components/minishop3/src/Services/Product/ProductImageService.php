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
                $file->set('position', $position);
                $file->save();
            }
        }

        return true;
    }

    /**
     * Build natural-sort positions for gallery rows by name (fallback: file), then id.
     *
     * @param list<array{id: int, name: string, file: string}> $rows
     * @return array<int, int> file_id => position (0..n-1)
     */
    public static function buildNaturalSortRanks(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        usort($rows, static function (array $a, array $b): int {
            $cmp = strnatcmp(self::naturalSortKey($a), self::naturalSortKey($b));
            if ($cmp !== 0) {
                return $cmp;
            }

            $cmp = strnatcmp(
                self::foldNaturalSortString((string) ($a['file'] ?? '')),
                self::foldNaturalSortString((string) ($b['file'] ?? '')),
            );
            if ($cmp !== 0) {
                return $cmp;
            }

            return (int) ($a['id'] ?? 0) <=> (int) ($b['id'] ?? 0);
        });

        $ranks = [];
        foreach ($rows as $position => $row) {
            $ranks[(int) $row['id']] = $position;
        }

        return $ranks;
    }

    /**
     * Re-rank top-level gallery files by natural sort on name/file (#616).
     *
     * Includes all parent_id=0 rows (not only type=image), matching Upload position counting.
     *
     * @return bool|mixed save result from updateProductImage(), or true when gallery is empty
     */
    public function sortProductImagesByName(msProductData $productData): mixed
    {
        $productId = (int) $productData->get('id');

        $rows = [];

        /** @var msProductFile $file */
        foreach ($this->modx->getIterator(msProductFile::class, [
            'product_id' => $productId,
            'parent_id' => 0,
        ]) as $file) {
            $rows[] = [
                'id' => (int) $file->get('id'),
                'name' => (string) $file->get('name'),
                'file' => (string) $file->get('file'),
            ];
        }

        if ($rows === []) {
            return true;
        }

        $this->rankProductImages($productData, self::buildNaturalSortRanks($rows));

        return $this->updateProductImage($productData);
    }

    /**
     * Set which gallery file is the product preview without changing sort order (#130).
     *
     * @return bool|mixed save result from updateProductImage()
     */
    public function setProductPreview(msProductData $productData, int $fileId): mixed
    {
        $productId = (int) $productData->get('id');
        if (!$this->getMainGalleryFile($productId, $fileId)) {
            return false;
        }

        $productData->set('preview_file_id', $fileId);

        return $this->updateProductImage($productData);
    }

    /**
     * Effective preview gallery file id (explicit preview or first image by position).
     * Read-only: does not mutate product data (#130).
     */
    public function resolvePreviewFileId(msProductData $productData): int
    {
        $file = $this->findMainImageFile($productData);

        return $file ? (int) $file->get('id') : 0;
    }

    /**
     * Update product main image / thumb URLs from gallery.
     *
     * Uses preview_file_id when set and valid; otherwise the first image by position (#130).
     *
     * @param msProductData $productData
     * @return bool|mixed
     */
    public function updateProductImage(msProductData $productData)
    {
        $stalePreview = false;
        $file = $this->findMainImageFile($productData, $stalePreview);
        if ($stalePreview) {
            $productData->set('preview_file_id', null);
        }

        if ($file) {
            /** @var msProductFile|null $thumbnailFile */
            $thumbnailFile = $this->modx->getObject(msProductFile::class, [
                'parent_id' => $file->get('id'),
                'type' => 'image',
            ]);
            $thumb = $thumbnailFile ? $thumbnailFile->get('url') : $file->get('url');

            $productData->set('image', $file->get('url'));
            $productData->set('thumb', $thumb);

            return $productData->save();
        }

        $productData->set('preview_file_id', null);
        $productData->set('image', '');
        $productData->set('thumb', '');

        return $productData->save();
    }

    /**
     * @param bool $stalePreview set true when preview_file_id pointed at a missing file
     */
    private function findMainImageFile(msProductData $productData, bool &$stalePreview = false): ?msProductFile
    {
        $stalePreview = false;
        $productId = (int) $productData->get('id');
        $previewId = (int) $productData->get('preview_file_id');

        if ($previewId > 0) {
            $preview = $this->getMainGalleryFile($productId, $previewId);
            if ($preview) {
                return $preview;
            }
            $stalePreview = true;
        }

        return $this->getMainGalleryFile($productId);
    }

    private function getMainGalleryFile(int $productId, ?int $fileId = null): ?msProductFile
    {
        if ($fileId !== null && $fileId > 0) {
            /** @var msProductFile|null $file */
            $file = $this->modx->getObject(msProductFile::class, [
                'id' => $fileId,
                'product_id' => $productId,
                'parent_id' => 0,
                'type' => 'image',
            ]);

            return $file ?: null;
        }

        $c = $this->modx->newQuery(msProductFile::class);
        $c->where([
            'product_id' => $productId,
            'parent_id' => 0,
            'type' => 'image',
        ]);
        $c->sortby('position', 'ASC');
        $c->sortby('id', 'ASC');

        /** @var msProductFile|null $file */
        $file = $this->modx->getObject(msProductFile::class, $c);

        return $file ?: null;
    }

    /**
     * @param array{name?: string, file?: string} $row
     */
    private static function naturalSortKey(array $row): string
    {
        $name = trim((string) ($row['name'] ?? ''));
        $key = $name !== '' ? $name : (string) ($row['file'] ?? '');

        return self::foldNaturalSortString($key);
    }

    /**
     * Case-fold for natural sort. strnatcasecmp is C-locale and skips multibyte
     * letters (Cyrillic), so UTF-8 names need mb_strtolower first (#616 review).
     */
    private static function foldNaturalSortString(string $value): string
    {
        return mb_strtolower($value, 'UTF-8');
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
