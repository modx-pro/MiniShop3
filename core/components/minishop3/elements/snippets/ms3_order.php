<?php

use MiniShop3\MiniShop3;
use ModxPro\PdoTools\Fetch;
use MiniShop3\Model\msDelivery;
use MiniShop3\Model\msPayment;
use MiniShop3\Model\msDeliveryMember;

/** @var modX $modx */
/** @var array $scriptProperties */

// Do not show order form when displaying details of existing order
if (!empty($_GET['msorder'])) {
    return '';
}

if (!$modx->services->has('ms3')) {
    $modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[MiniShop3] Service not registered');
    return '';
}

/** @var MiniShop3 $ms3 */
$ms3 = $modx->services->get('ms3');
$ms3->initialize($modx->context->key);

/** @var \MiniShop3\Services\TokenService $tokenService */
$tokenService = $modx->services->get('ms3_token_service');
$token = $tokenService->resolveOrCreateToken();

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

// Check if customer is authenticated
$isCustomerAuth = !empty($_SESSION['ms3']['customer_id']);
$customerId = $isCustomerAuth ? (int)$_SESSION['ms3']['customer_id'] : 0;

$ms3->order->initialize($token);
$response = $ms3->order->get();
$order = [];
if ($response['success']) {
    $order = $response['data']['order'];
}

$response = $ms3->order->getCost();
if ($response['success']) {
    $cost = $response['data'];
    // Суммы в cost, cart_cost, delivery_cost и discount_cost — это строки с локальным форматом
    // (разделители тысяч, десятичный разделитель), как в стандартных чанках. Те же величины
    // как числа — в полях с суффиксом _numeric (удобно для |number в Fenom и арифметики).
    $order['cost'] = $ms3->format->price($cost['cost']);
    $order['cart_cost'] = $ms3->format->price($cost['cart_cost']);
    $order['delivery_cost'] = $ms3->format->price($cost['delivery_cost']);
    $order['discount_cost'] = $ms3->format->price($cost['total_discount']);
    $order['cost_numeric'] = (float)($cost['cost'] ?? 0);
    $order['cart_cost_numeric'] = (float)($cost['cart_cost'] ?? 0);
    $order['delivery_cost_numeric'] = (float)($cost['delivery_cost'] ?? 0);
    $order['discount_cost_numeric'] = (float)($cost['total_discount'] ?? 0);
    $order['cost_formatted'] = $ms3->format->price($cost['cost'], true);
    $order['cart_cost_formatted'] = $ms3->format->price($cost['cart_cost'], true);
    $order['delivery_cost_formatted'] = $ms3->format->price($cost['delivery_cost'], true);
    $order['discount_cost_formatted'] = $ms3->format->price($cost['total_discount'], true);
    $order['currency_symbol'] = $ms3->format->getCurrencySymbol();
}

// Check if cart is empty
$ms3->cart->initialize($modx->context->key, $token);
$cartStatus = $ms3->cart->status();
$isCartEmpty = !$cartStatus['success'] || empty($cartStatus['data']['total_count']);

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

// Translate delivery and payment names (if they are lexicon keys)
foreach ($deliveries as &$delivery) {
    if (str_starts_with($delivery['name'], 'ms3_')) {
        $delivery['name'] = $modx->lexicon($delivery['name']);
    }
}
unset($delivery);

foreach ($payments as &$payment) {
    if (str_starts_with($payment['name'], 'ms3_')) {
        $payment['name'] = $modx->lexicon($payment['name']);
    }
}
unset($payment);

