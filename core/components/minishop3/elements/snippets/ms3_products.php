<?php

use MiniShop3\MiniShop3;
use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductData;
use MiniShop3\Model\msProductFile;
use MiniShop3\Model\msProductLink;
use MiniShop3\Model\msProductOption;
use MiniShop3\Model\msVendor;
use MiniShop3\Services\Category\CategoryProductScopeService;
use MiniShop3\Utils\EventGate;
use MiniShop3\Utils\ProductThumbnailJoin;
use MODX\Revolution\modPlugin;
use MODX\Revolution\modPluginEvent;
use ModxPro\PdoTools\Fetch;

/** @var \MODX\Revolution\modX $modx */
/** @var array $scriptProperties */
/** @var MiniShop3 $ms3 */

if (!$modx->services->has('ms3')) {
    $modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[MiniShop3] Service not registered');
    return '';
}

$ms3 = $modx->services->get('ms3');
$ms3->initialize($modx->context->key);

// Load lexicons for template (cart buttons)
$modx->lexicon->load('minishop3:cart');

$ms3->loadMap();

/** @var Fetch $pdoFetch */
$pdoFetch = $modx->services->get(Fetch::class);
$pdoFetch->addTime('pdoTools loaded.');

// Don't set default parents when using link parameter (linked products can be anywhere)
$link = $scriptProperties['link'] ?? null;
if (empty($link) && (!isset($parents) || $parents === '')) {
    $scriptProperties['parents'] = $modx->resource->id;
}
// Disable parents filtering when using link
if (!empty($link)) {
    $scriptProperties['parents'] = 0;
    $scriptProperties['depth'] = 0;
}

if (!empty($returnIds)) {
    $scriptProperties['return'] = 'ids';
}

if ($scriptProperties['return'] === 'ids') {
    $scriptProperties['returnIds'] = true;
}

// Start build "where" expression
$where = [
    'class_key' => msProduct::class,
];
if (empty($showZeroPrice)) {
    $where['Data.price:>'] = 0;
}
// Add grouping
$groupby = [
    'msProduct.id',
];

// Join tables
$leftJoin = [
    'Data' => ['class' => msProductData::class],
];

$select = [
    'msProduct' => !empty($includeContent)
        ? $modx->getSelectColumns(msProduct::class, 'msProduct')
        : $modx->getSelectColumns(msProduct::class, 'msProduct', '', ['content'], true),
    'Data' => $modx->getSelectColumns(msProductData::class, '`Data`', '', ['id'], true),
];

if (!empty($scriptProperties['includeVendorFields'])) {
    $includeVendorKeys = array_map('trim', explode(',', $scriptProperties['includeVendorFields']));
    $leftJoin['Vendor'] = ['class' => msVendor::class, 'on' => '`Data`.vendor_id=Vendor.id'];

    if ($includeVendorKeys[0] === '*') {
        $select['Vendor'] = $modx->getSelectColumns(msVendor::class, '`Vendor`', 'vendor_', ['id'], true);
    } else {
        $select['Vendor'] = $modx->getSelectColumns(msVendor::class, '`Vendor`', 'vendor_', $includeVendorKeys);
    }
}

// Include thumbnails
if (!empty($includeThumbs)) {
    $thumbs = array_map('trim', explode(',', $includeThumbs));
    foreach ($thumbs as $thumb) {
        if (empty($thumb)) {
            continue;
        }
        $leftJoin[$thumb] = [
            'class' => msProductFile::class,
            'on' => ProductThumbnailJoin::buildLeftJoinOn($modx, $thumb, $thumb),
        ];
        $select[$thumb] = "`{$thumb}`.url as `{$thumb}`";
    }
}

// Include linked products via innerJoin
$innerJoin = [];
$link = $scriptProperties['link'] ?? null;
$master = $scriptProperties['master'] ?? null;
$slave = $scriptProperties['slave'] ?? null;

if (!empty($link) && !empty($master)) {
    // Get slave products linked to this master
    $innerJoin['Link'] = [
        'class' => msProductLink::class,
        'alias' => 'Link',
        'on' => '`msProduct`.`id` = `Link`.`slave` AND `Link`.`link` = ' . (int)$link,
    ];
    $where['Link.master'] = (int)$master;
} elseif (!empty($link) && !empty($slave)) {
    // Get master products for this slave
    $innerJoin['Link'] = [
        'class' => msProductLink::class,
        'alias' => 'Link',
        'on' => '`msProduct`.`id` = `Link`.`master` AND `Link`.`link` = ' . (int)$link,
    ];
    $where['Link.slave'] = (int)$slave;
}

