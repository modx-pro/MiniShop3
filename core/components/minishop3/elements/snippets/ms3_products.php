<?php

use MiniShop3\MiniShop3;
use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductData;
use MiniShop3\Model\msProductFile;
use MiniShop3\Model\msProductLink;
use MiniShop3\Model\msProductOption;
use MiniShop3\Model\msVendor;
use MODX\Revolution\modPlugin;
use MODX\Revolution\modPluginEvent;
use ModxPro\PdoTools\Fetch;

/** @var modX $modx */
/** @var array $scriptProperties */
/** @var MiniShop3 $ms3 */
$ms3 = $modx->services->get('ms3');
$ms3->initialize($modx->context->key);
/** @var Fetch $pdoFetch */
$pdoFetch = $modx->services->get(Fetch::class);
$pdoFetch->addTime('pdoTools loaded.');

if (isset($parents) && $parents === '') {
    $scriptProperties['parents'] = $modx->resource->id;
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
            'on' => "`{$thumb}`.product_id = msProduct.id AND `{$thumb}`.`position` = 0 AND `{$thumb}`.path LIKE '%/{$thumb}/%'",
        ];
        $select[$thumb] = "`{$thumb}`.url as `{$thumb}`";
        $groupby[] = "`{$thumb}`.url";
    }
}

// Include linked products
$innerJoin = [];
if (!empty($link) && !empty($master)) {
    $innerJoin['Link'] = [
        'class' => msProductLink::class,
        'alias' => 'Link',
        'on' => 'msProduct.id = Link.slave AND Link.link = ' . $link,
    ];
    $where['Link.master'] = $master;
} elseif (!empty($link) && !empty($slave)) {
    $innerJoin['Link'] = [
        'class' => msProductLink::class,
        'alias' => 'Link',
        'on' => 'msProduct.id = Link.master AND Link.link = ' . $link,
    ];
    $where['Link.slave'] = $slave;
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
// Merge all properties and run!
$pdoFetch->setConfig(array_merge($default, $scriptProperties), false);
$rows = $pdoFetch->run();

if ($scriptProperties['return'] === 'json') {
    $rows = json_decode($rows, true);
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
//        $row['price'] = $ms3->format->price($row['price']);
//        $row['old_price'] = $ms3->format->price($row['old_price']);
//        $row['weight'] = $ms3->format->price($row['weight']);
        $row['idx'] = $pdoFetch->idx++;

        $opt_time_start = microtime(true);
        $options = [];
        if (!empty($includeOptions)) {
            $options = $msProductOption->getForProduct($row['id'], $includedOptionKeys);
        }

        $rows[$k] = $row = array_merge($additionalPlaceholders, $row, $options);
        $opt_time += microtime(true) - $opt_time_start;

        if ($scriptProperties['return'] == 'data') {
            $tpl = $pdoFetch->defineChunk($row);
            $output[] = $pdoFetch->getChunk($tpl, $row);
        }
    }
    $pdoFetch->addTime('Time to load products options', $opt_time);
}

$log = '';
if ($modx->user->hasSessionContext('mgr') && !empty($showLog)) {
    $log .= '<pre class="msProductsLog">' . print_r($pdoFetch->getTime(), 1) . '</pre>';
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
