<?php

use MiniShop3\MiniShop3;
use MiniShop3\Model\msProduct;
use MiniShop3\Services\Catalog\CatalogResourceGroupVisibility;
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

// $product from snippet props may be an id (int|string) or an object, not an msProduct yet.
// The snippet can also be called outside of a resource context, where $modx->resource is null.
$_ms3HasProduct = !empty($product);
$_ms3RequestedId = $_ms3HasProduct
    ? (is_object($product) ? (int) ($product->id ?? 0) : (int) $product)
    : 0;
$_ms3ResourceId = is_object($modx->resource) ? (int) ($modx->resource->id ?? 0) : 0;
$_ms3LoadedById = $_ms3HasProduct && $_ms3RequestedId !== $_ms3ResourceId;
$product = $_ms3LoadedById
    ? $modx->getObject(msProduct::class, ['id' => $_ms3RequestedId])
    : $modx->resource;
if (!($product instanceof msProduct)) {
    return $modx->lexicon('ms3_err_options_is_not_msproduct', [
        'id' => $_ms3LoadedById ? $_ms3RequestedId : $_ms3ResourceId
    ]);
}
if ($_ms3LoadedById && !(new CatalogResourceGroupVisibility($modx))->isVisibleForRequest((int) $product->id)) {
    return '';
}

$names = array_map('trim', explode(',', $options));
$options = [];
foreach ($names as $name) {
    if (!empty($name) && $option = $product->get($name)) {
        if (!is_array($option)) {
            $option = [$option];
        }
        if (isset($option[0]) and (trim($option[0]) != '')) {
            $options[$name] = $option;
        }
    }
}

$options = $ms3->options->sortOptionValues($options, $scriptProperties['sortOptionValues']);

/** @var CoreTools $pdoTools */
$pdoTools = $modx->services->get(CoreTools::class);

return $pdoTools->getChunk($tpl, [
    'id' => $product->id,
    'options' => $options,
]);
