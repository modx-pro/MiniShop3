<?php

/**
 * GridColumnTypeValidator smoke (Phase A #365).
 *
 * Run: php tests/GridColumnTypeValidatorTest.php
 */

declare(strict_types=1);

require __DIR__ . '/stubs/ModxStub.php';
require __DIR__ . '/stubs/GridValidatorModxStub.php';
require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Services\Grid\GridColumnTypeValidator;
use MiniShop3\Tests\Stubs\GridValidatorModxStub;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$assertTrue = static function (bool $cond, string $case) use ($fail): void {
    if (!$cond) {
        $fail($case);
    }
};

$validator = new GridColumnTypeValidator(new GridValidatorModxStub());

$failTemplate = $validator->validateForType('template', []);
$assertTrue(($failTemplate['success'] ?? true) === false, 'template empty fails');

$okTemplate = $validator->validateForType('template', ['template' => '{{name}}']);
$assertTrue(($okTemplate['success'] ?? false) === true, 'template string ok');

$failOption = $validator->validateForType('option', []);
$assertTrue(($failOption['success'] ?? true) === false, 'option empty fails');

$okOption = $validator->validateForType('option', ['option' => ['key' => 'color']], 'option_color');
$assertTrue(($okOption['success'] ?? false) === true, 'option ok');

$collision = $validator->validateForType('option', ['option' => ['key' => 'color']], 'price');
$assertTrue(($collision['success'] ?? true) === false, 'option field name collision');

$dupActions = $validator->validateForType('actions', [
    'actions' => [
        ['name' => 'edit', 'handler' => 'edit'],
        ['name' => 'edit', 'handler' => 'delete'],
    ],
]);
$assertTrue(($dupActions['success'] ?? true) === false, 'duplicate action name fails');

$okActions = $validator->validateForType('actions', [
    'actions' => [
        ['name' => 'edit', 'handler' => 'edit'],
        ['name' => 'delete', 'handler' => 'delete'],
    ],
]);
$assertTrue(($okActions['success'] ?? false) === true, 'actions ok');

$modelOk = $validator->validateForType('model', []);
$assertTrue(($modelOk['success'] ?? false) === true, 'model type always ok');

$failRelation = $validator->validateForType('relation', []);
$assertTrue(($failRelation['success'] ?? true) === false, 'relation empty fails');

$okRelation = $validator->validateForType('relation', [
    'relation' => [
        'table' => 'ms3_orders',
        'foreignKey' => 'id',
        'displayField' => 'num',
    ],
]);
$assertTrue(($okRelation['success'] ?? false) === true, 'relation table ok');
$assertTrue(
    ($okRelation['config']['relation']['resolvedTableName'] ?? '') === 'modx_ms3_orders',
    'relation resolvedTableName gets table_prefix'
);

$failAgg = $validator->validateForType('relation', [
    'relation' => [
        'table' => 'ms3_orders',
        'foreignKey' => 'id',
        'displayField' => 'num',
        'aggregation' => 'MEDIAN',
    ],
]);
$assertTrue(($failAgg['success'] ?? true) === false, 'invalid aggregation fails');

fwrite(STDOUT, "OK: GridColumnTypeValidatorTest\n");
exit(0);
