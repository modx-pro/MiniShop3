<?php

/**
 * Regression #889 (добивка): кабинет покупателя не отдаёт в чанки секреты
 * способа доставки.
 *
 * Страница заказа в кабинете кладёт в шаблон данные доставки. Оплату рядом
 * уже режет PaymentPublicFields, а у доставки в properties лежат ключи
 * интеграций. Штатный чанк печатает только name и description, но доступны
 * шаблону были все колонки.
 *
 * Запуск: php tests/CabinetSecretFieldsTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Services\Delivery\DeliveryPublicFields;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$path = dirname(__DIR__) . '/src/Services/Customer/OrdersPageService.php';
$source = is_readable($path) ? file_get_contents($path) : false;
if ($source === false) {
    $fail('cannot read OrdersPageService.php');
}

// Сырой массив доставки в шаблон кабинета больше не уезжает.
if (preg_match("/'delivery'\s*=>\s*\\\$delivery\s*\?\s*\\\$delivery->toArray\(\)/", $source)) {
    $fail('OrdersPageService must not pass the raw delivery array to cabinet chunks (#889)');
}

$deliveryKeys = preg_match_all("/'delivery'\s*=>/", $source);
if ($deliveryKeys < 1) {
    $fail('OrdersPageService: expected a delivery payload to guard');
}

$publicCalls = preg_match_all('/DeliveryPublicFields::fromEntityOrEmpty/', $source);
if ($publicCalls !== $deliveryKeys) {
    $fail(sprintf(
        'OrdersPageService: %d delivery payload(s) but %d go through DeliveryPublicFields (#889)',
        $deliveryKeys,
        $publicCalls
    ));
}

// Набор должен покрывать то, что печатает штатный чанк кабинета.
$chunk = dirname(__DIR__) . '/elements/chunks/ms3_customer_order_details.tpl';
$chunkSource = is_readable($chunk) ? file_get_contents($chunk) : false;
if ($chunkSource === false) {
    $fail('cannot read ms3_customer_order_details.tpl');
}

if (preg_match_all('/\$delivery\.([a-z_]+)/i', $chunkSource, $matches)) {
    foreach (array_unique($matches[1]) as $field) {
        if (!in_array($field, DeliveryPublicFields::FIELDS, true)) {
            $fail("ms3_customer_order_details.tpl prints \$delivery.{$field}, missing from DeliveryPublicFields::FIELDS");
        }
    }
}

fwrite(STDOUT, "OK CabinetSecretFieldsTest\n");
exit(0);
