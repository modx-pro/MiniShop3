<?php

/**
 * Regression #909: ms3_get_order сниппет не отдаёт в чанки секреты
 * способа доставки и служебные колонки заказа.
 *
 * В `ms3_get_order.php`:
 * - delivery должен идти через DeliveryPublicFields::fromEntityOrEmpty()
 * - order не должен содержать token и idempotency_key
 * - оплата уже через PaymentPublicFields (уже режет)
 *
 * Запуск: php tests/SnippetSecretFieldsTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Services\Delivery\DeliveryPublicFields;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$snippetPath = dirname(__DIR__) . '/elements/snippets/ms3_get_order.php';
$snippetSrc = is_readable($snippetPath) ? file_get_contents($snippetPath) : false;
if ($snippetSrc === false) {
    $fail('cannot read ms3_get_order.php');
}

// 1. Delivery не должен быть сырой toArray()
if (preg_match("/'delivery'\s*=>\s*\\\$tmp\s*\?\s*\\\$tmp->toArray\(\)/", $snippetSrc)) {
    $fail('ms3_get_order.php must not pass raw delivery array to template via ->toArray() (#909)');
}

// 2. Delivery должен идти через DeliveryPublicFields::fromEntityOrEmpty
if (!str_contains($snippetSrc, 'DeliveryPublicFields::fromEntityOrEmpty')) {
    $fail('ms3_get_order.php must use DeliveryPublicFields::fromEntityOrEmpty for delivery (#909)');
}

// 3. Order array — убраны token и idempotency_key (включая копию в properties)
if (!str_contains($snippetSrc, "unset(\$orderArray['token'], \$orderArray['idempotency_key'])")
    && !str_contains($snippetSrc, "unset(\$orderArray[\"token\"], \$orderArray[\"idempotency_key\"])")) {
    $fail('ms3_get_order.php must unset token and idempotency_key from order array (#909)');
}
if (!str_contains($snippetSrc, "unset(\$orderArray['properties']['idempotency_key'])")) {
    $fail('ms3_get_order.php must also unset the properties copy of idempotency_key (#909)');
}

// 4. Проверка набор полей DeliveryPublicFields покрывает штатный чанк ms3_get_order.tpl
$chunk = dirname(__DIR__) . '/elements/chunks/ms3_get_order.tpl';
$chunkSource = is_readable($chunk) ? file_get_contents($chunk) : false;
if ($chunkSource === false) {
    $fail('cannot read ms3_get_order.tpl');
}

if (preg_match_all('/\$delivery\.([a-z_]+)/i', $chunkSource, $matches)) {
    foreach (array_unique($matches[1]) as $field) {
        if (!in_array($field, DeliveryPublicFields::FIELDS, true)) {
            $fail("ms3_get_order.tpl prints \$delivery.{$field}, missing from DeliveryPublicFields::FIELDS");
        }
    }
}

// 5. Проверка CustomerOrderService::formatDeliveryOrPayment использует DeliveryPublicFields / PaymentPublicFields
$apiPath = dirname(__DIR__) . '/src/Services/Customer/CustomerOrderService.php';
$apiSrc = is_readable($apiPath) ? file_get_contents($apiPath) : false;
if ($apiSrc === false) {
    $fail('cannot read CustomerOrderService.php');
}

// formatDeliveryOrPayment должен использовать DeliveryPublicFields / PaymentPublicFields
if (!str_contains($apiSrc, 'DeliveryPublicFields::fromEntityOrEmpty')
    || !str_contains($apiSrc, 'PaymentPublicFields::fromEntityOrEmpty')) {
    $fail('CustomerOrderService::formatDeliveryOrPayment must use DeliveryPublicFields/PaymentPublicFields (#909)');
}

// formatDeliveryOrPayment НЕ должен содержать ручной маппинг полей (id, name, description, price, logo) напрямую
// (кроме fallback-ветки для неизвестных типов)
// Упрощённая проверка: если есть 'price' => $entity->get('price'), это старый код
if (preg_match("/'price'\s*=>\s*\\\$entity->get\('price'\)/", $apiSrc)) {
    // Проверяем, что это внутри fallback (после слова Fallback)
    if (!preg_match('/Fallback.*' . "'price'\s*=>\s*\\\$entity->get\('price'\)" . '/s', $apiSrc)) {
        $fail('CustomerOrderService::formatDeliveryOrPayment has manual field mapping outside fallback (#909)');
    }
}

fwrite(STDOUT, "OK SnippetSecretFieldsTest\n");
exit(0);
