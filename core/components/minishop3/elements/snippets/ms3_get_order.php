<?php

use MiniShop3\MiniShop3;
use MiniShop3\Model\msOrder;
use MiniShop3\Services\Payment\PaymentLinkResolver;
use MiniShop3\Model\msOrderProduct;
use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductData;
use MiniShop3\Model\msProductFile;
use MiniShop3\Model\msProductOption;
use MiniShop3\Model\msVendor;
use MiniShop3\Utils\ProductThumbnailJoin;
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

// Load lexicons for template
$modx->lexicon->load('minishop3:default');
$modx->lexicon->load('minishop3:cart');

/** @var Fetch $pdoFetch */
$pdoFetch = $modx->services->get(Fetch::class);
$pdoFetch->addTime('pdoTools loaded.');

$tpl = $modx->getOption('tpl', $scriptProperties, 'tpl.msGetOrder');

$orderIdentifier = $scriptProperties['id'] ?? $_GET['msorder'] ?? null;

if (empty($orderIdentifier)) {
    $modx->log(modX::LOG_LEVEL_WARN, '[msGetOrder] Missing order identifier');
    return '';
}

/** @var msOrder $msOrder */
if (is_string($orderIdentifier) && strlen($orderIdentifier) === 36) {
    $msOrder = $modx->getObject(msOrder::class, ['uuid' => $orderIdentifier]);
    $id = $msOrder ? $msOrder->get('id') : 0;
} else {
    $id = (int)$orderIdentifier;
    if ($id <= 0) {
        $modx->log(modX::LOG_LEVEL_WARN, '[msGetOrder] Invalid order ID');
        return '';
    }
    $msOrder = $modx->getObject(msOrder::class, ['id' => $id]);
}

if (!$msOrder) {
    return $modx->lexicon('ms3_err_order_nf');
}
$customerId = null;
/** @var \MiniShop3\Services\TokenService $tokenService */
$tokenService = $modx->services->get('ms3_token_service');
$token = $tokenService->resolveOrCreateToken();
if (!empty($token)) {
    $customer = $ms3->customer->getByToken($token);
    if (!empty($customer)) {
        $customerId = $customer->get('id');
    }
}

$isUuidAccess = is_string($orderIdentifier) && strlen($orderIdentifier) === 36;

$canView = (
        !empty($_SESSION['ms3']['orders']) && in_array($id, $_SESSION['ms3']['orders']))
    || $msOrder->get('user_id') == $modx->user->id
    || !empty($customerId) && $msOrder->get('customer_id') == $customerId
    || $modx->user->hasSessionContext('mgr')
    || $isUuidAccess;

if (!$canView) {
    $modx->log(modX::LOG_LEVEL_WARN, "[msGetOrder] Access denied for order {$id}");
    return '';
}

// Select ordered products
$where = [
    'msOrderProduct.order_id' => $id,
];

// Include products properties
$leftJoin = [
    'msProduct' => [
        'class' => msProduct::class,
        'on' => 'msProduct.id = msOrderProduct.product_id',
    ],
    'Data' => [
        'class' => msProductData::class,
        'on' => 'msProduct.id = Data.id',
    ],
    'Vendor' => [
        'class' => msVendor::class,
        'on' => 'Data.vendor_id = Vendor.id',
    ],
];

$select = [
    'msProduct' => !empty($includeContent)
        ? $modx->getSelectColumns(msProduct::class, 'msProduct')
        : $modx->getSelectColumns(msProduct::class, 'msProduct', '', ['content'], true),
    'Data' => $modx->getSelectColumns(
        msProductData::class,
        'Data',
        '',
        ['id'],
        true
    ) . ',`Data`.`price` as `original_price`',
    'Vendor' => $modx->getSelectColumns(msVendor::class, 'Vendor', 'vendor.', ['id'], true),
    'OrderProduct' => $modx->getSelectColumns(
        msOrderProduct::class,
        'msOrderProduct',
        '',
        ['id'],
        true
        ) . ', `msOrderProduct`.`id` as `order_product_id`',
];