// Add user parameters
foreach (['where', 'leftJoin', 'innerJoin', 'select', 'groupby'] as $v) {
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

// pdoTools parent filter ignores msCategoryMember; scope via CategoryProductScopeService (#481).
$_ms3Parents = (string)($scriptProperties['parents'] ?? '');
if ($_ms3Parents !== '' && $_ms3Parents !== '0' && $modx->services->has('ms3_category_product_scope')) {
    /** @var CategoryProductScopeService $scopeService */
    $scopeService = $modx->services->get('ms3_category_product_scope');
    $_ms3Depth = (int)($scriptProperties['depth'] ?? 10);
    $_ms3CategoryIds = $scopeService->resolveCategoryIdsFromParents($_ms3Parents, $_ms3Depth);

    if ($_ms3CategoryIds !== []) {
        $where[] = $scopeService->buildMsProductsWhereForCategories($_ms3CategoryIds);
        $scriptProperties['parents'] = 0;
    }
}

// Add filters by options
$joinedOptions = [];
if (!empty($scriptProperties['optionFilters'])) {
    $filters = $scriptProperties['optionFilters'];
    if (!is_array($scriptProperties['optionFilters'])) {
        $filters = json_decode($scriptProperties['optionFilters'], true);
    }

    foreach ($filters as $key => $value) {
        $components = explode(':', $key, 2);

        if (count($components) === 2) {
            if (in_array(strtolower($components[0]), ['or', 'and'])) {
                [$operator, $key] = $components;
            }
        }

        $option = preg_replace('#\:.*#', '', $key);
        $key = str_replace($option, $option . '.value', $key);

        if (!in_array($option, $joinedOptions)) {
            $leftJoin[$option] = [
                'class' => msProductOption::class,
                'on' => "`{$option}`.product_id = Data.id AND `{$option}`.key = '{$option}'",
            ];
            $joinedOptions[] = $option;
        }

        $index = isset($operator) && in_array(strtolower($operator), ['or', 'and'], true)
            ? sprintf('%s:%s', strtoupper($operator), $key)
            : $key;
        $where[$index] = $value;
    }
}

// Add sort by options
if (!empty($scriptProperties['sortbyOptions'])) {
    $sorts = array_map('trim', explode(',', $scriptProperties['sortbyOptions']));
    foreach ($sorts as $sort) {
        $sort = explode(':', $sort);
        $option = $sort[0];
        if (preg_match("#\b{$option}\b#", $scriptProperties['sortby'], $matches)) {
            $type = 'string';
            if (isset($sort[1])) {
                $type = $sort[1];
            }
            switch ($type) {
                case 'number':
                case 'decimal':
                    $sortbyOptions = "CAST(`{$option}`.`value` AS DECIMAL(13,3))";
                    break;
                case 'int':
                case 'integer':
                    $sortbyOptions = "CAST(`{$option}`.`value` AS UNSIGNED INTEGER)";
                    break;
                case 'date':
                case 'datetime':
                    $sortbyOptions = "CAST(`{$option}`.`value` AS DATETIME)";
                    break;
                default:
                    $sortbyOptions = "`{$option}`.`value`";
                    break;
            }
            $scriptProperties['sortby'] = preg_replace("#\b{$option}\b#", $sortbyOptions, $scriptProperties['sortby']);
            $groupby[] = "`{$option}`.value";
        }

        if (!in_array($option, $joinedOptions)) {
            $leftJoin[$option] = [
                'class' => msProductOption::class,
                'on' => "`{$option}`.product_id = Data.id AND `{$option}`.key = '{$option}'",
            ];
            $joinedOptions[] = $option;
        }
    }
}

$default = [
    'class' => msProduct::class,
    'where' => $where,
    'leftJoin' => $leftJoin,
    'innerJoin' => $innerJoin,
    'select' => $select,
    'sortby' => 'msProduct.id',
    'sortdir' => 'ASC',
    'groupby' => implode(', ', $groupby),
    'return' => 'data',
    'nestedChunkPrefix' => 'ms3_',
];

// Merge all properties and run with error handling
try {
    $config = array_merge($default, $scriptProperties);
    $pdoFetch->setConfig($config, false);
    $rows = $pdoFetch->run();
} catch (\Exception $e) {
    $modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[ms3_products] Query error: ' . $e->getMessage());

    if ($modx->getOption('debug', null, false)) {
        return '<div class="alert alert-danger">Product loading error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }

    $rows = [];
}

if ($scriptProperties['return'] === 'json') {
    $rows = json_decode($rows, true);
}

// Parse usePackages parameter for external package integration
$usePackages = [];
if (!empty($scriptProperties['usePackages'])) {
    $usePackages = array_map('trim', explode(',', $scriptProperties['usePackages']));
}

// Event: msOnProductsLoad - bulk loading of additional data from external packages
if (!empty($rows) && is_array($rows)) {
    $productIds = array_column($rows, 'id');
    // by-ref + returnedValues['rows'] — see EventGate contract (#219/#245).
    $event = EventGate::invokeRaw($modx, 'msOnProductsLoad', [
        'rows' => &$rows,
        'productIds' => $productIds,
        'usePackages' => $usePackages,
        'scriptProperties' => $scriptProperties,
    ]);
    $rows = EventGate::applyReturnedArray($rows, $event['returnedValues'], 'rows');
    $pdoFetch->addTime('Invoked msOnProductsLoad event');
}

// Process rows
$output = $additionalPlaceholders = [];
if (!empty($rows) && is_array($rows)) {
    $c = $modx->newQuery(
        modPluginEvent::class,
        ['event:IN' => ['msOnGetProductPrice', 'msOnGetProductWeight', 'msOnGetProductFields']]
    );
    $c->innerJoin(modPlugin::class, 'modPlugin', 'modPlugin.id = modPluginEvent.pluginid');
    $c->where('modPlugin.disabled = 0');

    $modifications = $modx->getOption('ms3_price_snippet', null, false, true) ||
        $modx->getOption('ms3_weight_snippet', null, false, true) || $modx->getCount(modPluginEvent::class, $c);
    if ($modifications) {
        /** @var msProductData $product */
        $product = $modx->newObject(msProductData::class);
    }
    $pdoFetch->addTime('Checked the active modifiers');

    $opt_time = 0;
    $includedOptionKeys = [];
    $msProductOption = null;
    if (!empty($includeOptions)) {
        $includedOptionKeys = array_map('trim', explode(',', $includeOptions));
        $msProductOption = $modx->newObject(msProductOption::class);
    }

    foreach ($rows as $k => $row) {
        if ($modifications) {
            $product->fromArray($row, '', true, true);
            $tmp = $row['price'];
            $row['price'] = $product->getPrice($row);
            $row['weight'] = $product->getWeight($row);
            // A discount here, so we should replace old price
            if ($row['price'] < $tmp) {
                $row['old_price'] = $tmp;
            }
            $row = $product->modifyFields($row);
        }

        $row['idx'] = $pdoFetch->idx++;

        $opt_time_start = microtime(true);
        $options = [];
        if (!empty($includeOptions)) {
            $options = $msProductOption->getForProduct($row['id'], $includedOptionKeys);
        }

        $rows[$k] = $row = array_merge($additionalPlaceholders, $row, $options);
        $opt_time += microtime(true) - $opt_time_start;

        // Event: msOnProductPrepare - enrich single product data from external packages
        $event = EventGate::invokeRaw($modx, 'msOnProductPrepare', [
            'row' => &$rows[$k],
            'productId' => $row['id'],
            'idx' => $row['idx'],
        ]);
        $rows[$k] = EventGate::applyReturnedArray($rows[$k], $event['returnedValues'], 'row');
        $row = $rows[$k];

        $rawPrice = (float)($row['price'] ?? 0);
        $rawOldPrice = (float)($row['old_price'] ?? 0);
        $rawWeight = (float)($row['weight'] ?? 0);
        $row['price'] = $rawPrice;
        $row['old_price'] = $rawOldPrice;
        $row['weight'] = $rawWeight;

        $row['discount'] = 0;
        if ($rawOldPrice > 0 && $rawPrice > 0) {
            $row['discount'] = $ms3->format->discount($rawOldPrice, $rawPrice);
        }

        $withCurrency = !empty($scriptProperties['withCurrency']);
        $row['price_formatted'] = $ms3->format->price($rawPrice, $withCurrency);
        $row['old_price_formatted'] = $rawOldPrice > 0
            ? $ms3->format->price($rawOldPrice, $withCurrency)
            : '';
        $row['weight_formatted'] = $ms3->format->weightWithUnit($rawWeight);

        $rows[$k] = $row;

        if ($scriptProperties['return'] == 'data') {
            $tpl = $pdoFetch->defineChunk($row);
            $output[] = $pdoFetch->getChunk($tpl, $row);
        }
    }
    $pdoFetch->addTime('Time to load products options', $opt_time);
}

$log = '';
if ($modx->user->hasSessionContext('mgr') && !empty($showLog)) {
    $log .= '<pre class="msProductsLog">' . print_r($pdoFetch->getTime(), true) . '</pre>';
}

if ($scriptProperties['return'] == 'json') {
    $rows = json_encode($rows);
}

// Return output
if (is_string($rows)) {
    $modx->setPlaceholder('msProducts.log', $log);
    if (!empty($toPlaceholder)) {
        $modx->setPlaceholder($toPlaceholder, $rows);
    } else {
        return $rows;
    }
} elseif (!empty($toSeparatePlaceholders)) {
    $output['log'] = $log;
    $modx->setPlaceholders($output, $toSeparatePlaceholders);
} else {
    if (empty($outputSeparator)) {
        $outputSeparator = "\n";
    }
    $output['log'] = $log;
    $output = implode($outputSeparator, $output);

    if (!empty($tplWrapper) && (!empty($wrapIfEmpty) || !empty($output))) {
        $output = $pdoFetch->getChunk($tplWrapper, [
            'output' => $output,
        ]);
    }

    if (!empty($toPlaceholder)) {
        $modx->setPlaceholder($toPlaceholder, $output);
    } else {
        return $output;
    }
}
