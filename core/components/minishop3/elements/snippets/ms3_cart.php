<?php

use MiniShop3\MiniShop3;
use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductData;
use MiniShop3\Model\msProductFile;
use MiniShop3\Model\msProductOption;
use MiniShop3\Model\msVendor;
use ModxPro\PdoTools\Fetch;

/** @var modX $modx */
/** @var array $scriptProperties */
/** @var MiniShop3 $ms3 */

if (isset($_POST['render'])) {
    unset($_POST['render']);
}

if (!$modx->services->has('ms3')) {
    $modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[MiniShop3] Service not registered');
    return '';
}

$ms3 = $modx->services->get('ms3');
$ms3->initialize($modx->context->key);

// Load lexicons for template
$modx->lexicon->load('minishop3:cart');

/** @var \MiniShop3\Services\TokenService $tokenService */
$tokenService = $modx->services->get('ms3_token_service');
$token = !empty($scriptProperties['customer_token'])
    ? $scriptProperties['customer_token']
    : $tokenService->resolveOrCreateToken();

$suppressRaw = $modx->getOption('suppressWhenMsOrder', $scriptProperties, false);
$suppressWhenMsOrder = is_bool($suppressRaw)
    ? $suppressRaw
    : filter_var($suppressRaw, FILTER_VALIDATE_BOOLEAN);
if ($suppressWhenMsOrder && !empty($_GET['msorder'])) {
    return '';
}

$ms3->cart->initialize($modx->context->key, $token);
$ms3->registerSnippet($scriptProperties);
/** @var Fetch $pdoFetch */
$pdoFetch = $modx->services->get(Fetch::class);
$pdoFetch->addTime('pdoTools loaded.');

$tpl = $modx->getOption('tpl', $scriptProperties, 'tpl.msCart');
$return = $modx->getOption('return', $scriptProperties, 'tpl');
$response = $ms3->cart->get();
$cart = $response['data']['cart'];
$status = $response['data']['status'];
$products = [];
$total = ['count' => 0, 'weight' => 0, 'cost' => 0, 'discount' => 0, 'positions' => 0];

/**
 * Overwrite snippet totals with cart status (after msOnGetStatusCart). Keys match CartItemManager::calculateStatus().
 *
 * If a plugin only overrides part of $status (e.g. only total_cost), line-derived total.discount may no longer
 * match total.cost; treat matching keys in $status as canonical for header totals — row-level discount_* on products
 * still reflect per-position math.
 */
$applyStatusToTotal = static function (array &$total, array $status): void {
    $map = [
        'total_cost' => ['cost', 'float'],
        'total_count' => ['count', 'int'],
        'total_weight' => ['weight', 'float'],
        'total_discount' => ['discount', 'float'],
        'total_positions' => ['positions', 'int'],
    ];
    foreach ($map as $statusKey => [$totalKey, $type]) {
        if (!isset($status[$statusKey]) || !is_numeric($status[$statusKey])) {
            continue;
        }
        $total[$totalKey] = $type === 'int' ? (int) $status[$statusKey] : (float) $status[$statusKey];
    }
};

$formatTotalForDisplay = static function (array &$total, MiniShop3 $ms3): void {
    $total['cost_formatted'] = $ms3->format->price($total['cost'], true);
    $total['weight_formatted'] = $ms3->format->weightWithUnit($total['weight']);
};

if (empty($status['total_count'])) {
    $applyStatusToTotal($total, $status);
    $formatTotalForDisplay($total, $ms3);
    if ($return === 'tpl') {
        return $pdoFetch->getChunk($tpl, compact('total', 'products', 'status'));
    }
    return compact('total', 'products', 'status');
}
if (empty($cart)) {
    $applyStatusToTotal($total, $status);
    $formatTotalForDisplay($total, $ms3);
    if ($return === 'tpl') {
        return $pdoFetch->getChunk($tpl, compact('total', 'products', 'status'));
    }
    return compact('total', 'products', 'status');
}

// Select cart products
$where = [
    'msProduct.id:IN' => [],
];
foreach ($cart as $entry) {
    $where['msProduct.id:IN'][] = $entry['product_id'];
}
$where['msProduct.id:IN'] = array_unique($where['msProduct.id:IN']);

// Include products properties
$leftJoin = [
    'Data' => [
        'class' => msProductData::class,
    ],
    'Vendor' => [
        'class' => msVendor::class,
        'on' => 'Data.vendor_id = Vendor.id',
    ],
];

