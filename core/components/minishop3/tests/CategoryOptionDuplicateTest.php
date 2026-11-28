<?php

/**
 * #881: copying parent category options must be idempotent and must not fatal
 * when the category service is not registered in DI.
 *
 * Run: php tests/CategoryOptionDuplicateTest.php
 */

declare(strict_types=1);

$root = dirname(__DIR__, 4);
$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$service = (string) file_get_contents($root . '/core/components/minishop3/src/Services/Category/CategoryService.php');
if (!str_contains($service, 'public function duplicateCategoryOptions(')) {
    $fail('duplicateCategoryOptions must be public for the Create processor (#881)');
}
if (!str_contains($service, "'option_id' => \$optionId")) {
    $fail('Existing (category_id, option_id) links must be skipped (#881)');
}
if (!str_contains($service, 'beginTransaction()') || !str_contains($service, 'rollBack()')) {
    $fail('Option duplication must use a transaction (#881)');
}

$create = (string) file_get_contents($root . '/core/components/minishop3/src/Processors/Category/Create.php');
if (!str_contains($create, "services->has('ms3_category_service')")) {
    $fail('Create processor must guard the DI lookup (#881)');
}
if (!str_contains($create, 'new CategoryService(')) {
    $fail('Create processor must fall back when ms3 bootstrap did not register the service (#881)');
}
if (str_contains($create, "runProcessor('MiniShop3\\\\Processors\\\\Category\\\\Option\\\\Duplicate'")) {
    $fail('Create processor must not call the removed Duplicate processor (#881)');
}

fwrite(STDOUT, "OK CategoryOptionDuplicateTest (#881)\n");
exit(0);
