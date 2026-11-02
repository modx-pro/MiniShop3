<?php

namespace MiniShop3\Processors\Gallery;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductFile;
use MiniShop3\Services\Product\RemoteImageFetcher;
use MiniShop3\Utils\PathBoundary;
use MODX\Revolution\modX;
use MODX\Revolution\Processors\ModelProcessor;
use MODX\Revolution\Sources\modFileMediaSource;
use MODX\Revolution\Sources\modMediaSource;

class Upload extends ModelProcessor
{
    /**
     * Потолок пикселей против гигапиксельных файлов (#923).
     *
     * Отсекает именно бомбы — крошечный файл, заявляющий десятки тысяч точек
     * по каждой стороне. От нехватки памяти в общем случае он не спасает:
     * 80 Мп это уже около 320 МБ в GD, и при memory_limit = 256M запрос упадёт
     * на изображении, которое проверку прошло. Так было и до правки, и лечится
     * это не потолком, а памятью и ограничением размеров на входе.
     *
     * Это не редакторское ограничение размеров, а предохранитель. Настройки
     * maxUploadWidth и maxUploadHeight медиа-источника здесь намеренно
     * не применяются: сегодня они не действуют ни на сервере, ни в загрузчике,
     * и включить их значит начать молча отклонять фотографии, которые
     * загружались вчера. Конвейер превьюшек для больших оригиналов и сделан —
     * `large` уменьшает до 1200 по длинной стороне.
     */
    private const MAX_IMAGE_PIXELS = 80000000;

    /** Если maxUploadSize у источника не задан. */
    private const DEFAULT_MAX_UPLOAD_SIZE = 10485760;

    public $classKey = msProductFile::class;
    public $languageTopics = ['minishop3:default', 'minishop3:product'];
    public $permission = 'msproductfile_save';
    /** @var modMediaSource $mediaSource */
    public $mediaSource;
    /** @var MiniShop3 $ms3 */
    protected $ms3;
    /** @var msProduct|null $product */
    private $product = null;

    /** Причина отказа из handleFile(), чтобы наружу ушло не общее сообщение. */
    private ?string $failureMessage = null;

    /**
     * @return bool|null|string
     */
    public function initialize()
    {
        $id = (int)$this->getProperty('id', @$_GET['id']);
        $this->product = $this->modx->getObject(msProduct::class, $id);
        if (!$this->product) {
            return $this->modx->lexicon('ms3_gallery_err_no_product');
        }
        if (!$this->mediaSource = $this->product->initializeMediaSource()) {
            return $this->modx->lexicon('ms3_gallery_err_no_source');
        }
        $this->ms3 = $this->modx->services->get('ms3');

        return true;
    }