// Include products thumbnails
if (!empty($includeThumbs)) {
    $thumbs = array_map('trim', explode(',', $includeThumbs));
    if (!empty($thumbs[0])) {
        foreach ($thumbs as $thumb) {
            $leftJoin[$thumb] = [
                'class' => msProductFile::class,
                'on' => ProductThumbnailJoin::buildLeftJoinOn($modx, $thumb, $thumb),
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

// Tables for joining
$default = [
    'class' => msOrderProduct::class,
    'where' => $where,
    'leftJoin' => $leftJoin,
    'select' => $select,
    'joinTVsTo' => 'msProduct',
    'sortby' => 'msOrderProduct.id',
    'sortdir' => 'asc',
    'groupby' => 'msOrderProduct.id',
    'fastMode' => false,
    'limit' => 0,
    'return' => 'data',
    'decodeJSON' => true,
    'nestedChunkPrefix' => 'ms3_',
];
// Merge all properties and run!
$pdoFetch->setConfig(array_merge($default, $scriptProperties), true);

try {
    $rows = $pdoFetch->run();
} catch (\Exception $e) {
    $modx->log(modX::LOG_LEVEL_ERROR, '[msGetOrder] Error fetching order products: ' . $e->getMessage());
    return $modx->lexicon('ms3_err_order_load');
}

$products = [];
$cart_count = 0;
$cart_discount_cost = 0;
foreach ($rows as $product) {
    $old_price = $product['original_price'] > $product['price']
        ? $product['original_price']
        : $product['old_price'];

    $discount_price = $old_price > 0 ? $old_price - $product['price'] : 0;

    $rawPrice = (float)$product['price'];
    $rawCost = (float)$product['cost'];
    $rawWeight = (float)$product['weight'];

    $oldPriceNum = (float)$old_price;
    $discountPriceNum = (float)$discount_price;
    $lineDiscountCostNum = (float)$product['count'] * $discountPriceNum;

    $product['old_price'] = $oldPriceNum;
    $product['price'] = $rawPrice;
    $product['cost'] = $rawCost;
    $product['weight'] = $rawWeight;
    $product['discount_price'] = $discountPriceNum;
    $product['discount_cost'] = $lineDiscountCostNum;

    // Pre-formatted fields with currency/unit for display in chunks
    $product['price_formatted'] = $ms3->format->price($rawPrice, true);
    $product['old_price_formatted'] = $oldPriceNum > 0 && $oldPriceNum > $rawPrice
        ? $ms3->format->price($oldPriceNum, true)
        : '';
    $product['cost_formatted'] = $ms3->format->price($rawCost, true);
    $product['old_cost_formatted'] = $oldPriceNum > 0 && $oldPriceNum > $rawPrice
        ? $ms3->format->price($product['count'] * $oldPriceNum, true)
        : '';
    $product['discount_price_formatted'] = $discountPriceNum > 0
        ? $ms3->format->price($discountPriceNum, true)
        : '';
    $product['discount_cost_formatted'] = $discountPriceNum > 0
        ? $ms3->format->price($lineDiscountCostNum, true)
        : '';
    $product['weight_formatted'] = $ms3->format->weightWithUnit($rawWeight);

    $product['id'] = (int)$product['id'];
    if (empty($product['name'])) {
        $product['name'] = $product['pagetitle'];
    } else {
        $product['pagetitle'] = $product['name'];
    }

    if (!empty($product['options']) && is_array($product['options'])) {
        foreach ($product['options'] as $option => $value) {
            $product['option.' . $option] = $value;
        }
    }

    try {
        $options = $modx->call(msProductOption::class, 'loadOptions', [$modx, $product['product_id']]);
        $products[] = array_merge($product, $options);
    } catch (\Exception $e) {
        $modx->log(modX::LOG_LEVEL_WARN, '[msGetOrder] Error loading options for product ' . $product['product_id'] . ': ' . $e->getMessage());
        $products[] = $product;
    }

    $cart_count += $product['count'];
    $cart_discount_cost += $product['count'] * $discount_price;
}

try {
    $pls = array_merge($scriptProperties, [
        'order' => $msOrder->toArray(),
        'products' => $products,
    //    'user' => ($tmp = $msOrder->getOne('User'))
    //        ? array_merge($tmp->getOne('Profile')->toArray(), $tmp->toArray())
    //        : [],
        'address' => ($tmp = $msOrder->getOne('Address'))
            ? $tmp->toArray()
            : [],
        'delivery' => ($tmp = $msOrder->getOne('Delivery'))
            ? $tmp->toArray()
            : [],
        'payment' => ($payment = $msOrder->getOne('Payment'))
            ? $payment->toArray()
            : [],
        'total' => [
            'cost' => (float)$msOrder->get('cost'),
            'cost_formatted' => $ms3->format->price($msOrder->get('cost'), true),
            'cart_cost' => (float)$msOrder->get('cart_cost'),
            'cart_cost_formatted' => $ms3->format->price($msOrder->get('cart_cost'), true),
            'delivery_cost' => (float)$msOrder->get('delivery_cost'),
            'delivery_cost_formatted' => $ms3->format->price($msOrder->get('delivery_cost'), true),
            'weight' => (float)$msOrder->get('weight'),
            'weight_formatted' => $ms3->format->weightWithUnit($msOrder->get('weight')),
            'cart_weight' => (float)$msOrder->get('weight'),
            'cart_weight_formatted' => $ms3->format->weightWithUnit($msOrder->get('weight')),
            'cart_count' => $cart_count,
            'cart_discount' => (float)$cart_discount_cost,
        ],
    ]);
} catch (\Exception $e) {
    $modx->log(modX::LOG_LEVEL_ERROR, '[msGetOrder] Error preparing order data: ' . $e->getMessage());
    return $modx->lexicon('ms3_err_order_load');
}

if ($payment) {
    $payStatusCsv = (string) $modx->getOption('payStatus', $scriptProperties, '1');
    $eligibleStatusIds = PaymentLinkResolver::parseEligibleStatusIds($payStatusCsv);

    /** @var PaymentLinkResolver $paymentLinkResolver */
    $paymentLinkResolver = $modx->services->get('ms3_payment_link_resolver');
    $orderStatus = $msOrder->getOne('Status');
    $link = $paymentLinkResolver->resolveForOrder($msOrder, $orderStatus, $eligibleStatusIds);

    if ($link) {
        $pls['payment_link'] = $link;
    }
}

try {
    $output = $pdoFetch->getChunk($tpl, $pls);
} catch (\Exception $e) {
    $modx->log(modX::LOG_LEVEL_ERROR, '[msGetOrder] Error rendering template: ' . $e->getMessage());
    return $modx->lexicon('ms3_err_order_load');
}

if ($modx->user->hasSessionContext('mgr') && !empty($showLog)) {
    $output .= '<pre class="msGetOrderLog">' . print_r($pdoFetch->getTime(), true) . '</pre>';
}

if (!empty($toPlaceholder)) {
    $modx->setPlaceholder($toPlaceholder, $output);
} else {
    return $output;
}
