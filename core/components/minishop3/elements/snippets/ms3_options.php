<?php

use MiniShop3\MiniShop3;
use MiniShop3\Model\msProduct;
use MiniShop3\Services\Catalog\CatalogResourceGroupVisibility;
use MiniShop3\Utils\ValuePresence;
use ModxPro\PdoTools\CoreTools;

/** @var \MODX\Revolution\modX $modx */
/** @var array $scriptProperties */
/** @var MiniShop3 $ms3 */

if (!$modx->services->has('ms3')) {
    $modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[MiniShop3] Service not registered');
    return '';
}

$ms3 = $modx->services->get('ms3');

$tpl = $modx->getOption('tpl', $scriptProperties, 'tpl.msOptions');
if (!empty($input) && empty($product)) {
    $product = $input;
}
$options = $options ?? '';
if (!empty($name) && empty($options)) {
    $options = $name;
}

// $product may be an id or an already loaded object; casting an object to int
// emits a PHP 8 warning, so take its id explicitly.
$passedProductId = empty($product)
    ? 0
    : ($product instanceof msProduct ? (int) $product->get('id') : (int) $product);
$_ms3LoadedById = $passedProductId > 0 && $passedProductId !== (int) $modx->resource->id;
$product = $_ms3LoadedById
    ? $modx->getObject(msProduct::class, ['id' => $passedProductId])
    : $modx->resource;
if (!($product instanceof msProduct)) {
    return $modx->lexicon('ms3_err_options_is_not_msproduct', [
        'id' => $product->id
    ]);
}
if ($_ms3LoadedById && !(new CatalogResourceGroupVisibility($modx))->isVisibleForRequest((int) $product->id)) {
    return '';
}

$names = array_map('trim', explode(',', $options));
$options = [];
foreach ($names as $name) {
    if ($name === '') {
        continue;
    }
    $option = $product->get($name);
    // Assignment inside empty() dropped 0 and "0"; blank scalars/arrays stay hidden (#859).
    if (ValuePresence::isBlank($option)) {
        continue;
    }
    if (!is_array($option)) {
        $option = [$option];
    }
    $firstValue = reset($option);
    if (!ValuePresence::isBlank($firstValue)) {
        $options[$name] = $option;
    }
}

$options = $ms3->options->sortOptionValues($options, $scriptProperties['sortOptionValues']);

/** @var CoreTools $pdoTools */
$pdoTools = $modx->services->get(CoreTools::class);

return $pdoTools->getChunk($tpl, [
    'id' => $product->id,
    'options' => $options,
]);