// Load customer addresses for authenticated customers
$addresses = [];
if (!empty($includeCustomerAddresses) && $isCustomerAuth && $customerId > 0) {
    $addresses = $ms3->customer->getAddresses($customerId);
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

// Get msCustomer data (if authenticated)
$customerData = [];
if ($isCustomerAuth && $customerId > 0) {
    $msCustomer = $modx->getObject(\MiniShop3\Model\msCustomer::class, ['id' => $customerId]);

    if ($msCustomer && $msCustomer->get('is_active')) {
        $customerData = $msCustomer->toArray();

        $modx->log(
            modX::LOG_LEVEL_DEBUG,
            "[ms3_order] Auto-filling form for authenticated customer #{$customerId}"
        );
    }
}

// Determine data source based on sync setting
// If sync enabled: msCustomer and modUser are unified, use modUserProfile
// If sync disabled: msCustomer is independent, use only msCustomer data
$syncEnabled = (bool) $modx->getOption('ms3_customer_sync_enabled', null, false);

// Get modUser data (only used when sync is enabled)
$profile = [];
if ($syncEnabled && $modx->user->isAuthenticated($modx->context->key)) {
    $profile = array_merge($modx->user->Profile->toArray(), $modx->user->toArray());
}

// msCustomer fields mapping (simple 1:1 mapping)
$defaultCustomerFields = [
    'first_name' => 'first_name',
    'last_name' => 'last_name',
    'email' => 'email',
    'phone' => 'phone',
];

// Apply custom customerFields
if (!empty($customerFields)) {
    if (!is_array($customerFields)) {
        $customerFields = json_decode($customerFields, true);
    }
    if (is_array($customerFields)) {
        $defaultCustomerFields = array_merge($defaultCustomerFields, $customerFields);
    }
}

// modUser fields mapping (extended mapping with profile fields)
// Only used when sync is enabled
$userProfileFields = [
    'first_name' => 'fullname',
    'phone' => 'phone',
    'email' => 'email',
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

// Apply custom userFields (only when sync is enabled)
if ($syncEnabled && !empty($userFields)) {
    if (!is_array($userFields)) {
        $userFields = json_decode($userFields, true);
    }
    if (is_array($userFields)) {
        $userProfileFields = array_merge($userProfileFields, $userFields);
    }
}

// Apply data based on sync mode
if ($syncEnabled) {
    // Sync enabled: use modUserProfile as source of truth
    foreach ($userProfileFields as $key => $value) {
        if (!empty($profile) && !empty($value)) {
            if (strpos($value, 'extended') !== false) {
                $tmp = substr($value, 9, -1);
                $value = !empty($profile['extended'][$tmp])
                    ? $profile['extended'][$tmp]
                    : '';
            } else {
                $value = $profile[$value] ?? '';
            }
            if (!empty($value)) {
                $response = $ms3->order->add($key, $value);
                if ($response['success'] && !empty($response['data'][$key])) {
                    $form[$key] = $response['data'][$key];
                }
            }
        }
        if (empty($form[$key]) && !empty($order[$key])) {
            $form[$key] = $order[$key];
            unset($order[$key]);
        }
    }
} else {
    // Sync disabled: use msCustomer as source of truth
    if (!empty($customerData)) {
        foreach ($defaultCustomerFields as $orderField => $customerField) {
            if (!empty($customerData[$customerField]) && empty($form[$orderField])) {
                $response = $ms3->order->add($orderField, $customerData[$customerField]);
                if ($response['success'] && !empty($response['data'][$orderField])) {
                    $form[$orderField] = $response['data'][$orderField];
                }
            }
        }
    }
}

// Apply remaining order data to form
foreach ($userProfileFields as $key => $value) {
    if (empty($form[$key]) && !empty($order[$key])) {
        $form[$key] = $order[$key];
        unset($order[$key]);
    }
}

$outputData = [
    'order' => $order,
    'form' => $form,
    'deliveries' => $deliveries,
    'payments' => $payments,
    'isCustomerAuth' => $isCustomerAuth,
    'isCartEmpty' => $isCartEmpty,
];

if (!empty($includeCustomerAddresses)) {
    $outputData['addresses'] = $addresses;
}

// Include JS module for customer addresses (SSR approach)
if ($isCustomerAuth && !empty($includeCustomerAddresses)) {
    $assetsUrl = $modx->getOption('ms3_assets_url', null, $modx->getOption('assets_url') . 'components/minishop3/');
    $modx->regClientStartupScript($assetsUrl . 'js/web/order-addresses.js');
}

if ($return === 'data') {
    return $outputData;
}
$output = $pdoFetch->getChunk($tpl, $outputData);

if ($modx->user->hasSessionContext('mgr') && !empty($showLog)) {
    $output .= '<pre class="msOrderLog">' . print_r($pdoFetch->getTime(), true) . '</pre>';
}

return $output;