    /**
     * @return array|string
     */
    public function process()
    {
        if (!$data = $this->handleFile()) {
            return $this->failure($this->failureMessage ?? $this->modx->lexicon('ms3_err_gallery_ns'));
        }

        $properties = $this->mediaSource->getPropertyList();
        $pathinfo = $this->ms3->utils->pathinfo($data['name']);
        $extension = strtolower($pathinfo['extension']);
        $filename = strtolower($pathinfo['filename']);

        $image_extensions = $allowed_extensions = [];
        if (!empty($properties['imageExtensions'])) {
            $image_extensions = array_map('trim', explode(',', strtolower($properties['imageExtensions'])));
        }
        if (!empty($properties['allowedFileTypes'])) {
            $allowed_extensions = array_map('trim', explode(',', strtolower($properties['allowedFileTypes'])));
        }
        if (!empty($allowed_extensions) && !in_array($extension, $allowed_extensions)) {
            @unlink($data['tmp_name']);

            return $this->failure($this->modx->lexicon('ms3_err_gallery_ext'));
        } else {
            if (in_array($extension, $image_extensions)) {
                if (empty($data['properties']['height']) || empty($data['properties']['width'])) {
                    @unlink($data['tmp_name']);

                    return $this->failure($this->modx->lexicon('ms3_err_wrong_image'));
                }
                $type = 'image';
            } else {
                $type = $extension;
            }
        }

        // Duplicate check
        $count = $this->modx->getCount($this->classKey, [
            'product_id' => $this->product->get('id'),
            'hash' => $data['hash'],
            'parent_id' => 0,
        ]);
        if ($count) {
            @unlink($data['tmp_name']);

            return $this->failure($this->modx->lexicon('ms3_err_gallery_exists'));
        }

        $filename = !empty($properties['imageNameType']) && $properties['imageNameType'] == 'friendly'
            ? $this->product->cleanAlias($filename)
            : $data['hash'];
        $filename = str_replace(',', '', $filename) . '.' . $extension;
        $tmp_filename = $filename;
        $i = 1;
        while (true) {
            $count = $this->modx->getCount($this->classKey, [
                'product_id' => $this->product->id,
                'file' => $tmp_filename,
                'parent_id' => 0,
            ]);
            if (!$count) {
                $filename = $tmp_filename;
                break;
            } else {
                $pcre = '#(-' . ($i - 1) . '|)\.' . $extension . '$#';
                $tmp_filename = preg_replace($pcre, "-$i.$extension", $tmp_filename);
                $i++;
            }
        }

        $position = isset($properties['imageUploadDir']) && empty($properties['imageUploadDir'])
            ? 0
            : $this->modx->getCount($this->classKey, [
                'parent_id' => 0, 'product_id' => $this->product->get('id')
            ]);

        /** @var msProductFile $uploaded_file */
        $uploaded_file = $this->modx->newObject($this->classKey, [
            'product_id' => $this->product->get('id'),
            'parent_id' => 0,
            'name' => preg_replace('#\.' . $extension . '$#i', '', $data['name']),
            'file' => $filename,
            'path' => $this->product->get('id') . '/',
            'source_id' => $this->mediaSource->get('id'),
            'type' => $type,
            'position' => $position,
            'createdon' => date('Y-m-d H:i:s'),
            'createdby' => $this->modx->user->get('id'),
            'hash' => $data['hash'],
            'properties' => $data['properties'],
            'description' => $this->getProperty('description'),
        ]);

        $this->mediaSource->createContainer($uploaded_file->get('path'), '/');
        $this->mediaSource->errors = [];
        if ($this->mediaSource instanceof modFileMediaSource) {
            $upload = $this->mediaSource->createObject($uploaded_file->get('path'), $uploaded_file->get('file'), file_get_contents($data['tmp_name']));
        } else {
            $data['name'] = $filename;
            $upload = $this->mediaSource->uploadObjectsToContainer($uploaded_file->get('path'), [$data]);
        }
        @unlink($data['tmp_name']);

        if ($upload) {
            $url = $this->mediaSource->getObjectUrl($uploaded_file->get('path') . $uploaded_file->get('file'));
            $uploaded_file->set('url', $url);
            $uploaded_file->save();

            if (empty($position)) {
                $imagesTable = $this->modx->getTableName($this->classKey);
                $sql = "UPDATE {$imagesTable} SET `position` = `position` + 1 WHERE product_id ='" . $this->product->id . "' AND id !='" . $uploaded_file->get('id') . "'";
                $this->modx->exec($sql);
            }

            $generate = $uploaded_file->generateThumbnails($this->mediaSource);
            if ($generate !== true) {
                $this->modx->log(
                    modX::LOG_LEVEL_ERROR,
                    '[miniShop3] Could not generate thumbnails for image with id = ' . $uploaded_file->get('id') .
                    '. ' . $generate
                );
            }

            $productData = $this->product->getOne('Data');
            if ($productData) {
                /** @var \MiniShop3\Services\Product\ProductImageService $imageService */
                $imageService = $this->modx->services->get('ms3_product_image');
                if ($imageService) {
                    $imageService->updateProductImage($productData);
                }
            }

            return $this->success('', $uploaded_file);
        } else {
            return $this->failure($this->modx->lexicon('ms3_err_gallery_save') . ': ' .
                print_r($this->mediaSource->getErrors(), true));
        }
    }

