<?php

use MiniShop3\MiniShop3;

/** @var modX $modx */
/** @var array $scriptProperties */
/** @var MiniShop3 $ms3 */
$ms3 = $modx->services->get('ms3');
$ms3->initialize($modx->context->key);

$tpl = $modx->getOption('tpl', $scriptProperties, 'tpl.msCustomerForm');
if (!empty($_SESSION['ms3']) && !empty($_SESSION['ms3']['customer_token'])) {
    $token = $_SESSION['ms3']['customer_token'];
} else {
    $response = $ms3->customer->generateToken();
    $token = $response['data']['token'];
}

$validationRules = $modx->getOption('validationRules', $scriptProperties);

$ms3->customer->initialize($token);
$ms3->customer->registerValidation($validationRules);
$customer = $ms3->customer->get();
$errors = [];
$form = [];

foreach ($customer as $key => $value) {
    switch ($key) {
        case 'id':
        case 'token':
        case 'user_id':
            break;
        default:
            $form[$key] = $value;
    }
}

return $ms3->pdoFetch->getChunk($tpl, compact('form', 'errors'));
