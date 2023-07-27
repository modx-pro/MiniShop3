<?php

use MiniShop3\MiniShop3;

/** @var modX $modx */
/** @var array $scriptProperties */
/** @var MiniShop3 $ms3 */
$ms3 = $modx->services->get('ms3');
$ms3->initialize($modx->context->key);

$tpl = $modx->getOption('tpl', $scriptProperties, 'tpl.msMiniCart');

$cart = $ms3->cart->status();
$cart['total_cost'] = $ms3->format->price($cart['total_cost']);
$cart['total_weight'] = $ms3->format->weight($cart['total_weight']);
$cart['total_discount'] = $ms3->format->price($cart['total_discount']);

return $ms3->pdoFetch->getChunk($tpl, $cart);
