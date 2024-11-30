<?php

use MiniShop3\MiniShop3;
use ModxPro\PdoTools\Fetch;
use MiniShop3\Model\msDelivery;
use MiniShop3\Model\msPayment;
use MiniShop3\Model\msDeliveryMember;

// Do not show order form when displaying details of existing order
if (!empty($_GET['msorder'])) {
    return '';
}

/** @var modX $modx */
/** @var array $scriptProperties */
/** @var MiniShop3 $ms3 */
$ms3 = $modx->services->get('ms3');
$ms3->initialize($modx->context->key);
if (!empty($_SESSION['ms3']) && !empty($_SESSION['ms3']['customer_token'])) {
    $token = $_SESSION['ms3']['customer_token'];
} else {
    $response = $ms3->customer->generateToken();
    $token = $response['data']['token'];
}

/** @var Fetch $pdoFetch */
$pdoFetch = $modx->services->get(Fetch::class);
$pdoFetch->addTime('pdoTools loaded.');

$tpl = $modx->getOption('tpl', $scriptProperties, 'tpl.msOrder');
$return = $modx->getOption('return', $scriptProperties, 'tpl');
$includeDeliveryFields = $modx->getOption('includeDeliveryFields', $scriptProperties, 'id');
$includeDeliveryKeys = array_map('trim', explode(',', $includeDeliveryFields));
$includeDeliveryKeys = array_unique(array_merge($includeDeliveryKeys, ['id']));
$includePaymentFields = $modx->getOption('includePaymentFields', $scriptProperties, '*');
$includePaymentKeys = array_map('trim', explode(',', $includePaymentFields));
$includePaymentKeys = array_unique(array_merge($includePaymentKeys, ['id']));
$includeCustomerAddresses = $modx->getOption('includeCustomerAddresses', $scriptProperties, true);

$ms3->order->initialize($token);
$response = $ms3->order->get();
$order = [];
if ($response['success']) {
    $order = $response['data']['order'];
}

$response = $ms3->order->getCost();
if ($response['success']) {
    $cost = $response['data'];
    $order['cost'] = $ms3->format->price($cost['cost']);
    $order['cart_cost'] = $ms3->format->price($cost['cart_cost']);
    $order['delivery_cost'] = $ms3->format->price($cost['delivery_cost']);
    $order['discount_cost'] = $ms3->format->price($cost['total_discount']);
}

// We need only active methods
$where = [
    'msDelivery.active' => true,
    'msPayment.active' => true,
];

// Join payments to deliveries
$leftJoin = [
    'Payments' => [
        'class' => msDeliveryMember::class,
    ],
    'msPayment' => [
        'class' => msPayment::class,
        'on' => 'Payments.payment_id = msPayment.id',
    ],
];

// Select columns
$select = [];

if ($includeDeliveryKeys[0] === '*') {
    $select['msDelivery'] = $modx->getSelectColumns(msDelivery::class, '`msDelivery`', 'delivery_');
} else {
    $select['msDelivery'] = $modx->getSelectColumns(
        msDelivery::class,
        '`msDelivery`',
        'delivery_',
        $includeDeliveryKeys
    );
}

