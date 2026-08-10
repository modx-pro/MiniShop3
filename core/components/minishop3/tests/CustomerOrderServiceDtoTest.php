<?php

/**
 * Static checks for CustomerOrderService list params and public DTO shape.
 *
 * Run: php tests/CustomerOrderServiceDtoTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Services\Customer\CustomerOrderService;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$assertSame = static function ($expected, $actual, string $case) use ($fail): void {
    if ($actual !== $expected) {
        $fail($case . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
};

$assertTrue = static function (bool $cond, string $case) use ($fail): void {
    if (!$cond) {
        $fail($case);
    }
};

// --- normalizeListParams ---

$defaults = CustomerOrderService::normalizeListParams([]);
$assertSame(CustomerOrderService::DEFAULT_LIMIT, $defaults['limit'], 'default limit');
$assertSame(0, $defaults['offset'], 'default offset');
$assertSame(null, $defaults['status_id'], 'default status');

$capped = CustomerOrderService::normalizeListParams(['limit' => 999, 'offset' => -5, 'status' => 0]);
$assertSame(CustomerOrderService::MAX_LIMIT, $capped['limit'], 'limit capped at MAX');
$assertSame(0, $capped['offset'], 'negative offset → 0');
$assertSame(null, $capped['status_id'], 'status 0 ignored');

$draftFilter = CustomerOrderService::normalizeListParams(
    ['status' => CustomerOrderService::DEFAULT_DRAFT_STATUS_ID],
    CustomerOrderService::DEFAULT_DRAFT_STATUS_ID
);
$assertSame(null, $draftFilter['status_id'], 'draft status filter ignored');

$customDraft = CustomerOrderService::normalizeListParams(['status' => 9], 9);
$assertSame(null, $customDraft['status_id'], 'custom draft id ignored as filter');

$statusWhenDraftIsNine = CustomerOrderService::normalizeListParams(['status' => 1], 9);
$assertSame(1, $statusWhenDraftIsNine['status_id'], 'status 1 allowed when draft is 9');

$valid = CustomerOrderService::normalizeListParams(['limit' => 10, 'offset' => 20, 'status' => 3]);
$assertSame(10, $valid['limit'], 'custom limit');
$assertSame(20, $valid['offset'], 'custom offset');
$assertSame(3, $valid['status_id'], 'status filter');

// --- buildPublicOrderSummary: no internals ---

$dto = CustomerOrderService::buildPublicOrderSummary(
    [
        'id' => 42,
        'uuid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
        'num' => '2601/1',
        'createdon' => '2026-07-17 12:00:00',
        'updatedon' => '2026-07-17 12:05:00',
        'cost' => 1500.5,
        'cart_cost' => 1400,
        'delivery_cost' => 100.5,
        'weight' => 1.2,
        'status_id' => 2,
        'delivery_id' => 1,
        'payment_id' => 2,
        'context' => 'web',
        'order_comment' => 'leave at door',
        'token' => 'secret-token-must-not-leak',
        'properties' => ['internal' => true],
        'user_id' => 99,
        'customer_id' => 7,
    ],
    ['name' => 'New', 'color' => '#00aa00'],
    true
);

$assertSame(42, $dto['id'], 'dto id');
$assertSame('2601/1', $dto['num'], 'dto num');
$assertSame(1500.5, $dto['cost'], 'dto cost');
$assertSame('New', $dto['status_name'], 'dto status_name');
$assertSame('#00aa00', $dto['status_color'], 'dto status_color');
$assertSame(true, $dto['can_cancel'], 'dto can_cancel');
$assertTrue(!array_key_exists('token', $dto), 'token must not be in DTO');
$assertTrue(!array_key_exists('properties', $dto), 'properties must not be in DTO');
$assertTrue(!array_key_exists('user_id', $dto), 'user_id must not be in DTO');
$assertTrue(!array_key_exists('customer_id', $dto), 'customer_id must not be in DTO');

fwrite(STDOUT, "OK CustomerOrderServiceDtoTest\n");
exit(0);
