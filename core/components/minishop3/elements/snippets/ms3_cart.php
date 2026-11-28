<?php

use MiniShop3\MiniShop3;
use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductData;
use MiniShop3\Model\msProductFile;
use MiniShop3\Model\msProductOption;
use MiniShop3\Model\msVendor;
use MiniShop3\Utils\ProductThumbnailJoin;
use MODX\Revolution\modX;
use ModxPro\PdoTools\Fetch;

/** @var modX $modx */
/** @var array $scriptProperties */
/** @var MiniShop3 $ms3 */

if (isset($_POST['render'])) {
    unset($_POST['render']);
}

if (!$modx->services->has('ms3')) {
    $modx->log(modX::LOG_LEVEL_ERROR, '[MiniShop3] Service not registered');
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

// hideOnThanks: when set to 1/true, this msCart call returns empty on the
// thanks page (detected by the ?msorder= URL parameter MS3 redirects to
// after a successful order). Default: false — cart renders normally even
// on the thanks page, so mini-cart blocks in the global layout keep showing
// the (typically empty) cart state.
$hideRaw = $modx->getOption('hideOnThanks', $scriptProperties, false);
$hideOnThanks = is_bool($hideRaw)
    ? $hideRaw
    : filter_var($hideRaw, FILTER_VALIDATE_BOOLEAN);
if ($hideOnThanks && !empty($_GET['msorder'])) {
    return '';
}

$ms3->cart->initialize($modx->context->key, $token);

// Cart output order comes from the cart itself, not from the query: rows are re-keyed
// by product id below and rendered by iterating $cart. &sortby/&sortdir were merged
// into the pdoFetch config and silently lost, so they are dropped with a warning
// instead of pretending to work (#800).
$ignoredSortProperties = [];
foreach (['sortby', 'sortdir'] as $ignoredSortProperty) {
    if (isset($scriptProperties[$ignoredSortProperty])) {
        $ignoredSortProperties[] = $ignoredSortProperty;
        unset($scriptProperties[$ignoredSortProperty]);
    }
}
if ($ignoredSortProperties !== []) {
    $modx->log(
        modX::LOG_LEVEL_WARN,
        '[MiniShop3] ms3_cart ignored ' . implode(', ', $ignoredSortProperties)
        . ': product order follows the cart, not the query (#800)'
    );
}

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

// Select cart products.
// Intentionally no CatalogResourceGroupVisibility filter here (#681 review):
// validateProduct() already gated add-to-cart; lines stay visible in the draft
// even if the product is closed later. Listing/resource cache staleness is #717.
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

$default = [
    'class' => msProduct::class,
    'where' => $where,
    'leftJoin' => $leftJoin,
    'select' => $select,
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
    $catalogRow = $rows[$entry['product_id']] ?? null;
    if ($catalogRow === null) {
        // The product was removed from the catalogue but its line is still in
        // the draft, and the footer follows cart status. Render the line from
        // the draft itself so visible rows and totals stay consistent (#861).
        // Keep product_key/cost/properties: the standard chunk uses them for
        // quantity/option change and remove forms.
        $product = $entry;
        // id=0 marks a stale line: the chunk must not link to a deleted resource.
        $product['id'] = 0;
        $product['product_id'] = $entry['product_id'];
        $product['product_key'] = $entry['product_key'] ?? $key;
        $product['name'] = $entry['name'] ?? '';
        $product['pagetitle'] = $entry['name'] ?? '';
        $product['old_price'] = $entry['properties']['old_price'] ?? 0;
        $catalogPrice = 0.0;
    } else {
        // Remember the catalogue price BEFORE merging: $entry overrides it
        // with the line price, making price > line price impossible (#861).
        $catalogPrice = (float) ($catalogRow['price'] ?? 0);
        $product = array_merge($catalogRow, $entry);
    }

    // Real lines keep their own old price in properties, not in a top-level
    // column, and MySQL DECIMAL returns '0.00' (which empty() treats as set).
    // Compare numerically so both cases behave the same (#861 review).
    $linePrice = (float) $entry['price'];
    $lineOldPrice = (float) ($entry['properties']['old_price'] ?? 0);
    $catalogOldPrice = (float) ($product['old_price'] ?? 0);
    // Mirror the order view: the current catalog price wins when it is above
    // the line price, otherwise keep the highest known old price. Never show
    // a struck-through price below the actual line price (#861 review).
    $candidates = [$lineOldPrice, $catalogOldPrice];
    if ($catalogPrice > $linePrice) {
        $candidates[] = $catalogPrice;
    }
    $old_price = max(0.0, ...$candidates);
    if ($old_price <= $linePrice) {
        $old_price = 0;
    }
    $discount_price = $old_price > $linePrice ? $old_price - $linePrice : 0;

    $lineWeight = (float)$entry['weight'];
    $oldPriceNum = (float)$old_price;
    $discountPriceNum = (float)$discount_price;

    $product['price'] = $linePrice;
    $product['weight'] = $lineWeight;
    $product['old_price'] = $oldPriceNum;
    $product['discount_price'] = $discountPriceNum;
    $product['discount_cost'] = (float)$entry['count'] * $discountPriceNum;

    // Pre-formatted fields with currency/unit for display in chunks
    $product['old_cost'] = $oldPriceNum > 0 ? (float)$entry['count'] * $oldPriceNum : 0.0;
    $product['price_formatted'] = $ms3->format->price($linePrice, true);
    $product['old_price_formatted'] = $oldPriceNum > 0 ? $ms3->format->price($oldPriceNum, true) : '';
    $product['cost_formatted'] = $ms3->format->price($entry['count'] * $linePrice, true);
    $product['old_cost_formatted'] = $oldPriceNum > 0 ? $ms3->format->price($entry['count'] * $oldPriceNum, true) : '';
    $product['discount_price_formatted'] = $discountPriceNum > 0 ? $ms3->format->price($discountPriceNum, true) : '';
    $product['discount_cost_formatted'] = $discountPriceNum > 0 ? $ms3->format->price($entry['count'] * $discountPriceNum, true) : '';
    $product['weight_formatted'] = $ms3->format->weightWithUnit($lineWeight);

    // Additional properties of product in cart
    if (!empty($entry['options']) && is_array($entry['options'])) {
        $product['options'] = $entry['options'];
        foreach ($entry['options'] as $option => $value) {
            $product['option_' . $option] = $value;
        }
    }

    // Add option values (deleted products have no catalog options to load)
    $options = $catalogRow !== null
        ? $modx->call(msProductOption::class, 'loadOptions', [$modx, $product['product_id']])
        : [];
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
