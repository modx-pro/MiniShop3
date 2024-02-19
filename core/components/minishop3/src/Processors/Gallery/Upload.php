<?php

namespace MiniShop3\Processors\Gallery;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductFile;
use MODX\Revolution\modX;
use MODX\Revolution\Processors\ModelProcessor;
use MODX\Revolution\Sources\modFileMediaSource;
use MODX\Revolution\Sources\modMediaSource;

class Upload extends ModelProcessor
{
    public $classKey = msProductFile::class;
    public $languageTopics = ['minishop3:default', 'minishop3:product'];
    public $permission = 'msproductfile_save';
    /** @var modMediaSource $mediaSource */
    public $mediaSource;
    /** @var MiniShop3 $ms3 */
    protected $ms3;
    /** @var msProduct $product */
    private $product = 0;

    /**
     * @return bool|null|string
     */
    public function initialize()
    {
        if (!$this->modx->hasPermission($this->permission)) {
            return $this->modx->lexicon('access_denied');
        }
        /** @var msProduct $product */
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
            return $this->failure($this->modx->lexicon('ms3_err_gallery_ns'));
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

                return $this->failure($this->modx->lexicon('ms3_err_gallery_thumb'));
            } else {
                $this->product->updateProductImage();

                return $this->success('', $uploaded_file);
            }
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
        $tf = tempnam(MODX_BASE_PATH, 'ms3_');

        if (!empty($_FILES['file']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
            $name = $_FILES['file']['name'];
            move_uploaded_file($_FILES['file']['tmp_name'], $tf);
        } else {
            $file = $this->getProperty('file');
            if (!empty($file) && (strpos($file, '://') !== false || file_exists($file))) {
                $tmp = explode('/', $file);
                $name = end($tmp);
                if ($stream = fopen($file, 'r')) {
                    if ($res = fopen($tf, 'w')) {
                        while (!feof($stream)) {
                            fwrite($res, fread($stream, 80000));
                        }
                        fclose($res);
                    }
                    fclose($stream);
                }
            }
        }

        clearstatcache(true, $tf);
        if (file_exists($tf) && !empty($name) && $size = filesize($tf)) {
            /** @var msProductFile $o */
            $hash = ($o = $this->modx->newObject($this->classKey)) ? $o->generateHash($tf) : '';
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
                $data['properties'] = array_merge(
                    $data['properties'],
                    [
                        'width' => $tmp[0],
                        'height' => $tmp[1],
                        'bits' => $tmp['bits'],
                        'mime' => $tmp['mime'],
                    ]
                );
            } elseif (strpos($data['name'], '.webp') !== false) {
                $img = imagecreatefromwebp($tf);
                $width = imagesx($img);
                $height = imagesy($img);

                $data['properties'] = array_merge(
                    $data['properties'],
                    [
                        'width' => $width,
                        'height' => $height,
                        'mime' => 'image/webp',
                    ]
                );
            }
            return $data;
        } else {
            unlink($tf);

            return false;
        }
    }
}
