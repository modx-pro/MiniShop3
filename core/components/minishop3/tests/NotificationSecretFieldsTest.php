<?php

/**
 * Regression #889: шаблон письма не должен видеть секреты.
 *
 * Сборка плейсхолдеров уведомления кладёт в шаблон данные заказа, покупателя и
 * доставки. В заказе это был token — тот же, которым витрина правит черновик;
 * у покупателя password и token; у доставки properties с ключами интеграций.
 * Штатные чанки их не печатают, но любой шаблон, выбранный в настройках
 * уведомления, может.
 *
 * Проверка статическая: сторожит сами списки и то, что в этих местах больше
 * нет сырого toArray().
 *
 * Запуск: php tests/NotificationSecretFieldsTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Notifications\Notification;
use MiniShop3\Services\Customer\CustomerPublicDto;
use MiniShop3\Services\Delivery\DeliveryPublicFields;
use MiniShop3\Services\Payment\PaymentPublicFields;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$srcDir = dirname(__DIR__) . '/src';

$read = static function (string $path) use ($fail): string {
    $content = is_readable($path) ? file_get_contents($path) : false;
    if ($content === false) {
        $fail('cannot read ' . $path);
    }

    return $content;
};

// --- Заказ: служебные колонки не уезжают в шаблон ---------------------------

$orderPrivate = (new ReflectionClass(Notification::class))->getConstant('ORDER_PRIVATE_FIELDS');
if (!is_array($orderPrivate)) {
    $fail('Notification::ORDER_PRIVATE_FIELDS must be an array');
}

foreach (['token', 'idempotency_key'] as $field) {
    if (!in_array($field, $orderPrivate, true)) {
        $fail("Notification::ORDER_PRIVATE_FIELDS must contain '{$field}' (#889)");
    }
}

$stripped = Notification::orderPublicFields([
    'id' => 1,
    'num' => '2610/1',
    'token' => 'secret-order-token',
    'uuid' => '90b663d7-03b3-469c-98cc-389562697e8e',
    'idempotency_key' => 'secret-key',
]);

foreach (['token', 'idempotency_key'] as $field) {
    if (array_key_exists($field, $stripped)) {
        $fail("orderPublicFields() must drop '{$field}'");
    }
}

// uuid покупатель и так получает в ссылке на страницу «заказ оформлен».
foreach (['id', 'num', 'uuid'] as $field) {
    if (!array_key_exists($field, $stripped)) {
        $fail("orderPublicFields() must keep '{$field}'");
    }
}

// --- Доставка: ключи интеграций и внутренности не уезжают -------------------

foreach (['properties', 'class', 'validation_rules'] as $field) {
    if (in_array($field, DeliveryPublicFields::FIELDS, true)) {
        $fail("DeliveryPublicFields::FIELDS must not contain '{$field}' (#889)");
    }
}

if (DeliveryPublicFields::fromEntityOrEmpty(null) !== []) {
    $fail('DeliveryPublicFields::fromEntityOrEmpty() must return [] for a non-entity');
}

// Набор держим таким же, как у оплаты: оба уходят в одни и те же шаблоны.
if (DeliveryPublicFields::FIELDS !== PaymentPublicFields::FIELDS) {
    $fail('DeliveryPublicFields::FIELDS should mirror PaymentPublicFields::FIELDS');
}

// --- Покупатель: ни пароля, ни токена --------------------------------------

$publicCustomerKeys = CustomerPublicDto::publicFieldKeys();
foreach (['password', 'token', 'user_id', 'customer_group_id'] as $field) {
    if (in_array($field, $publicCustomerKeys, true)) {
        $fail("CustomerPublicDto must not expose '{$field}'");
    }
}

// --- Сырого toArray() в этих местах больше нет ------------------------------

$notification = $read($srcDir . '/Notifications/Notification.php');

foreach (['$customer->toArray()', '$delivery->toArray()'] as $call) {
    if (str_contains($notification, $call)) {
        $fail("Notification::getPlaceholders() must not pass {$call} to templates (#889)");
    }
}

foreach (['CustomerPublicDto::fromCustomer', 'DeliveryPublicFields::fromEntityOrEmpty'] as $needle) {
    if (!str_contains($notification, $needle)) {
        $fail("Notification::getPlaceholders() must use {$needle} (#889)");
    }
}

// Получатель уходит в плагины и шаблоны каналов тем же путём.
$statusService = $read($srcDir . '/Services/Order/OrderStatusService.php');

if (str_contains($statusService, "\$recipient['customer'] = \$customer->toArray();")) {
    $fail('OrderStatusService must not put the raw customer array into the recipient (#889)');
}
if (!str_contains($statusService, 'CustomerPublicDto::fromCustomer')) {
    $fail('OrderStatusService must build the recipient customer from CustomerPublicDto (#889)');
}

fwrite(STDOUT, "OK NotificationSecretFieldsTest\n");
exit(0);
