<?php

use MiniShop3\MiniShop3;
use MiniShop3\Model\msProduct;
use ModxPro\PdoTools\CoreTools;

/** @var modX $modx */
/** @var array $scriptProperties */
/** @var MiniShop3 $ms3 */
$ms3 = $modx->services->get('ms3');

$tpl = $modx->getOption('tpl', $scriptProperties, 'tpl.msOptions');
if (!empty($input) && empty($product)) {
    $product = $input;
}
if (!empty($name) && empty($options)) {
    $options = $name;
}

$product = !empty($product) && $product != $modx->resource->id
    ? $modx->getObject('msProduct', ['id' => $product])
    : $modx->resource;
if (!($product instanceof msProduct)) {
    return $modx->lexicon('ms_err_gallery_is_not_msproduct', [
        'id' => $product->id
    ]);
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
