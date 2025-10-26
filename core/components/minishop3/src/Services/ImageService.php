<?php

namespace MiniShop3\Services;

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use MODX\Revolution\modX;
use MODX\Revolution\Sources\modMediaSource;

/**
 * Сервис для работы с изображениями
 *
 * Заменяет устаревший phpThumb на современный Intervention Image v3
 * Поддерживает:
 * - WebP, AVIF форматы
 * - Работу с любыми MODX Media Sources (локальные, S3, CDN)
 * - Оптимизированную генерацию превью
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

        // Автовыбор драйвера (Imagick предпочтительнее для WebP/AVIF)
        $driver = extension_loaded('imagick') ? new ImagickDriver() : new GdDriver();
        $this->imageManager = new ImageManager($driver);

        $driverName = $driver instanceof ImagickDriver ? 'Imagick' : 'GD';
        $this->modx->log(
            modX::LOG_LEVEL_INFO,
            "[ImageService] Initialized with {$driverName} driver"
        );
    }

    /**
     * Генерация превью изображения
     *
     * Основной метод для замены msProductFile::makeThumbnail()
     * Работает с любыми MODX Media Sources через бинарные данные
     *
     * @param array $sourceInfo Данные из $mediaSource->getObjectContents()
     *                          Обязательные ключи: ['content' => binary_data]
     * @param array $options Параметры генерации:
     *                       - 'w' (int): ширина в пикселях
     *                       - 'h' (int): высота в пикселях
     *                       - 'q' (int): качество 1-100 (по умолчанию 90)
     *                       - 'f' или 'fm' (string): формат (jpg, png, webp, avif)
     *                       - 'zc' или 'fit' (string): режим ресайза (crop, contain, max)
     *
     * @return string|null Бинарные данные превью или null при ошибке
     *
     * @example
     * ```php
     * $info = $mediaSource->getObjectContents('products/1/photo.jpg');
     * $thumbnail = $imageService->makeThumbnail($info, [
     *     'w' => 300,
     *     'h' => 200,
     *     'q' => 85,
     *     'f' => 'webp',
     *     'zc' => 'T'
     * ]);
     * ```
     */
    public function makeThumbnail(array $sourceInfo, array $options): ?string
    {
        try {
            // Проверяем наличие бинарных данных
            if (empty($sourceInfo['content'])) {
                throw new \InvalidArgumentException('Source info must contain "content" key with binary data');
            }

            // Читаем изображение из бинарных данных
            $image = $this->imageManager->read($sourceInfo['content']);

            // Извлекаем параметры
            $width = isset($options['w']) ? (int) $options['w'] : null;
            $height = isset($options['h']) ? (int) $options['h'] : null;
            $quality = isset($options['q']) ? (int) $options['q'] : 90;
            $format = $options['f'] ?? $options['fm'] ?? 'jpg';
            $fit = $options['fit'] ?? $options['zc'] ?? 'crop';

            // Применяем трансформации
            if ($width || $height) {
                $this->applyTransformations($image, $width, $height, $fit);
            }

            // Получаем энкодер для нужного формата
            $encoder = $this->getEncoder($format, $quality);

            // Возвращаем бинарные данные
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
     * Применение трансформаций к изображению
     *
     * Поддерживает различные режимы ресайза для совместимости с phpThumb
     *
     * @param \Intervention\Image\Interfaces\ImageInterface $image
     * @param int|null $width
     * @param int|null $height
     * @param string $fit Режим ресайза
     */
    private function applyTransformations($image, ?int $width, ?int $height, string $fit): void
    {
        // Различные режимы ресайза
        switch ($fit) {
            // Crop (обрезка с заполнением)
            case 'crop':
            case 'C':
            case 'T': // phpThumb: zc=T (top crop)
                $image->cover($width, $height);
                break;

            // Contain (вписывание с сохранением пропорций)
            case 'contain':
            case 'scale':
            case '1': // phpThumb: zc=1
                $image->scale($width, $height);
                break;

            // Max (уменьшение если больше, не увеличивает)
            case 'max':
            case '2': // phpThumb: zc=2
                $image->scaleDown($width, $height);
                break;

            // Stretch (растяжение без сохранения пропорций)
            case 'stretch':
            case '3': // phpThumb: zc=3
                $image->resize($width, $height);
                break;

            // По умолчанию - crop
            default:
                $image->cover($width, $height);
        }
    }

    /**
     * Получение энкодера для нужного формата
     *
     * Поддерживаемые форматы: JPEG, PNG, WebP, AVIF, GIF
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
     * Сохранение превью в Media Source
     *
     * Универсальный метод для сохранения превью в любой тип Media Source
     * (локальные файлы, S3, Cloudinary, Azure и т.д.)
     *
     * @param string $thumbnailData Бинарные данные превью
     * @param string $path Путь для сохранения (например: "products/1/120x90/")
     * @param string $filename Имя файла (например: "photo.webp")
     * @param modMediaSource $mediaSource Источник для сохранения
     *
     * @return string|false URL сохраненного превью или false при ошибке
     */
    public function saveThumbnailToSource(
        string $thumbnailData,
        string $path,
        string $filename,
        modMediaSource $mediaSource
    ) {
        try {
            // Создаем контейнер (папку) если нужно
            $mediaSource->createContainer($path, '/');

            // Очищаем ошибки перед операцией
            $mediaSource->errors = [];

            // Сохраняем файл в Media Source
            $success = $mediaSource->createObject($path, $filename, $thumbnailData);

            if (!$success) {
                $errors = $mediaSource->getErrors();
                $this->modx->log(
                    modX::LOG_LEVEL_ERROR,
                    "[ImageService] Failed to save thumbnail to Media Source: " . print_r($errors, true)
                );
                return false;
            }

            // Получаем URL (может быть CDN URL для S3/Cloudinary!)
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
     * Получить информацию о драйвере
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