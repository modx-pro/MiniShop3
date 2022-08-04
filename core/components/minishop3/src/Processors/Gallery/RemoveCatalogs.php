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
        $imgPath = MODX_BASE_PATH . $props['basePath']['value'] . $id;
        $modx->runProcessor('browser/directory/remove', ['dir' => $imgPath]);
    }
}
