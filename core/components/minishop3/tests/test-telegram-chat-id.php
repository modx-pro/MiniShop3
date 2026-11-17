<?php
/**
 * Test script for issue #900: telegram_chat_id read from msCustomer for guest customers.
 *
 * Usage: php test-telegram-chat-id.php
 */

declare(strict_types=1);

// Bootstrap MODX (adjust path if needed)
require_once dirname(__DIR__, 4) . '/index.php';

if (!($modx instanceof modX)) {
    echo "ERROR: MODX not initialized\n";
    exit(1);
}

$modx->setLogLevel(modX::LOG_LEVEL_INFO);
$modx->setLogTarget('ECHO');

echo "=== Testing Issue #900: telegram_chat_id for guest customers ===\n\n";

// 1. Create a test guest customer (no user_id)
echo "1. Creating guest customer with telegram_chat_id...\n";

/** @var \MiniShop3\Model\msCustomer|null $customer */
$customer = $modx->newObject(\MiniShop3\Model\msCustomer::class);
if (!$customer) {
    echo "ERROR: Cannot create msCustomer object\n";
    exit(1);
}

$telegramChatId = '123456789';
$customer->fromArray([
    'user_id' => null, // Guest customer (no linked modUser)
    'email' => 'guest-' . time() . '@test.com',
    'phone' => '+79991234567',
    'first_name' => 'Guest',
    'last_name' => 'Test',
    'telegram_chat_id' => $telegramChatId,
    'is_active' => true,
]);

if (!$customer->save()) {
    echo "ERROR: Failed to save customer\n";
    exit(1);
}

$customerId = (int)$customer->get('id');
echo "   Customer ID: {$customerId}\n";
echo "   telegram_chat_id saved: " . $customer->get('telegram_chat_id') . "\n\n";

// 2. Create a test order for this customer
echo "2. Creating test order for guest customer...\n";

/** @var \MiniShop3\Model\msOrder|null $order */
$order = $modx->newObject(\MiniShop3\Model\msOrder::class);
if (!$order) {
    echo "ERROR: Cannot create msOrder object\n";
    exit(1);
}

$order->fromArray([
    'customer_id' => $customerId,
    'user_id' => null, // No linked user
    'status_id' => 1,
    'cost' => 1000,
    'cart_cost' => 1000,
    'delivery_cost' => 0,
    'weight' => 1,
    'created_at' => date('Y-m-d H:i:s'),
]);

if (!$order->save()) {
    echo "ERROR: Failed to save order\n";
    exit(1);
}

$orderId = (int)$order->get('id');
echo "   Order ID: {$orderId}\n\n";

// 3. Test OrderStatusService::getCustomerRecipient
echo "3. Testing OrderStatusService->getCustomerRecipient()...\n";

$ms3 = $modx->services->get('ms3');
if (!$ms3) {
    echo "ERROR: ms3 service not available\n";
    exit(1);
}

// Load the map to ensure telegram_chat_id field is known
$ms3->loadMap();

// Refresh order object with relationships
/** @var \MiniShop3\Model\msOrder $order */
$order = $modx->getObject(\MiniShop3\Model\msOrder::class, $orderId);
$order->getOne('Customer');

// Use reflection to call protected method
$serviceClass = \MiniShop3\Services\Order\OrderStatusService::class;
$reflector = new ReflectionClass($serviceClass);
$method = $reflector->getMethod('getCustomerRecipient');
$method->setAccessible(true);

// We need to instantiate the service properly
$orderStatusService = $modx->services->get('ms3_order_status_service');
if (!$orderStatusService) {
    echo "WARNING: ms3_order_status_service not available, creating manually...\n";
    $orderStatusService = new \MiniShop3\Services\Order\OrderStatusService($modx, $ms3);
}

$recipient = $method->invoke($orderStatusService, $order);

if (!is_array($recipient)) {
    echo "ERROR: getCustomerRecipient returned non-array\n";
    exit(1);
}

echo "   Recipient type: " . ($recipient['type'] ?? 'unknown') . "\n";
echo "   Email: " . ($recipient['email'] ?? 'null') . "\n";
echo "   Phone: " . ($recipient['phone'] ?? 'null') . "\n";
echo "   telegram_chat_id: " . ($recipient['telegram_chat_id'] ?? 'null') . "\n";

if (empty($recipient['telegram_chat_id'])) {
    echo "\n❌ FAILED: telegram_chat_id is empty for guest customer!\n";
    exit(1);
}

if ($recipient['telegram_chat_id'] !== $telegramChatId) {
    echo "\n❌ FAILED: telegram_chat_id mismatch! Expected {$telegramChatId}, got {$recipient['telegram_chat_id']}\n";
    exit(1);
}

echo "\n✅ SUCCESS: telegram_chat_id correctly read from msCustomer for guest customer!\n\n";

// 4. Cleanup
echo "4. Cleaning up test data...\n";
$order->remove();
$customer->remove();
echo "   Test order and customer removed.\n\n";

echo "=== Test completed successfully ===\n";
exit(0);
