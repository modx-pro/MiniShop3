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

$ms3 = $modx->services->get('ms3');
$ms3->initialize($modx->context->key);
if (!empty($_SESSION['ms3']) && !empty($_SESSION['ms3']['customer_token'])) {
    $token = $_SESSION['ms3']['customer_token'];
} else {
    $response = $ms3->customer->generateToken();
    $token = $response['data']['token'];
}
$ms3->cart->initialize($modx->context->key, $token);
//TODO Как то передать название сниппета, в т.ч путь для файлового
$ms3->registerSnippet($scriptProperties);
/** @var Fetch $pdoFetch */
$pdoFetch = $modx->services->get(Fetch::class);
$pdoFetch->addTime('pdoTools loaded.');

$tpl = $modx->getOption('tpl', $scriptProperties, 'tpl.msCart');
$response = $ms3->cart->get();
$cart = $response['data']['cart'];
$status = $response['data']['status'];
$products = [];
$total = ['count' => 0, 'weight' => 0, 'cost' => 0, 'discount' => 0, 'positions' => 0];

// Do not show empty cart when displaying order details
if (!empty($_GET['msorder'])) {
    return '';
} elseif (empty($status['total_count'])) {
    return $pdoFetch->getChunk($tpl, compact('total', 'products'));
}
if (empty($cart)) {
    return $pdoFetch->getChunk($tpl, compact('total', 'products'));
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

//TODO Поля вендор сделать выборочными
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
    $product = $rows[$entry['product_id']];

    $product['product_key'] = $key;
    $product['count'] = $entry['count'];
    $product['options'] = $entry['options'];
    $old_price = $product['old_price'];
    if ($product['price'] > $entry['price'] && empty($product['old_price'])) {
        $old_price = $product['price'];
    }
    $discount_price = $old_price > 0 ? $old_price - $entry['price'] : 0;

    $product['old_price'] = $old_price;
    $product['price'] = $entry['price'];
    $product['weight'] = $entry['weight'];
    $product['cost'] = $entry['count'] * $entry['price'];
    $product['discount_price'] = $ms3->format->price($discount_price);
    $product['discount_price'] = $discount_price;
    $product['discount_cost'] = $entry['count'] * $discount_price;

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
