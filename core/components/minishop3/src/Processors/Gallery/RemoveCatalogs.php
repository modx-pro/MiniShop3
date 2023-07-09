<?php

namespace MiniShop3\Processors\Gallery;

use MODX\Revolution\modX;
use MODX\Revolution\Sources\modMediaSource;

class RemoveCatalogs
{
    public static function process(modX $modx, int $id)
    {
        $source = $modx->getObject(modMediaSource::class, $modx->getOption('ms_product_source_default'));
        $props = $source->get('properties');
        $path = MODX_BASE_PATH . $props['basePath']['value'] . $id;

        self::removeDir($path);
    }

    private static function removeDir($path)
    {
        $files = glob($path . '/*');
        foreach ($files as $file) {
            if (is_dir($file)) {
                self::removeDir($file);
            }
        }

        rmdir($path);
    }
}