// Select columns
$select = [
    'msProduct' => !empty($includeContent)
        ? $modx->getSelectColumns(msProduct::class, 'msProduct')
        : $modx->getSelectColumns(msProduct::class, 'msProduct', '', ['content'], true),
    'Data' => $modx->getSelectColumns(msProductData::class, 'Data', '', ['id'], true),
    'Vendor' => $modx->getSelectColumns(msVendor::class, 'Vendor', 'vendor.', ['id'], true),
];

// Include products thumbnails
if (!empty($includeThumbs)) {
    $thumbs = array_map('trim', explode(',', $includeThumbs));
    if (!empty($thumbs[0])) {
        foreach ($thumbs as $thumb) {
            $leftJoin[$thumb] = [
                'class' => msProductFile::class,
                'on' => "`{$thumb}`.product_id = msProduct.id AND `{$thumb}`.parent_id != 0 AND `{$thumb}`.path LIKE '%/{$thumb}/%' AND `{$thumb}`.`position` = 0",
            ];
            $select[$thumb] = "`{$thumb}`.url as '{$thumb}'";
        }
        $pdoFetch->addTime('Included list of thumbnails: <b>' . implode(', ', $thumbs) . '</b>.');
    }
}

// Add user parameters
foreach (['where', 'leftJoin', 'select'] as $v) {
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
    'class' => msProduct::class,
    'where' => $where,
    'leftJoin' => $leftJoin,
    'select' => $select,
    'sortby' => 'msProduct.id',
    'sortdir' => 'ASC',
    'groupby' => 'msProduct.id',
    'limit' => 0,
    'return' => 'data',
    'nestedChunkPrefix' => 'ms3_',
];
if ($return === 'tpl') {
    unset($scriptProperties['return']);
}
// Merge all properties and run!
$pdoFetch->setConfig(array_merge($default, $scriptProperties), false);

$tmp = $pdoFetch->run();
$rows = [];
foreach ($tmp as $row) {
    $rows[$row['id']] = $row;
}
// Process products in cart
foreach ($cart as $key => $entry) {
    if (!isset($rows[$entry['product_id']])) {
        continue;
    }
    $product = array_merge($rows[$entry['product_id']], $entry);

    $old_price = $product['old_price'];
    if ($product['price'] > $entry['price'] && empty($product['old_price'])) {
        $old_price = $product['price'];
    }
    $discount_price = $old_price > 0 ? $old_price - $entry['price'] : 0;

    $product['old_price'] = $old_price;
    $product['discount_price'] = $ms3->format->price($discount_price);
    $product['discount_cost'] = $entry['count'] * $discount_price;

    // Pre-formatted fields with currency/unit for display in chunks
    $product['old_cost'] = $old_price > 0 ? $entry['count'] * $old_price : 0;
    $product['price_formatted'] = $ms3->format->price($entry['price'], true);
    $product['old_price_formatted'] = $old_price > 0 ? $ms3->format->price($old_price, true) : '';
    $product['cost_formatted'] = $ms3->format->price($entry['count'] * $entry['price'], true);
    $product['old_cost_formatted'] = $old_price > 0 ? $ms3->format->price($entry['count'] * $old_price, true) : '';
    $product['discount_price_formatted'] = $discount_price > 0 ? $ms3->format->price($discount_price, true) : '';
    $product['discount_cost_formatted'] = $discount_price > 0 ? $ms3->format->price($entry['count'] * $discount_price, true) : '';
    $product['weight_formatted'] = $ms3->format->weightWithUnit($entry['weight']);

    // Additional properties of product in cart
    if (!empty($entry['options']) && is_array($entry['options'])) {
        $product['options'] = $entry['options'];
        foreach ($entry['options'] as $option => $value) {
            $product['option_' . $option] = $value;
        }
    }

    // Add option values
    $options = $modx->call(msProductOption::class, 'loadOptions', [$modx, $product['product_id']]);
    $products[] = array_merge($product, $options);

    // Count total
    $total['count'] += $entry['count'];
    $total['cost'] += $entry['count'] * $entry['price'];
    $total['weight'] += $entry['count'] * $entry['weight'];
    $total['discount'] += $entry['count'] * $discount_price;
    $total['positions']++;
}

$applyStatusToTotal($total, $status);
$formatTotalForDisplay($total, $ms3);

$outputData = [
    'total' => $total,
    'products' => $products,
    'status' => $status,
];

if ($return === 'data') {
    return $outputData;
}

$output = $pdoFetch->getChunk($tpl, $outputData);

if ($modx->user->hasSessionContext('mgr') && !empty($showLog)) {
    $output .= '<pre class="msCartLog">' . print_r($pdoFetch->getTime(), true) . '</pre>';
}

if (!empty($toPlaceholder)) {
    $modx->setPlaceholder($toPlaceholder, $output);
} else {
    return $output;
}
