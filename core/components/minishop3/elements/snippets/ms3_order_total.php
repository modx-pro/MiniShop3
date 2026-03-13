<?php

use MiniShop3\MiniShop3;
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
$ms3->registerSnippet($scriptProperties, 'msOrderTotal');

/** @var \MiniShop3\Services\TokenService $tokenService */
$tokenService = $modx->services->get('ms3_token_service');
$token = $tokenService->resolveOrCreateToken();

/** @var Fetch $pdoFetch */
$pdoFetch = $modx->services->get(Fetch::class);
$pdoFetch->addTime('pdoTools loaded.');

$tpl = $modx->getOption('tpl', $scriptProperties, 'tpl.msOrderTotal');
$return = $modx->getOption('return', $scriptProperties, 'tpl');

$ms3->order->initialize($token);
$response = $ms3->order->getCost();

$total = [
    'cost' => 0,
    'cart_cost' => 0,
    'delivery_cost' => 0,
    'payment_cost' => 0,
    'total_count' => 0,
    'total_cost' => 0,
    'total_weight' => 0,
    'total_discount' => 0,
    'total_positions' => 0
];

if ($response['success']) {
    $total = array_merge($total, $response['data']);
}

if (!empty($scriptProperties['formatPrices'])) {
    $withCurrency = !empty($scriptProperties['withCurrency']);
    $total['cost'] = $ms3->format->price($total['cost'], $withCurrency);
    $total['cart_cost'] = $ms3->format->price($total['cart_cost'], $withCurrency);
    $total['delivery_cost'] = $ms3->format->price($total['delivery_cost'], $withCurrency);
    $total['payment_cost'] = $ms3->format->price($total['payment_cost'], $withCurrency);
    $total['total_cost'] = $ms3->format->price($total['total_cost'], $withCurrency);
    $total['total_discount'] = $ms3->format->price($total['total_discount'], $withCurrency);
    $total['total_weight'] = $ms3->format->weight($total['total_weight']);
}

// Pre-formatted fields with currency/unit — always available for chunks
$total['cost_formatted'] = $ms3->format->price($response['success'] ? $response['data']['cost'] ?? 0 : 0, true);
$total['cart_cost_formatted'] = $ms3->format->price($response['success'] ? $response['data']['cart_cost'] ?? 0 : 0, true);
$total['delivery_cost_formatted'] = $ms3->format->price($response['success'] ? $response['data']['delivery_cost'] ?? 0 : 0, true);
$total['payment_cost_formatted'] = $ms3->format->price($response['success'] ? $response['data']['payment_cost'] ?? 0 : 0, true);
$total['total_cost_formatted'] = $ms3->format->price($response['success'] ? $response['data']['total_cost'] ?? 0 : 0, true);
$total['total_discount_formatted'] = $ms3->format->price($response['success'] ? $response['data']['total_discount'] ?? 0 : 0, true);
$total['total_weight_formatted'] = $ms3->format->weightWithUnit($response['success'] ? $response['data']['total_weight'] ?? 0 : 0);

if ($return === 'data') {
    return $total;
}

$output = $pdoFetch->getChunk($tpl, $total);
return $output;