    /**
     * @return array|bool
     */
    public function handleFile()
    {
        $tf = tempnam(self::basePath(), 'ms3_');
        $maxSize = $this->maxUploadSize();

        $url = trim((string) $this->getProperty('url', ''));

        if (!empty($_FILES['file']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
            $name = $_FILES['file']['name'];
            move_uploaded_file($_FILES['file']['tmp_name'], $tf);
        } elseif ($url !== '') {
            $name = $this->fetchByUrl($url, $tf, $maxSize);
            if ($name === null) {
                @unlink($tf);

                return false;
            }
        } else {
            // Параметр `file` остался строго локальным: его использует
            // CSV-импорт, и расширять его до ссылок нельзя — у них разный
            // уровень доверия. Ссылки идут через отдельный `url`.
            $source = $this->resolveLocalSource((string) $this->getProperty('file', ''));
            if ($source === null) {
                @unlink($tf);

                return false;
            }

            $name = basename($source);
            if (!$this->copyWithSizeLimit($source, $tf, $maxSize)) {
                @unlink($tf);

                return false;
            }
        }

        clearstatcache(true, $tf);

        // Проверка одна для обоих путей: multipart приходит мимо copyWithSizeLimit().
        if (file_exists($tf) && filesize($tf) > $maxSize) {
            @unlink($tf);
            $this->failureMessage = $this->modx->lexicon('ms3_err_gallery_too_large');

            return false;
        }

        if (file_exists($tf) && !empty($name) && $size = filesize($tf)) {
            /** @var msProductFile|null $o */
            $o = $this->modx->newObject($this->classKey);
            $hash = $o ? $o->generateHash($tf) : '';
            $data = [
                'name' => $name,
                'tmp_name' => $tf,
                'hash' => $hash,
                'properties' => [
                    'size' => $size,
                ],
            ];

            $tmp = getimagesize($tf);

            if (is_array($tmp)) {
                if (!$this->withinPixelCeiling((int) $tmp[0], (int) $tmp[1], $tf)) {
                    return false;
                }

                $data['properties'] = array_merge(
                    $data['properties'],
                    [
                        'width' => $tmp[0],
                        'height' => $tmp[1],
                        'bits' => $tmp['bits'],
                        'mime' => $tmp['mime'],
                    ]
                );
            }

            // Прежде здесь лежал запасной путь для webp: если getimagesize()
            // не справился, а в имени есть `.webp`, размеры брались через
            // Intervention или imagecreatefromwebp(). Он убран (#923).
            //
            // Во-первых, он бесполезен: getimagesize() разбирает webp с PHP 7.1,
            // а компонент требует 8.2+. Сюда попадал только файл, который
            // getimagesize() разобрать не смог.
            //
            // Во-вторых, он был опасен ровно для таких файлов: оба способа
            // раскодируют изображение целиком, то есть проверка числа пикселей
            // срабатывала уже после того, как память занята. Под именем .webp
            // можно было подсунуть формат, который getimagesize() не понимает,
            // а бэкенд Intervention понимает.
            //
            // Теперь такой файл остаётся без width/height, и process() отвечает
            // ms3_err_wrong_image — отказ вместо декодирования неизвестного.
            return $data;
        } else {
            unlink($tf);

            return false;
        }
    }

    /**
     * Загрузка по ссылке: имя файла либо null, причина уже записана.
     *
     * Возможность существовала и раньше, но как побочный эффект `fopen($url)`
     * без каких-либо проверок (#923). Теперь это отдельная фича с защитой от
     * SSRF в {@see RemoteImageFetcher} и выключателем в настройках.
     */
    private function fetchByUrl(string $url, string $target, int $maxSize): ?string
    {
        if (!$this->modx->getOption('ms3_gallery_allow_url_upload', null, true)) {
            $this->failureMessage = $this->modx->lexicon('ms3_err_gallery_url_disabled');

            return null;
        }

        $fetcher = $this->modx->services->has('ms3_remote_image_fetcher')
            ? $this->modx->services->get('ms3_remote_image_fetcher')
            : new RemoteImageFetcher($this->modx);

        $result = $fetcher->fetchTo($url, $target, $maxSize);

        if ($result['ok'] !== true) {
            $this->failureMessage = $this->modx->lexicon($result['reason']);

            return null;
        }

        return $result['name'];
    }

    /**
     * Корень сайта одним обращением к константе: PHPStan её не видит,
     * и в базлайне на этот файл заведена ровно одна запись.
     */
    private static function basePath(): string
    {
        return MODX_BASE_PATH;
    }

    /**
     * Предел размера файла из медиа-источника.
     *
     * Загрузчик в админке этот же предел уже соблюдает на клиенте, так что
     * серверная проверка ничего не меняет для обычной загрузки — она закрывает
     * прямой вызов процессора, минующий интерфейс (#923).
     */
    private function maxUploadSize(): int
    {
        $properties = $this->mediaSource->getPropertyList();
        $max = (int) ($properties['maxUploadSize'] ?? 0);

        return $max > 0 ? $max : self::DEFAULT_MAX_UPLOAD_SIZE;
    }

    /**
     * Локальный файл-источник для параметра `file`, либо null с причиной отказа.
     *
     * Загрузка по URL убрана совсем (#923). Раньше сюда уходил любой
     * пользовательский адрес: `fopen()` открывал и `http://` внутренней сети,
     * и обёртки вроде `php://filter`, то есть сервер делал запросы за
     * пользователя и читал локальные файлы. Внутри компонента такой вызов один
     * — CSV-импорт, и он передаёт локальный путь под base_path. Вернуть
     * загрузку по URL безопасно можно только с проверкой адреса после
     * разрешения DNS, запретом редиректов и закреплением IP; это отдельная
     * задача, а не побочная часть исправления.
     */
    private function resolveLocalSource(string $file): ?string
    {
        if ($file === '') {
            return null;
        }

        if (str_contains($file, '://')) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                "[Gallery/Upload] remote source rejected: {$file}"
            );
            $this->failureMessage = $this->modx->lexicon('ms3_err_gallery_remote_source');

            return null;
        }

