<?php

use MiniShop3\MiniShop3;
use MiniShop3\Model\msCategory;
use MiniShop3\Model\msCategoryMember;
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
            'on' => "`{$thumb}`.product_id = msProduct.id AND `{$thumb}`.`position` = 0 AND `{$thumb}`.path LIKE '%/{$thumb}/%'",
        ];
        $select[$thumb] = "`{$thumb}`.url as `{$thumb}`";
        $groupby[] = "`{$thumb}`.url";
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

// Workaround: pdoTools проверяет 'msCategory' в classMap, но MiniShop3 использует namespace
// Добавляем товары из дополнительных категорий (msCategoryMember) через кастомный WHERE
// TODO убрать этот блок в случае доработок pdoTools
$_ms3Parents = (string)($scriptProperties['parents'] ?? '');
if ($_ms3Parents !== '' && $_ms3Parents !== '0') {
    $_ms3Depth = (int)($scriptProperties['depth'] ?? 10);
    $_ms3ParentsIn = [];
    $_ms3ParentsOut = [];

    // Разбираем parents: положительные - включить, отрицательные - исключить
    foreach (array_map('trim', explode(',', $_ms3Parents)) as $_ms3Parent) {
        $_ms3Parent = (int)$_ms3Parent;
        if ($_ms3Parent > 0) {
            $_ms3ParentsIn[] = $_ms3Parent;
        } elseif ($_ms3Parent < 0) {
            $_ms3ParentsOut[] = abs($_ms3Parent);
        }
    }

    // Получаем дочерние категории для включения (только msCategory, не все ресурсы)
    if (!empty($_ms3ParentsIn) && $_ms3Depth > 0) {
        $_ms3CatIds = $_ms3ParentsIn;
        for ($_ms3i = 0; $_ms3i < $_ms3Depth; $_ms3i++) {
            $_ms3CatQuery = $modx->newQuery(msCategory::class);
            $_ms3CatQuery->where([
                'class_key' => msCategory::class,
                'parent:IN' => $_ms3CatIds,
                'published' => 1,
                'deleted' => 0,
            ]);
            $_ms3CatQuery->select('id');

            if ($_ms3CatQuery->prepare() && $_ms3CatQuery->stmt->execute()) {
                $_ms3ChildIds = $_ms3CatQuery->stmt->fetchAll(\PDO::FETCH_COLUMN);
                if (empty($_ms3ChildIds)) {
                    break;
                }
                $_ms3ParentsIn = array_merge($_ms3ParentsIn, $_ms3ChildIds);
                $_ms3CatIds = $_ms3ChildIds;
            } else {
                break;
            }
        }
    }

    // Получаем дочерние категории для исключения
    if (!empty($_ms3ParentsOut) && $_ms3Depth > 0) {
        $_ms3CatIds = $_ms3ParentsOut;
        for ($_ms3i = 0; $_ms3i < $_ms3Depth; $_ms3i++) {
            $_ms3CatQuery = $modx->newQuery(msCategory::class);
            $_ms3CatQuery->where([
                'class_key' => msCategory::class,
                'parent:IN' => $_ms3CatIds,
                'published' => 1,
                'deleted' => 0,
            ]);
            $_ms3CatQuery->select('id');

            if ($_ms3CatQuery->prepare() && $_ms3CatQuery->stmt->execute()) {
                $_ms3ChildIds = $_ms3CatQuery->stmt->fetchAll(\PDO::FETCH_COLUMN);
                if (empty($_ms3ChildIds)) {
                    break;
                }
                $_ms3ParentsOut = array_merge($_ms3ParentsOut, $_ms3ChildIds);
                $_ms3CatIds = $_ms3ChildIds;
            } else {
                break;
            }
        }
    }

    // Вычитаем исключённые категории из включённых
    $_ms3ParentsIn = array_unique($_ms3ParentsIn);
    $_ms3ParentsOut = array_unique($_ms3ParentsOut);
    if (!empty($_ms3ParentsOut)) {
        $_ms3ParentsIn = array_diff($_ms3ParentsIn, $_ms3ParentsOut);
    }

    // ВСЕГДА отключаем pdoTools parent processing - мы обрабатываем сами
    if (!empty($_ms3ParentsIn)) {
        $_ms3ParentsList = implode(',', array_map('intval', $_ms3ParentsIn));

        // Получаем товары из дополнительных категорий
        $_ms3MemberQuery = $modx->newQuery(msCategoryMember::class);
        $_ms3MemberQuery->where(['category_id:IN' => $_ms3ParentsIn]);
        $_ms3MemberQuery->select('product_id');

        $_ms3MemberIds = [];
        if ($_ms3MemberQuery->prepare() && $_ms3MemberQuery->stmt->execute()) {
            $_ms3MemberIds = $_ms3MemberQuery->stmt->fetchAll(\PDO::FETCH_COLUMN);
        }

        // Строим WHERE: parent IN категориях, опционально OR id IN доп. категориях
        if (!empty($_ms3MemberIds)) {
            $_ms3MembersList = implode(',', array_map('intval', $_ms3MemberIds));
            $where[] = "(`msProduct`.`parent` IN ({$_ms3ParentsList}) OR `msProduct`.`id` IN ({$_ms3MembersList}))";
        } else {
            // Нет товаров в доп. категориях - просто фильтруем по parent
            $where[] = "`msProduct`.`parent` IN ({$_ms3ParentsList})";
        }

        // ВСЕГДА отключаем стандартную фильтрацию pdoTools по parents
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
    $modx->invokeEvent('msOnProductsLoad', [
        'rows' => $rows,
        'productIds' => $productIds,
        'usePackages' => $usePackages,
        'scriptProperties' => $scriptProperties,
    ]);
    // Apply plugin mutations via returnedValues (by-ref params don't propagate
    // through MODX invokeEvent scope isolation). Plugins should set
    // $modx->event->returnedValues = ['rows' => [...]] with full rows collection
    // (typically enriched copy of the original array).
    if (isset($modx->event->returnedValues) && is_array($modx->event->returnedValues)) {
        $returned = $modx->event->returnedValues;
        if (isset($returned['rows']) && is_array($returned['rows'])) {
            $rows = $returned['rows'];
        }
    }
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

        $row['discount'] = 0;
        if (!empty($row['old_price']) && $row['old_price'] > 0 && !empty($row['price']) && $row['price'] > 0) {
            $row['discount'] = $ms3->format->discount($row['old_price'], $row['price']);
        }

        if (!empty($scriptProperties['formatPrices'])) {
            $withCurrency = !empty($scriptProperties['withCurrency']);
            $row['price'] = $ms3->format->price($row['price'], $withCurrency);
            $row['old_price'] = $ms3->format->price($row['old_price'], $withCurrency);
            $row['weight'] = $ms3->format->weight($row['weight']);
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
        $modx->invokeEvent('msOnProductPrepare', [
            'row' => $rows[$k],
            'productId' => $row['id'],
            'idx' => $row['idx'],
        ]);
        // Apply plugin mutations via returnedValues (by-ref params don't propagate
        // through MODX invokeEvent scope isolation).
        if (isset($modx->event->returnedValues) && is_array($modx->event->returnedValues)) {
            $returned = $modx->event->returnedValues;
            if (isset($returned['row']) && is_array($returned['row'])) {
                $rows[$k] = array_merge($rows[$k], $returned['row']);
            }
        }
        $row = $rows[$k]; // Update local variable after event modifications

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
