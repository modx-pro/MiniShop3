<?php

use MiniShop3\MiniShop3;
use ModxPro\PdoTools\Fetch;

/** @var modX $modx */
/** @var array $scriptProperties */
/** @var MiniShop3 $ms3 */

$ms3 = $modx->services->get('ms3');
$ms3->initialize($modx->context->key);
/** @var Fetch $pdoFetch */
$pdoFetch = $modx->services->get(Fetch::class);
$pdoFetch->addTime('pdoTools loaded.');

$tpl = $modx->getOption('tpl', $scriptProperties, 'tpl.msCart');
$cart = $ms3->cart->get();
$status = $ms3->cart->status();

// Do not show empty cart when displaying order details
if (!empty($_GET['msorder'])) {
    return '';
} elseif (empty($status['total_count'])) {
    return $pdoFetch->getChunk($tpl);
}

// Select cart products
$where = [
    'msProduct.id:IN' => [],
];
foreach ($cart as $entry) {
    $where['msProduct.id:IN'][] = $entry['id'];
}
$where['msProduct.id:IN'] = array_unique($where['msProduct.id:IN']);

// Include products properties
$leftJoin = [
    'Data' => [
        'class' => 'msProductData',
    ],
    'Vendor' => [
        'class' => 'msVendor',
        'on' => 'Data.vendor = Vendor.id',
    ],
];

// Select columns
$select = [
    'msProduct' => !empty($includeContent)
        ? $modx->getSelectColumns('msProduct', 'msProduct')
        : $modx->getSelectColumns('msProduct', 'msProduct', '', ['content'], true),
    'Data' => $modx->getSelectColumns('msProductData', 'Data', '', ['id'], true),
    'Vendor' => $modx->getSelectColumns('msVendor', 'Vendor', 'vendor.', ['id'], true),
];

// Include products thumbnails
if (!empty($includeThumbs)) {
    $thumbs = array_map('trim', explode(',', $includeThumbs));
    if (!empty($thumbs[0])) {
        foreach ($thumbs as $thumb) {
            $leftJoin[$thumb] = [
                'class' => 'msProductFile',
                'on' => "`{$thumb}`.product_id = msProduct.id AND `{$thumb}`.parent != 0 AND `{$thumb}`.path LIKE '%/{$thumb}/%' AND `{$thumb}`.`rank` = 0",
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
    'class' => 'msProduct',
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
// Merge all properties and run!
$pdoFetch->setConfig(array_merge($default, $scriptProperties), false);

$tmp = $pdoFetch->run();
$rows = [];
foreach ($tmp as $row) {
    $rows[$row['id']] = $row;
}

// Process products in cart
$products = [];
$total = ['count' => 0, 'weight' => 0, 'cost' => 0, 'discount' => 0];
foreach ($cart as $key => $entry) {
    if (!isset($rows[$entry['id']])) {
        continue;
    }
    $product = $rows[$entry['id']];

    $product['key'] = $key;
    $product['count'] = $entry['count'];
    $old_price = $product['old_price'];
    if ($product['price'] > $entry['price']) {
        $old_price = $product['price'];
    }
    $discount_price = $old_price > 0 ? $old_price - $entry['price'] : 0;

    $product['old_price'] = $ms3->format->price($old_price);
    $product['price'] = $ms3->format->price($entry['price']);
    $product['weight'] = $ms3->format->weight($entry['weight']);
    $product['cost'] = $ms3->format->price($entry['count'] * $entry['price']);
    $product['discount_price'] = $ms3->format->price($discount_price);
    $product['discount_cost'] = $ms3->format->price($entry['count'] * $discount_price);

    // Additional properties of product in cart
    if (!empty($entry['options']) && is_array($entry['options'])) {
        $product['options'] = $entry['options'];
        foreach ($entry['options'] as $option => $value) {
            $product['option.' . $option] = $value;
        }
    }

    // Add option values
    $options = $modx->call('msProductOption', 'loadOptions', [$modx, $product['id']]);
    $products[] = array_merge($product, $options);

    // Count total
    $total['count'] += $entry['count'];
    $total['cost'] += $entry['count'] * $entry['price'];
    $total['weight'] += $entry['count'] * $entry['weight'];
    $total['discount'] += $entry['count'] * $discount_price;
}
$total['cost'] = $ms3->format->price($total['cost']);
$total['discount'] = $ms3->format->price($total['discount']);
$total['weight'] = $ms3->format->weight($total['weight']);

$output = $pdoFetch->getChunk($tpl, [
    'total' => $total,
    'products' => $products,
]);

if ($modx->user->hasSessionContext('mgr') && !empty($showLog)) {
    $output .= '<pre class="msCartLog">' . print_r($pdoFetch->getTime(), true) . '</pre>';
}

if (!empty($toPlaceholder)) {
    $modx->setPlaceholder($toPlaceholder, $output);
} else {
    return $output;
}