        // Базовый путь через realpath: MODX_BASE_PATH на Windows приходит
        // со слэшами вперёд, а realpath() отдаёт обратные, и сравнение
        // «как есть» молча не совпало бы — то есть граница бы не работала.
        $realBase = realpath(self::basePath());
        $real = realpath($file);

        $usable = $real !== false
            && $realBase !== false
            && PathBoundary::isInside($real, $realBase)
            && is_file($real);

        if (!$usable) {
            // Наружу один ответ на все случаи. Разные сообщения для «файл есть,
            // но снаружи» и «файла нет» превращают процессор в детектор файлов
            // на всём диске: перебирая пути, можно узнать, что на сервере лежит.
            // Точную причину пишем в журнал — администратору этого достаточно,
            // а вызывающему знать её незачем.
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                sprintf(
                    '[Gallery/Upload] source rejected (%s): %s',
                    $real === false ? 'does not resolve' : (is_file($real) ? 'outside base path' : 'not a file'),
                    $file
                )
            );
            $this->failureMessage = $this->modx->lexicon('ms3_err_gallery_source_not_found');

            return null;
        }

        return $real;
    }

    /**
     * Копирование с обрывом по превышении предела.
     *
     * Раньше поток лился до EOF без ограничения, и достаточно большой источник
     * заполнял диск ещё до любых проверок (#923).
     */
    private function copyWithSizeLimit(string $source, string $target, int $maxSize): bool
    {
        $size = filesize($source);
        if ($size !== false && $size > $maxSize) {
            $this->failureMessage = $this->modx->lexicon('ms3_err_gallery_too_large');

            return false;
        }

        $in = fopen($source, 'rb');
        if (!$in) {
            $this->failureMessage = $this->modx->lexicon('ms3_err_gallery_source_not_found');

            return false;
        }

        $out = fopen($target, 'wb');
        if (!$out) {
            fclose($in);

            return false;
        }

        $written = 0;
        $ok = true;

        while (!feof($in)) {
            $chunk = fread($in, 80000);
            if ($chunk === false || $chunk === '') {
                break;
            }

            $written += strlen($chunk);
            if ($written > $maxSize) {
                $this->failureMessage = $this->modx->lexicon('ms3_err_gallery_too_large');
                $ok = false;
                break;
            }

            fwrite($out, $chunk);
        }

        fclose($out);
        fclose($in);

        return $ok;
    }

    /**
     * Не бомба ли это: предохранитель по числу пикселей до декодирования.
     */
    private function withinPixelCeiling(int $width, int $height, string $tmpFile): bool
    {
        if ($width > 0 && $height > 0 && $width * $height > self::MAX_IMAGE_PIXELS) {
            @unlink($tmpFile);
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                "[Gallery/Upload] image rejected: {$width}x{$height} exceeds pixel ceiling"
            );
            $this->failureMessage = $this->modx->lexicon('ms3_err_gallery_too_many_pixels');

            return false;
        }

        return true;
    }
}
