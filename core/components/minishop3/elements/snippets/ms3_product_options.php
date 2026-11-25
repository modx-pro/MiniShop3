<?php

use MiniShop3\MiniShop3;
use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductData;
use MiniShop3\Services\Catalog\CatalogResourceGroupVisibility;
use MiniShop3\Utils\CommaList;
use ModxPro\PdoTools\CoreTools;

/** @var \MODX\Revolution\modX $modx */
/** @var array $scriptProperties */
/** @var MiniShop3 $ms3 */

if (!$modx->services->has('ms3')) {
    $modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[MiniShop3] Service not registered');
    return '';
}

$ms3 = $modx->services->get('ms3');

$tpl = $modx->getOption('tpl', $scriptProperties, 'tpl.msProductOptions');
if (!empty($input) && empty($product)) {
    $product = $input;
}

// $product may arrive as an id or as an already loaded object. Casting the
// object to int emits a PHP 8 warning, so take its id explicitly (#842 review).
$passedProductId = empty($product) ? 0 : (is_object($product) ? (int) $product->get('id') : (int) $product);
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

$ignoreGroups = CommaList::parse($modx->getOption('ignoreGroups', $scriptProperties, ''));
$ignoreOptions = CommaList::parse($modx->getOption('ignoreOptions', $scriptProperties, ''));
$sortGroups = CommaList::parse($modx->getOption('sortGroups', $scriptProperties, ''));
$sortOptions = CommaList::parse($modx->getOption('sortOptions', $scriptProperties, ''));
$onlyOptions = CommaList::parse($modx->getOption('onlyOptions', $scriptProperties, ''));
if (empty($sortOptions) && !empty($onlyOptions)) {
    $sortOptions = $onlyOptions;
}
// Keep the old falsy semantics of &groups (e.g. '0' meant "no filter").
// MODX extracts snippet properties into variables, so accept both sources.
$groupsRaw = $scriptProperties['groups'] ?? $groups ?? '';
$groups = empty($groupsRaw) ? [] : CommaList::parse($groupsRaw);
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
    if (!empty($onlyOptions) && !in_array($key, $onlyOptions)) {
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

    $skip = (!empty($ignoreGroups) && in_array($option['group_name'] ?? null, $ignoreGroups, true))
        || (!empty($groups) && !in_array($option['group_name'] ?? null, $groups, true));

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
        $ai = array_search(mb_strtolower((string)($a['group_name'] ?? ''), 'utf-8'), $sortGroups, true);
        $bi = array_search(mb_strtolower((string)($b['group_name'] ?? ''), 'utf-8'), $sortGroups, true);
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

$sortOptionValues = $scriptProperties['sortOptionValues'] ?? '';
$options = $ms3->options->sortOptionValues($options, $sortOptionValues);

$return = $scriptProperties['return'] ?? '';
if (in_array($return, ['data', 'array'], true)) {
    return $options;
}

/** @var CoreTools $pdoTools */
$pdoTools = $modx->services->get(CoreTools::class);

return $pdoTools->getChunk($tpl, [
    'options' => $options,
]);