if (!empty($scriptProperties['includePaymentFields'])) {
    $includePaymentKeys = array_map('trim', explode(',', $scriptProperties['includePaymentFields']));
    $includePaymentKeys = array_unique(array_merge($includePaymentKeys, ['id']));
}
if ($includePaymentKeys[0] === '*') {
    $select['msPayment'] = $modx->getSelectColumns(msPayment::class, '`msPayment`', 'payment_');
} else {
    $select['msPayment'] = $modx->getSelectColumns(
        msPayment::class,
        '`msPayment`',
        'payment_',
        $includePaymentKeys
    );
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

// Default parameters
$default = [
    'class' => msDelivery::class,
    'where' => $where,
    'leftJoin' => $leftJoin,
    'select' => $select,
    'sortby' => 'msDelivery.position asc, msPayment.position',
    'sortdir' => 'asc',
    'limit' => 0,
    'return' => 'data',
    'nestedChunkPrefix' => 'ms3_',
];
if ($scriptProperties['return'] === 'tpl') {
    unset($scriptProperties['return']);
}
// Merge all properties and run!
$pdoFetch->setConfig(array_merge($default, $scriptProperties), false);
$rows = $pdoFetch->run();
$deliveries = $payments = [];
foreach ($rows as $row) {
    $delivery = [];
    $payment = [];
    foreach ($row as $key => $value) {
        if (str_starts_with($key, 'delivery_')) {
            $delivery[substr($key, 9)] = $value;
        } else {
            $payment[substr($key, 8)] = $value;
        }
    }

    if (!isset($deliveries[$delivery['id']])) {
        $delivery['payments'] = [];
        $deliveries[$delivery['id']] = $delivery;
    }
    if (!empty($payment['id'])) {
        $deliveries[$delivery['id']]['payments'][] = (int)$payment['id'];
        if (!isset($payments[$payment['id']])) {
            $payments[$payment['id']] = $payment;
        }
    }
}

$addresses = [];
if (!empty($includeCustomerAddresses) && !empty($order['customer_id'])) {
    $addresses = $ms3->customer->getAddresses($order['customer_id']);
}

$form = [];
if (!empty($order['properties']['address_hash'])) {
    $form['address_hash'] = $order['properties']['address_hash'];
}
foreach ($order as $key => $value) {
    if (str_starts_with($key, 'address_')) {
        unset($order[$key]);
        $form[substr($key, 8)] = $value;
    }
}
//TODO здесь применить еще модель msCustomer
// Get user data
$profile = [];
if ($modx->user->isAuthenticated($modx->context->key)) {
    $profile = array_merge($modx->user->Profile->toArray(), $modx->user->toArray());
}
$fields = [
//    'receiver' => 'fullname',
//    'phone' => 'phone',
//    'email' => 'email',
    'address_comment' => 'extended[comment]',
    'index' => 'zip',
    'country' => 'country',
    'region' => 'state',
    'city' => 'city',
    'street' => 'address',
    'building' => 'extended[building]',
    'room' => 'extended[room]',
    'entrance' => 'extended[entrance]',
    'floor' => 'extended[floor]',
    'text_address' => 'extended[address]',
];
// Apply custom fields
if (!empty($userFields)) {
    if (!is_array($userFields)) {
        $userFields = json_decode($userFields, true);
    }
    if (is_array($userFields)) {
        $fields = array_merge($fields, $userFields);
    }
}
// Set user fields
foreach ($fields as $key => $value) {
    if (!empty($profile) && !empty($value)) {
        if (strpos($value, 'extended') !== false) {
            $tmp = substr($value, 9, -1);
            $value = !empty($profile['extended'][$tmp])
                ? $profile['extended'][$tmp]
                : '';
        } else {
            $value = $profile[$value];
        }
        //TODO здесь поля наверное нужно передавать в контроллер Customer
        $response = $ms3->order->add($key, $value);
        if ($response['success'] && !empty($response['data'][$key])) {
            $form[$key] = $response['data'][$key];
        }
    }
    if (empty($form[$key]) && !empty($order[$key])) {
        $form[$key] = $order[$key];
        unset($order[$key]);
    }
}

// Check for errors
$errors = [];
if (!empty($_POST)) {
    $response = $ms3->order->getDeliveryRequiresFields();
//    if ($requires = $response['data']['requires']) {
//        foreach ($_POST as $field => $val) {
//            $validated = $ms3->order->validate($field, $val);
//            if ((in_array($field, $requires) && empty($validated))) {
//                $errors[] = $field;
//            }
//        }
//    }
}

$outputData = [
    'order' => $order,
    'form' => $form,
    'deliveries' => $deliveries,
    'payments' => $payments,
    'errors' => $errors,
];

if (!empty($includeCustomerAddresses)) {
    $outputData['addresses'] = $addresses;
}

if ($return === 'data') {
    return $outputData;
}
$output = $pdoFetch->getChunk($tpl, $outputData);

if ($modx->user->hasSessionContext('mgr') && !empty($showLog)) {
    $output .= '<pre class="msOrderLog">' . print_r($pdoFetch->getTime(), true) . '</pre>';
}

return $output;
