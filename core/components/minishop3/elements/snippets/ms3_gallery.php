<?php

use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductData;
use MiniShop3\Model\msProductFile;
use MODX\Revolution\modX;
use MiniShop3\MiniShop3;
use ModxPro\PdoTools\Fetch;

/** @var modX $modx */
/** @var array $scriptProperties */
/** @var MiniShop3 $ms3 */

$ms3 = $modx->services->get('ms3');
$ms3->initialize($modx->context->key);
$pdoFetch = $modx->services->get(Fetch::class);
$pdoFetch->addTime('pdoTools loaded.');

$extensionsDir = $modx->getOption('extensionsDir', $scriptProperties, 'components/minishop3/img/mgr/extensions/', true);
$limit = $modx->getOption('limit', $scriptProperties, 0);
$tpl = $modx->getOption('tpl', $scriptProperties, 'tpl.msGallery');

/** @var msProduct $product */
$product = !empty($product) && $product != $modx->resource->id
    ? $modx->getObject(msProduct::class, ['id' => $product])
    : $modx->resource;
if (!($product instanceof msProduct)) {
    return "[msGallery] The resource with id = {$product->id} is not instance of msProduct.";
}

$where = [
    'product_id' => $product->id,
    'parent_id' => 0,
];
if (!empty($filetype)) {
    $where['type:IN'] = array_map('trim', explode(',', $filetype));
}
if (empty($showInactive)) {
    $where['active'] = 1;
}
$select = [
    'msProductFile' => $modx->getSelectColumns(msProductFile::class, 'msProductFile')
];

// Add user parameters
foreach (['where'] as $v) {
    if (!empty($scriptProperties[$v])) {
        $tmp = $scriptProperties[$v];
        if (!is_array($tmp)) {
            $tmp = json_decode($tmp, true);
        }
        if (is_array($tmp)) {
            $$v = array_merge($$v, $tmp);
        }
    }
    unset($scriptProperties[$v]);
}
$pdoFetch->addTime('Conditions prepared');
$default = [
    'class' => msProductFile::class,
    'where' => $where,
    'select' => $select,
    'limit' => $limit,
    'sortby' => '`position`',
    'sortdir' => 'ASC',
    'fastMode' => false,
    'return' => 'data',
    'nestedChunkPrefix' => 'minishop3_',
];
if ($scriptProperties['return'] === 'tpl') {
    unset($scriptProperties['return']);
}
// Merge all properties and run!
$pdoFetch->setConfig(array_merge($default, $scriptProperties), false);
$rows = $pdoFetch->run();
if ($scriptProperties['return'] === 'sql' || $scriptProperties['return'] === 'json') {
    return $rows;
}
$pdoFetch->addTime('Fetching thumbnails');

$resolution = [];
/** @var msProductData $data */
if ($data = $product->getOne('Data')) {
    if ($data->initializeMediaSource()) {
        $properties = $data->mediaSource->getProperties();
        if (isset($properties['thumbnails']['value'])) {
            $fileTypes = json_decode($properties['thumbnails']['value'], true);
            foreach ($fileTypes as $k => $v) {
                if (!is_numeric($k)) {
                    $resolution[] = $k;
                } elseif (!empty($v['name'])) {
                    $resolution[] = $v['name'];
                } else {
                    $resolution[] = @$v['w'] . 'x' . @$v['h'];
                }
            }
        }
    }
}

// Processing rows
$files = [];
foreach ($rows as $row) {
    if (isset($row['type']) && $row['type'] == 'image') {
        $c = $modx->newQuery(msProductFile::class, ['parent_id' => $row['id']]);
        $c->select('product_id,url');
        $tstart = microtime(true);
        if ($c->prepare() && $c->stmt->execute()) {
            $modx->queryTime += microtime(true) - $tstart;
            $modx->executedQueries++;
            while ($tmp = $c->stmt->fetch(PDO::FETCH_ASSOC)) {
                if (preg_match("#/{$tmp['product_id']}/(.*?)/#", $tmp['url'], $size)) {
                    $row[$size[1]] = $tmp['url'];
                }
            }
        }
    } elseif (isset($row['type'])) {
        $row['thumbnail'] = file_exists(MODX_ASSETS_PATH . $extensionsDir . $row['type'] . '.png')
            ? MODX_ASSETS_URL . $extensionsDir . $row['type'] . '.png'
            : MODX_ASSETS_URL . $extensionsDir . 'other.png';
        foreach ($resolution as $v) {
            $row[$v] = $row['thumbnail'];
        }
    }

    $files[] = $row;
}

if ($scriptProperties['return'] === 'data') {
    return $files;
}

$output = $pdoFetch->getChunk($tpl, [
    'files' => $files,
    'scriptProperties' => $scriptProperties
]);

if ($modx->user->hasSessionContext('mgr') && !empty($showLog)) {
    $output .= '<pre class="msGalleryLog">' . print_r($pdoFetch->getTime(), 1) . '</pre>';
}

if (!empty($toPlaceholder)) {
    $modx->setPlaceholder($toPlaceholder, $output);
} else {
    return $output;
}
