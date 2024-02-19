<?php

use MiniShop3\MiniShop3;
use ModxPro\PdoTools\Fetch;
use MiniShop3\Model\msDelivery;
use MiniShop3\Model\msPayment;
use MiniShop3\Model\msDeliveryMember;

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

$ms3->order->initialize($token);
$order = $ms3->order->get();
$modx->log(1, print_r($order, 1));

// Do not show order form when displaying details of existing order
if (!empty($_GET['msorder'])) {
    return '';
}

$cost = $ms3->order->getCost();
$order['cost'] = $ms3->format->price($cost['data']['cost']);
$order['cart_cost'] = $ms3->format->price($cost['data']['cart_cost']);
$order['delivery_cost'] = $ms3->format->price($cost['data']['delivery_cost']);
$order['discount_cost'] = $ms3->format->price($cost['data']['discount_cost']);

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
if (!empty($scriptProperties['includeDeliveryFields'])) {
    $includeDeliveryKeys = array_map('trim', explode(',', $scriptProperties['includeDeliveryFields']));
    $includeDeliveryKeys = array_merge($includeDeliveryKeys, ['id']);

    if ($includeDeliveryKeys[0] === '*') {
        $select['msDelivery'] = $modx->getSelectColumns(msDelivery::class, '`msDelivery`', 'delivery_', ['id'], true);
    } else {
        $select['msDelivery'] = $modx->getSelectColumns(
            msDelivery::class,
            '`msDelivery`',
            'delivery_',
            $includeDeliveryKeys
        );
    }
}

