<?php

use MiniShop3\MiniShop3;
use ModxPro\PdoTools\Fetch;

/** @var modX $modx */
/** @var array $scriptProperties */
/** @var MiniShop3 $ms3 */

$ms3 = $modx->services->get('ms3');
$ms3->initialize($modx->context->key);
$ms3->registerSnippet($scriptProperties, 'msOrderTotal');

if (!empty($_SESSION['ms3']) && !empty($_SESSION['ms3']['customer_token'])) {
    $token = $_SESSION['ms3']['customer_token'];
} else {
    $response = $ms3->customer->generateToken();
    $token = $response['data']['token'];
}

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

if ($return === 'data') {
    return $total;
}

$output = $pdoFetch->getChunk($tpl, $total);
return $output;
