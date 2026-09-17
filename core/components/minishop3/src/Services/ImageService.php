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

    /** Optional site root override (tests); production uses MODX_BASE_PATH. */
    private ?string $basePath;

    /**
     * ImageService constructor.
     *
     * @param modX $modx
     * @param string|null $basePath Optional filesystem root for resolving watermark paths
     */
    public function __construct(modX $modx, ?string $basePath = null)
    {
        $this->modx = $modx;
        $this->basePath = $basePath;

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
     *                       - 'watermark' (array): optional overlay from Media Source thumbnails JSON
     *                         (enabled, path, position, offset_x, offset_y, opacity).
     *                         position: Intervention names or `tile` (mosaic; offsets = margins).
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

            $this->applyWatermark($image, $options);

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
     * Overlay watermark from thumbnails JSON (docs gallery Watermarks section).
     * Missing/invalid file is logged; thumbnail generation continues without overlay.
     *
     * @param \Intervention\Image\Interfaces\ImageInterface $image
     * @param array<string, mixed> $options
     */
    private function applyWatermark($image, array $options): void
    {
        $config = $options['watermark'] ?? null;
        if (!is_array($config) || empty($config['enabled'])) {
            return;
        }

        $path = trim((string) ($config['path'] ?? ''));
        if ($path === '') {
            $this->modx->log(modX::LOG_LEVEL_ERROR, '[ImageService] Watermark enabled but path is empty');

            return;
        }

        $resolved = $this->resolveWatermarkPath($path);
        if ($resolved === null) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                "[ImageService] Watermark file not found or outside site base path: {$path}"
            );

            return;
        }

        try {
            $rawPosition = trim((string) ($config['position'] ?? 'bottom-right'));
            $position = $this->resolveWatermarkPosition($rawPosition);
            if ($position === null) {
                $this->modx->log(
                    modX::LOG_LEVEL_ERROR,
                    "[ImageService] Unknown watermark position \"{$rawPosition}\";"
                    . ' use Intervention names (top-left…bottom-right) or tile'
                );

                return;
            }

            $offsetX = (int) ($config['offset_x'] ?? 0);
            $offsetY = (int) ($config['offset_y'] ?? 0);
            $opacity = $this->normalizeWatermarkOpacity($config['opacity'] ?? 100);
            $mark = $this->prepareWatermarkOverlay($resolved, $opacity);

            if ($position === 'tile') {
                $this->placeTiledWatermark($image, $mark, $offsetX, $offsetY);

                return;
            }

            // Opacity is baked into the mark alpha so GD uses imagecopy() (not imagecopymerge).
            $image->place($mark, $position, $offsetX, $offsetY, 100);
        } catch (\Exception $e) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                "[ImageService] Failed to apply watermark: {$e->getMessage()}"
            );
        }
    }

    /**
     * Accept Intervention place() positions plus `tile` for mosaic.
     * Returns null when the value is unknown (caller logs and skips overlay).
     */
    private function resolveWatermarkPosition(string $position): ?string
    {
        $key = strtolower(trim($position));
        if ($key === '') {
            return 'bottom-right';
        }

        /** @var array<string, string> $map */
        static $map = [
            'top-left' => 'top-left',
            'top' => 'top',
            'top-right' => 'top-right',
            'left' => 'left',
            'center' => 'center',
            'right' => 'right',
            'bottom-left' => 'bottom-left',
            'bottom' => 'bottom',
            'bottom-right' => 'bottom-right',
            'tile' => 'tile',
        ];

        return $map[$key] ?? null;
    }

    /**
     * Decode the watermark and bake opacity into its alpha channel.
     *
     * Intervention GD place() with opacity < 100 uses imagecopymerge(), which ignores
     * source alpha and paints opaque gray boxes on transparent canvases. Baking opacity
     * and placing at 100 uses imagecopy() with correct alpha. The same Image is safe to
     * reuse for tiling (Imagick PlaceModifier no longer mutates alpha per call).
     *
     * @return \Intervention\Image\Interfaces\ImageInterface
     */
    private function prepareWatermarkOverlay(string $resolvedPath, int $opacity)
    {
        $mark = $this->imageManager->read($resolvedPath);
        if ($opacity < 100) {
            $this->bakeWatermarkOpacity($mark, $opacity);
        }

        return $mark;
    }

    /**
     * @param \Intervention\Image\Interfaces\ImageInterface $watermark
     */
    private function bakeWatermarkOpacity($watermark, int $opacity): void
    {
        $native = $watermark->core()->native();
        if ($native instanceof \Imagick) {
            $native->setImageAlphaChannel(\Imagick::ALPHACHANNEL_SET);
            $native->evaluateImage(
                \Imagick::EVALUATE_DIVIDE,
                $opacity > 0 ? 100 / $opacity : 1000,
                \Imagick::CHANNEL_ALPHA
            );

            return;
        }

        if (!($native instanceof \GdImage)) {
            return;
        }

        imagealphablending($native, false);
        imagesavealpha($native, true);
        $factor = $opacity / 100.0;
        $width = imagesx($native);
        $height = imagesy($native);
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $color = imagecolorat($native, $x, $y);
                $alpha = ($color & 0x7F000000) >> 24;
                $red = ($color >> 16) & 0xFF;
                $green = ($color >> 8) & 0xFF;
                $blue = $color & 0xFF;
                $newAlpha = (int) round(127 - (127 - $alpha) * $factor);
                $allocated = imagecolorallocatealpha($native, $red, $green, $blue, $newAlpha);
                if ($allocated !== false) {
                    imagesetpixel($native, $x, $y, $allocated);
                }
            }
        }
    }

    /**
     * Tile the prepared watermark across the canvas (`position: tile`).
     * offset_x / offset_y are inter-tile margins. Mark opacity must already be baked.
     *
     * @param \Intervention\Image\Interfaces\ImageInterface $image
     * @param \Intervention\Image\Interfaces\ImageInterface $mark
     */
    private function placeTiledWatermark($image, $mark, int $marginX, int $marginY): void
    {
        $tileW = $mark->width();
        $tileH = $mark->height();
        if ($tileW <= 0 || $tileH <= 0) {
            return;
        }

        $stepX = $tileW + max(0, $marginX);
        $stepY = $tileH + max(0, $marginY);
        $canvasW = $image->width();
        $canvasH = $image->height();

        for ($y = 0; $y < $canvasH; $y += $stepY) {
            for ($x = 0; $x < $canvasW; $x += $stepX) {
                $image->place($mark, 'top-left', $x, $y, 100);
            }
        }
    }

    /**
     * Resolve watermark path relative to the site base path; reject traversal outside the root.
     * Paths like `/assets/watermark.png` are treated as site-relative when the FS absolute miss.
     */
    private function resolveWatermarkPath(string $path): ?string
    {
        $base = $this->siteBasePath();
        if ($base === '') {
            return $this->readableRealPath($path);
        }

        $baseReal = realpath($base);
        if ($baseReal === false) {
            return null;
        }

        foreach ($this->watermarkPathCandidates($path, $baseReal) as $candidate) {
            $real = $this->readableRealPath($candidate);
            if ($real !== null && $this->isPathWithinBase($real, $baseReal)) {
                return $real;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function watermarkPathCandidates(string $path, string $baseReal): array
    {
        $relative = $baseReal . DIRECTORY_SEPARATOR . ltrim(
            str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path),
            '/\\'
        );

        if (!$this->isAbsoluteFilesystemPath($path)) {
            return [$relative];
        }

        // Absolute FS path, plus URL-style "/assets/..." fallback under the site root.
        return [$path, $relative];
    }

    private function normalizeWatermarkOpacity(mixed $opacity): int
    {
        if ($opacity === '' || $opacity === null) {
            return 100;
        }

        return max(0, min(100, (int) $opacity));
    }

    private function readableRealPath(string $path): ?string
    {
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        $real = realpath($path);

        return $real !== false ? $real : null;
    }

    private function isPathWithinBase(string $path, string $baseReal): bool
    {
        $basePrefix = rtrim(str_replace('\\', '/', $baseReal), '/') . '/';

        return str_starts_with(str_replace('\\', '/', $path), $basePrefix);
    }

    private function siteBasePath(): string
    {
        if ($this->basePath !== null && $this->basePath !== '') {
            return $this->basePath;
        }

        return defined('MODX_BASE_PATH') ? (string) MODX_BASE_PATH : '';
    }

    private function isAbsoluteFilesystemPath(string $path): bool
    {
        if ($path === '') {
            return false;
        }
        if ($path[0] === '/' || $path[0] === '\\') {
            return true;
        }

        return (bool) preg_match('#^[A-Za-z]:[\\\\/]#', $path);
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
     * @return \Intervention\Image\Interfaces\EncoderInterface
     */
    private function getEncoder(string $format, int $quality)
    {
        $format = strtolower($format);

        return match ($format) {
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
