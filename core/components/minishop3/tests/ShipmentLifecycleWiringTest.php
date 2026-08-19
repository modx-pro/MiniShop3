<?php

/**
 * Wiring: shipment lifecycle stays opt-in and off checkout (#591).
 *
 * Run: php tests/ShipmentLifecycleWiringTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$root = dirname(__DIR__);
$read = static function (string $relative) use ($root, $fail): string {
    $path = $root . '/' . $relative;
    $src = file_get_contents($path);
    if ($src === false) {
        $fail('unable to read ' . $relative);
    }

    return $src;
};

$web = $read('config/routes/web.php');
if (!str_contains($web, "post('/webhook/{delivery_id}'")) {
    $fail('web.php missing POST /delivery/webhook/{delivery_id}');
}
if (!str_contains($web, 'DeliveryWebhookController')) {
    $fail('web.php missing DeliveryWebhookController');
}
if (preg_match(
    "/group\\('\\/delivery'\\s*,\\s*function.*?\\}\\s*,\\s*\\[\\s*\\\$tokenMiddleware/s",
    $web
)) {
    $fail('delivery group must not use TokenMiddleware');
}

$token = $read('src/Middleware/TokenMiddleware.php');
if (!str_contains($token, "'/api/v1/delivery/webhook/'")) {
    $fail('TokenMiddleware must list /api/v1/delivery/webhook/ as public');
}

$registry = $read('src/ServiceRegistry.php');
$factories = $read('src/ServiceRegistryFactories.php');
if (!str_contains($registry, "'ms3_shipment_lifecycle'")) {
    $fail('ServiceRegistry missing ms3_shipment_lifecycle');
}
if (!str_contains($factories, "'ms3_shipment_lifecycle'")) {
    $fail('ServiceRegistryFactories missing ms3_shipment_lifecycle');
}
if (!str_contains($factories, 'PdoShipmentStore')) {
    $fail('shipment factory must use PdoShipmentStore');
}

$lifecycle = $read('src/Services/Shipment/ShipmentLifecycleService.php');
if (str_contains($lifecycle, "->set('status_id'")) {
    $fail('ShipmentLifecycleService must not write order status_id');
}
if (!str_contains($lifecycle, 'OrderStatusService')) {
    $fail('ShipmentLifecycleService must use OrderStatusService');
}

$submit = $read('src/Services/Order/OrderSubmitHandler.php');
if (str_contains($submit, 'ms3_shipment_lifecycle') || str_contains($submit, 'ShipmentLifecycle')) {
    $fail('OrderSubmitHandler must not create shipments');
}

$provider = $read('src/Controllers/Delivery/DeliveryProviderInterface.php');
if (!str_contains($provider, 'function getCost(')) {
    $fail('DeliveryProviderInterface must keep getCost');
}
if (str_contains($provider, 'createShipment') || str_contains($provider, 'verifyWebhook')) {
    $fail('DeliveryProviderInterface must stay cost-only');
}

$default = $read('src/Controllers/Delivery/DefaultDelivery.php');
if (str_contains($default, 'ShipmentProviderInterface')) {
    $fail('DefaultDelivery must stay cost-only');
}

$dto = $read('src/Services/Shipment/ShipmentPublicDto.php');
foreach (['meta', 'provider', 'external_id', 'properties'] as $secret) {
    if (str_contains($dto, "'{$secret}'")) {
        $fail("ShipmentPublicDto must not expose {$secret}");
    }
}

$cabinet = $read('src/Services/Customer/CustomerOrderService.php');
if (!str_contains($cabinet, "'shipments'")) {
    $fail('CustomerOrderService must expose shipments[]');
}

$settings = $read('../../../_build/elements/settings.php');
if (!str_contains($settings, "'ms3_shipment_enabled'")) {
    $fail('settings.php missing ms3_shipment_enabled');
}
if (!str_contains($settings, "'ms3_status_sent'")) {
    $fail('settings.php missing ms3_status_sent');
}

$migration = $read('migrations/20260819150000_create_shipments.php');
if (!str_contains($migration, 'uniq_shipment_order')) {
    $fail('migration must unique-index order_id');
}

fwrite(STDOUT, "OK ShipmentLifecycleWiringTest\n");
exit(0);
