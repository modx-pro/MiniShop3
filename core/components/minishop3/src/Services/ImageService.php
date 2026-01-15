<?php

namespace MiniShop3\Services;

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use MODX\Revolution\modX;
use MODX\Revolution\Sources\modMediaSource;

/**
 * Service for working with images
 *
 * Replaces deprecated phpThumb with modern Intervention Image v3
 * Supports:
 * - WebP, AVIF formats
 * - Working with any MODX Media Sources (local, S3, CDN)
 * - Optimized thumbnail generation
 *
 * @package MiniShop3\Services
 */
class ImageService
{
    private modX $modx;
    private ImageManager $imageManager;

    /**
     * ImageService constructor.
     *
     * @param modX $modx
     */
    public function __construct(modX $modx)
    {
        $this->modx = $modx;

        // Auto-select driver (Imagick is preferred for WebP/AVIF)
        $driver = extension_loaded('imagick') ? new ImagickDriver() : new GdDriver();
        $this->imageManager = new ImageManager($driver);

        $driverName = $driver instanceof ImagickDriver ? 'Imagick' : 'GD';
        $this->modx->log(
            modX::LOG_LEVEL_INFO,
            "[ImageService] Initialized with {$driverName} driver"
        );
    }

    /**
     * Generate image thumbnail
     *
     * Works with any MODX Media Sources via binary data
     *
     * @param array $sourceInfo Data from $mediaSource->getObjectContents()
     *                          Required keys: ['content' => binary_data]
     * @param array $options Generation parameters:
     *                       - 'width' (int): width in pixels
     *                       - 'height' (int): height in pixels
     *                       - 'quality' (int): quality 1-100 (default 90)
     *                       - 'format' (string): jpg, png, webp, avif (default jpg)
     *                       - 'mode' (string): resize mode - cover, contain, max, stretch (default cover)
     *
     * @return string|null Binary thumbnail data or null on error
     *
     * @example
     * ```php
     * $info = $mediaSource->getObjectContents('products/1/photo.jpg');
     * $thumbnail = $imageService->makeThumbnail($info, [
     *     'width' => 300,
     *     'height' => 200,
     *     'quality' => 85,
     *     'format' => 'webp',
     *     'mode' => 'cover'
     * ]);
     * ```
     */
    public function makeThumbnail(array $sourceInfo, array $options): ?string
    {
        try {
            // Check for binary data presence
            if (empty($sourceInfo['content'])) {
                throw new \InvalidArgumentException('Source info must contain "content" key with binary data');
            }

            // Read image from binary data
            $image = $this->imageManager->read($sourceInfo['content']);

            // Extract parameters
            $width = isset($options['width']) ? (int) $options['width'] : null;
            $height = isset($options['height']) ? (int) $options['height'] : null;
            $quality = (int) ($options['quality'] ?? 90);
            $format = $options['format'] ?? 'jpg';
            $mode = $options['mode'] ?? 'cover';

            $this->modx->log(
                modX::LOG_LEVEL_DEBUG,
                "[ImageService] Processing: {$width}x{$height}, quality={$quality}, format={$format}, mode={$mode}"
            );

            // Apply transformations
            if ($width || $height) {
                $this->applyResize($image, $width, $height, $mode);
            }

            // Get encoder for required format
            $encoder = $this->getEncoder($format, $quality);

            // Return binary data
            return $image->encode($encoder)->toString();

        } catch (\Exception $e) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                "[ImageService] Failed to generate thumbnail: {$e->getMessage()}"
            );
            return null;
        }
    }

    /**
     * Apply resize to image
     *
     * @param \Intervention\Image\Interfaces\ImageInterface $image
     * @param int|null $width
     * @param int|null $height
     * @param string $mode Resize mode: cover, contain, max, stretch
     */
    private function applyResize($image, ?int $width, ?int $height, string $mode): void
    {
        switch ($mode) {
            // Cover - crop to fill exact dimensions (default)
            case 'cover':
                $image->cover($width, $height);
                break;

            // Contain - fit within dimensions, preserve aspect ratio
            case 'contain':
                $image->scale($width, $height);
                break;

            // Max - shrink if larger, don't upscale
            case 'max':
                $image->scaleDown($width, $height);
                break;

            // Stretch - resize to exact dimensions, ignore aspect ratio
            case 'stretch':
                $image->resize($width, $height);
                break;

            default:
                $image->cover($width, $height);
        }
    }

    /**
     * Get encoder for required format
     *
     * Supported formats: JPEG, PNG, WebP, AVIF, GIF
     *
     * @param string $format
     * @param int $quality
     * @return \Intervention\Image\Encoders\EncoderInterface
     */
    private function getEncoder(string $format, int $quality)
    {
        $format = strtolower($format);

        return match($format) {
            'webp' => new \Intervention\Image\Encoders\WebpEncoder($quality),
            'avif' => new \Intervention\Image\Encoders\AvifEncoder($quality),
            'png' => new \Intervention\Image\Encoders\PngEncoder(),
            'gif' => new \Intervention\Image\Encoders\GifEncoder(),
            'jpg', 'jpeg' => new \Intervention\Image\Encoders\JpegEncoder($quality),
            default => new \Intervention\Image\Encoders\JpegEncoder($quality),
        };
    }

    /**
     * Save thumbnail to Media Source
     *
     * Universal method for saving thumbnails to any Media Source type
     * (local files, S3, Cloudinary, Azure, etc.)
     *
     * @param string $thumbnailData Binary thumbnail data
     * @param string $path Path for saving (e.g.: "products/1/120x90/")
     * @param string $filename File name (e.g.: "photo.webp")
     * @param modMediaSource $mediaSource Source for saving
     *
     * @return string|false URL of saved thumbnail or false on error
     */
    public function saveThumbnailToSource(
        string $thumbnailData,
        string $path,
        string $filename,
        modMediaSource $mediaSource
    ) {
        try {
            // Create container (folder) if needed
            $mediaSource->createContainer($path, '/');

            // Clear errors before operation
            $mediaSource->errors = [];

            // Save file to Media Source
            $success = $mediaSource->createObject($path, $filename, $thumbnailData);

            if (!$success) {
                $errors = $mediaSource->getErrors();
                $this->modx->log(
                    modX::LOG_LEVEL_ERROR,
                    "[ImageService] Failed to save thumbnail to Media Source: " . print_r($errors, true)
                );
                return false;
            }

            // Get URL (can be CDN URL for S3/Cloudinary!)
            $url = $mediaSource->getObjectUrl($path . $filename);

            $this->modx->log(
                modX::LOG_LEVEL_INFO,
                "[ImageService] Thumbnail saved successfully: {$url}"
            );

            return $url;

        } catch (\Exception $e) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                "[ImageService] Exception while saving thumbnail: {$e->getMessage()}"
            );
            return false;
        }
    }

    /**
     * Get driver information
     *
     * @return array
     */
    public function getDriverInfo(): array
    {
        $driver = $this->imageManager->driver();

        return [
            'name' => $driver instanceof ImagickDriver ? 'Imagick' : 'GD',
            'supports_webp' => extension_loaded('imagick') || function_exists('imagewebp'),
            'supports_avif' => extension_loaded('imagick'), // AVIF requires Imagick
        ];
    }
}