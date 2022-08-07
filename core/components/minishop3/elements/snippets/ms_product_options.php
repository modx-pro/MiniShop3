<?php

use MiniShop3\MiniShop3;
use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductData;
use ModxPro\PdoTools\CoreTools;

/** @var modX $modx */
/** @var array $scriptProperties */
/** @var MiniShop3 $ms3 */
$ms3 = $modx->services->get('ms3');

$tpl = $modx->getOption('tpl', $scriptProperties, 'tpl.msProductOptions');
if (!empty($input) && empty($product)) {
    $product = $input;
}

$product = !empty($product) && $product != $modx->resource->id
    ? $modx->getObject('msProduct', ['id' => $product])
    : $modx->resource;
if (!($product instanceof msProduct)) {
    return $modx->lexicon('ms_err_gallery_is_not_msproduct', [
        'id' => $product->id
    ]);
}

$ignoreGroups = array_diff(array_map('trim', explode(',', $modx->getOption('ignoreGroups', $scriptProperties, ''))), ['']);
$ignoreOptions = array_diff(array_map('trim', explode(',', $modx->getOption('ignoreOptions', $scriptProperties, ''))), ['']);
$sortGroups = array_diff(array_map('trim', explode(',', $modx->getOption('sortGroups', $scriptProperties, ''))), ['']);
$sortOptions = array_diff(array_map('trim', explode(',', $modx->getOption('sortOptions', $scriptProperties, ''))), ['']);
$onlyOptions = array_diff(array_map('trim', explode(',', $modx->getOption('onlyOptions', $scriptProperties, ''))), ['']);
if (empty($sortOptions) && !empty($onlyOptions)) {
    $sortOptions = $onlyOptions;
}
$groups = !empty($groups)
    ? array_map('trim', explode(',', $groups))
    : [];
/** @var msProductData $data */
if ($data = $product->getOne('Data')) {
    $optionKeys = $data->getOptionKeys();
}
if (empty($optionKeys)) {
    return '';
}
$productData = $product->loadOptions();

$options = [];
foreach ($optionKeys as $key) {
    // Filter by key
    if (!empty($onlyOptions) && $onlyOptions[0] != '' && !in_array($key, $onlyOptions)) {
        continue;
    } elseif (in_array($key, $ignoreOptions)) {
        continue;
    }
    $option = [];
    foreach ($productData as $dataKey => $dataValue) {
        $dataKey = explode('.', $dataKey);
        if ($dataKey[0] == $key && (count($dataKey) > 1)) {
            $option[$dataKey[1]] = $dataValue;
        }
    }

    $skip = (!empty($ignoreGroups) && (in_array($option['category'], $ignoreGroups) || in_array($option['category_name'], $ignoreGroups)))
        || (!empty($groups) && !in_array($option['category'], $groups) && !in_array($option['category_name'], $groups));

    if (!$skip) {
        $option['value'] = $product->get($key);
        if (!empty($option['value'])) {
            $options[$key] = $option;
        }
    }
}

if (!empty($sortGroups) && !empty($options)) {
    $sortGroups = array_map('mb_strtolower', $sortGroups);
    uasort($options, function ($a, $b) use ($sortGroups) {
        $ai = array_search(mb_strtolower($a['category'], 'utf-8'), $sortGroups, true);
        $ai = $ai !== false ? $ai : array_search(mb_strtolower($a['category_name'], 'utf-8'), $sortGroups, true);
        $bi = array_search(mb_strtolower($b['category'], 'utf-8'), $sortGroups, true);
        $bi = $bi !== false ? $bi : array_search(mb_strtolower($b['category_name'], 'utf-8'), $sortGroups, true);
        if ($ai === false && $bi === false) {
            return 0;
        } elseif ($ai === false) {
            return 1;
        } elseif ($bi === false) {
            return -1;
        } elseif ($ai < $bi) {
            return -1;
        } elseif ($ai > $bi) {
            return 1;
        }
        return 0;
    });
}

if (!empty($sortOptions) && !empty($options)) {
    $sortOptions = array_map('mb_strtolower', $sortOptions);
    uksort($options, function ($a, $b) use ($sortOptions) {
        $ai = array_search(mb_strtolower($a, 'utf-8'), $sortOptions, true);
        $bi = array_search(mb_strtolower($b, 'utf-8'), $sortOptions, true);
        if ($ai === false && $bi === false) {
            return 0;
        } elseif ($ai === false) {
            return 1;
        } elseif ($bi === false) {
            return -1;
        } elseif ($ai < $bi) {
            return -1;
        } elseif ($ai > $bi) {
            return 1;
        }
        return 0;
    });
}

$options = $ms3->options->sortOptionValues($options, $scriptProperties['sortOptionValues']);

if (in_array($scriptProperties['return'], ['data', 'array'], true)) {
    return $options;
}

/** @var CoreTools $pdoTools */
$pdoTools = $modx->services->get(CoreTools::class);

return $pdoTools->getChunk($tpl, [
    'options' => $options,
]);