if (!empty($scriptProperties['includePaymentFields'])) {
    $includePaymentKeys = array_map('trim', explode(',', $scriptProperties['includePaymentFields']));
    $includePaymentKeys = array_merge($includePaymentKeys, ['id']);

    if ($includePaymentKeys[0] === '*') {
        $select['msPayment'] = $modx->getSelectColumns(msPayment::class, '`msPayment`', 'payment_', ['id'], true);
    } else {
        $select['msPayment'] = $modx->getSelectColumns(
            msPayment::class,
            '`msPayment`',
            'payment_',
            $includePaymentKeys
        );
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
// Merge all properties and run!
$pdoFetch->setConfig(array_merge($default, $scriptProperties), false);
$rows = $pdoFetch->run();

$deliveries = $payments = [];
foreach ($rows as $row) {
    $delivery = [];
    $payment = [];
    foreach ($row as $key => $value) {
        if (strpos($key, 'delivery_') === 0) {
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

$outputData = [
    'order' => $order,
//    'form' => $form,
    'deliveries' => $deliveries,
    'payments' => $payments,
//    'errors' => $errors,
];

if ($scriptProperties['return'] === 'data') {
    return $outputData;
}
$output = $pdoFetch->getChunk($tpl, $outputData);

if ($modx->user->hasSessionContext('mgr') && !empty($showLog)) {
    $output .= '<pre class="msOrderLog">' . print_r($pdoFetch->getTime(), true) . '</pre>';
}

return $output;


//
//$cart = $ms3->cart->get();
//$order = $ms3->order->get();
//$status = $ms3->cart->status();
//if (empty($status['total_count'])) {
//    return '';
//}
//$cost = $ms3->order->getCost();
//$order['cost'] = $ms3->format->price($cost['data']['cost']);
//$order['cart_cost'] = $ms3->format->price($cost['data']['cart_cost']);
//$order['delivery_cost'] = $ms3->format->price($cost['data']['delivery_cost']);
//$order['discount_cost'] = $ms3->format->price($cost['data']['discount_cost']);
//
//// We need only active methods
//$where = [
//    'msDelivery.active' => true,
//    'msPayment.active' => true,
//];
//
//// Join payments to deliveries
//$leftJoin = [
//    'Payments' => [
//        'class' => 'msDeliveryMember',
//    ],
//    'msPayment' => [
//        'class' => 'msPayment',
//        'on' => 'Payments.payment_id = msPayment.id',
//    ],
//];
//
//// Select columns
//$select = [
//    'msDelivery' => $modx->getSelectColumns('msDelivery', 'msDelivery', 'delivery_'),
//    'msPayment' => $modx->getSelectColumns('msPayment', 'msPayment', 'payment_'),
//];
//
//// Add user parameters
//foreach (['where', 'leftJoin', 'select'] as $v) {
//    if (!empty($scriptProperties[$v])) {
//        $tmp = $scriptProperties[$v];
//        if (!is_array($tmp)) {
//            $tmp = json_decode($tmp, true);
//        }
//        if (is_array($tmp)) {
//            $$v = array_merge($$v, $tmp);
//        }
//    }
//    unset($scriptProperties[$v]);
//}
//$pdoFetch->addTime('Conditions prepared');
//
//// Default parameters
//$default = [
//    'class' => 'msDelivery',
//    'where' => $where,
//    'leftJoin' => $leftJoin,
//    'select' => $select,
//    'sortby' => 'msDelivery.rank asc, msPayment.rank',
//    'sortdir' => 'asc',
//    'limit' => 0,
//    'return' => 'data',
//    'nestedChunkPrefix' => 'ms3_',
//];
//// Merge all properties and run!
//$pdoFetch->setConfig(array_merge($default, $scriptProperties), false);
//$rows = $pdoFetch->run();
//
//$deliveries = $payments = [];
//foreach ($rows as $row) {
//    $delivery = [];
//    $payment = [];
//    foreach ($row as $key => $value) {
//        if (strpos($key, 'delivery_') === 0) {
//            $delivery[substr($key, 9)] = $value;
//        } else {
//            $payment[substr($key, 8)] = $value;
//        }
//    }
//
//    if (!isset($deliveries[$delivery['id']])) {
//        $delivery['payments'] = [];
//        $deliveries[$delivery['id']] = $delivery;
//    }
//    if (!empty($payment['id'])) {
//        $deliveries[$delivery['id']]['payments'][] = (int)$payment['id'];
//        if (!isset($payments[$payment['id']])) {
//            $payments[$payment['id']] = $payment;
//        }
//    }
//}
//
//$form = [];
//// Get user data
//$profile = [];
//if ($modx->user->isAuthenticated($modx->context->key)) {
//    $profile = array_merge($modx->user->Profile->toArray(), $modx->user->toArray());
//}
//$fields = [
//    'receiver' => 'fullname',
//    'phone' => 'phone',
//    'email' => 'email',
//    'comment' => 'extended[comment]',
//    'index' => 'zip',
//    'country' => 'country',
//    'region' => 'state',
//    'city' => 'city',
//    'street' => 'address',
//    'building' => 'extended[building]',
//    'room' => 'extended[room]',
//    'entrance' => 'extended[entrance]',
//    'floor' => 'extended[floor]',
//    'text_address' => 'extended[address]',
//];
//// Apply custom fields
//if (!empty($userFields)) {
//    if (!is_array($userFields)) {
//        $userFields = json_decode($userFields, true);
//    }
//    if (is_array($userFields)) {
//        $fields = array_merge($fields, $userFields);
//    }
//}
//// Set user fields
//foreach ($fields as $key => $value) {
//    if (!empty($profile) && !empty($value)) {
//        if (strpos($value, 'extended') !== false) {
//            $tmp = substr($value, 9, -1);
//            $value = !empty($profile['extended'][$tmp])
//                ? $profile['extended'][$tmp]
//                : '';
//        } else {
//            $value = $profile[$value];
//        }
//        $response = $ms3->order->add($key, $value);
//        if ($response['success'] && !empty($response['data'][$key])) {
//            $form[$key] = $response['data'][$key];
//        }
//    }
//    if (empty($form[$key]) && !empty($order[$key])) {
//        $form[$key] = $order[$key];
//        unset($order[$key]);
//    }
//}
//
//// Check for errors
//$errors = [];
//if (!empty($_POST)) {
//    $response = $ms3->order->getDeliveryRequiresFields();
//    if ($requires = $response['data']['requires']) {
//        foreach ($_POST as $field => $val) {
//            $validated = $ms3->order->validate($field, $val);
//            if ((in_array($field, $requires) && empty($validated))) {
//                $errors[] = $field;
//            }
//        }
//    }
//}
//
//$output = $pdoFetch->getChunk($tpl, [
//    'order' => $order,
//    'form' => $form,
//    'deliveries' => $deliveries,
//    'payments' => $payments,
//    'errors' => $errors,
//]);
//
//if ($modx->user->hasSessionContext('mgr') && !empty($showLog)) {
//    $output .= '<pre class="msOrderLog">' . print_r($pdoFetch->getTime(), true) . '</pre>';
//}
//
//return $output;
