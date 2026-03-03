<?php

use xPDO\Transport\xPDOTransport;
use MODX\Revolution\modX;

/** @var xPDOTransport $transport */
/** @var array $options */

/** @var modX $modx */

use MODX\Revolution\modSystemSetting;
use MODX\Revolution\Sources\modMediaSource;

if ($transport->xpdo) {
    $modx = $transport->xpdo;
    switch ($options[xPDOTransport::PACKAGE_ACTION]) {
        case xPDOTransport::ACTION_INSTALL:
        case xPDOTransport::ACTION_UPGRADE:
            $tmp = explode('/', MODX_ASSETS_URL);
            $assets = $tmp[count($tmp) - 2];

            $properties = [
                'name' => 'MS3 Images',
                'description' => 'Default media source for images of MiniShop3 products',
                'class_key' => 'sources.modFileMediaSource',
                'properties' => [
                    'basePath' => [
                        'name' => 'basePath',
                        'desc' => 'prop_file.basePath_desc',
                        'type' => 'textfield',
                        'lexicon' => 'core:source',
                        'value' => $assets . '/images/products/',
                    ],
                    'baseUrl' => [
                        'name' => 'baseUrl',
                        'desc' => 'prop_file.baseUrl_desc',
                        'type' => 'textfield',
                        'lexicon' => 'core:source',
                        'value' => $assets . '/images/products/',
                    ],
                    'imageExtensions' => [
                        'name' => 'imageExtensions',
                        'desc' => 'prop_file.imageExtensions_desc',
                        'type' => 'textfield',
                        'lexicon' => 'core:source',
                        'value' => 'jpg,jpeg,png,gif,webp',
                    ],
                    'allowedFileTypes' => [
                        'name' => 'allowedFileTypes',
                        'desc' => 'prop_file.allowedFileTypes_desc',
                        'type' => 'textfield',
                        'lexicon' => 'core:source',
                        'value' => 'jpg,jpeg,png,gif,webp',
                    ],
                    'thumbnailType' => [
                        'name' => 'thumbnailType',
                        'desc' => 'prop_file.thumbnailType_desc',
                        'type' => 'list',
                        'lexicon' => 'core:source',
                        'options' => [
                            ['text' => 'Png', 'value' => 'png'],
                            ['text' => 'Jpg', 'value' => 'jpg'],
                            ['text' => 'Webp', 'value' => 'webp'],
                        ],
                        'value' => 'jpg',
                    ],
                    'thumbnails' => [
                        'name' => 'thumbnails',
                        'desc' => 'ms3_source_thumbnails_desc',
                        'type' => 'textarea',
                        'lexicon' => 'MiniShop3:setting',
                        // Базовая конфигурация для интернет-магазина (Intervention Image v3)
                        // thumb (150x150 WebP) - миниатюры в списках
                        // small (300x300 WebP) - карточки товаров
                        // medium (600x600 WebP) - галерея на странице товара
                        // large (1200x1200 JPEG) - zoom/детальный просмотр
                        'value' => '{"thumb":{"width":150,"height":150,"quality":80,"mode":"cover","format":"webp"},"small":{"width":300,"height":300,"quality":85,"mode":"cover","format":"webp"},"medium":{"width":600,"height":600,"quality":85,"mode":"cover","format":"webp"},"large":{"width":1200,"height":1200,"quality":85,"mode":"max","format":"jpg"}}',
                    ],
                    'maxUploadWidth' => [
                        'name' => 'maxUploadWidth',
                        'desc' => 'ms3_source_max_upload_width_desc',
                        'type' => 'numberfield',
                        'lexicon' => 'MiniShop3:setting',
                        'value' => 1920,
                    ],
                    'maxUploadHeight' => [
                        'name' => 'maxUploadHeight',
                        'desc' => 'ms3_source_max_upload_height_desc',
                        'type' => 'numberfield',
                        'lexicon' => 'MiniShop3:setting',
                        'value' => 1080,
                    ],
                    'maxUploadSize' => [
                        'name' => 'maxUploadSize',
                        'desc' => 'ms3_source_max_upload_size_desc',
                        'type' => 'numberfield',
                        'lexicon' => 'MiniShop3:setting',
                        'value' => 10485760,
                    ],
                    'imageNameType' => [
                        'name' => 'imageNameType',
                        'desc' => 'ms3_source_image_name_type_desc',
                        'type' => 'list',
                        'lexicon' => 'MiniShop3:setting',
                        'options' => [
                            ['text' => 'Hash', 'value' => 'hash'],
                            ['text' => 'Friendly', 'value' => 'friendly'],
                        ],
                        'value' => 'friendly',
                    ],
                ],
                'is_stream' => 1,
            ];

            $settings_properties = ['key' => 'ms3_product_source_default'];
            /** @var $setting modSystemSetting */
            $setting = $modx->getObject(modSystemSetting::class, $settings_properties) ?:
                $modx->newObject(modSystemSetting::class, $settings_properties);

            $c = $modx->newQuery(modMediaSource::class);
            $c->where(['id' => $setting->get('value')]);
            $c->orCondition(['name' => $properties['name']]);
            /** @var $source modMediaSource */
            if (!$source = $modx->getObject(modMediaSource::class, $c)) {
                $source = $modx->newObject(modMediaSource::class, $properties);
            } else {
                $default = $source->get('properties');
                foreach ($properties['properties'] as $k => $v) {
                    if (!array_key_exists($k, $default)) {
                        $default[$k] = $v;
                    }
                }
                foreach ($default as $k => $prop) {
                    if (is_array($prop) && !array_key_exists('desc', $prop)) {
                        $default[$k]['desc'] = '';
                    }
                }
                $source->set('properties', $default);
            }
            $source->save();

            $setting->set('value', $source->get('id'));
            $setting->save();

            @mkdir(MODX_ASSETS_PATH . 'images/');
            @mkdir(MODX_ASSETS_PATH . 'images/products/');
            break;
        case xPDOTransport::ACTION_UNINSTALL:
            break;
    }
}
return true;
