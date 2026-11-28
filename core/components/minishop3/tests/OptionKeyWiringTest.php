<?php

/**
 * #841: manager option create/update must reject reserved keys derived from
 * the msOption metaMap, and schema/model rules must stay in sync.
 *
 * Run: php tests/OptionKeyWiringTest.php
 */

declare(strict_types=1);

$root = dirname(__DIR__, 4);
$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$controller = (string) file_get_contents($root . '/core/components/minishop3/src/Controllers/Api/Manager/OptionsController.php');
if (substr_count($controller, 'optionKeyErrorResponse(') < 2) {
    $fail('Both create and update must validate option keys (#841)');
}

$validator = (string) file_get_contents($root . '/core/components/minishop3/src/Services/Option/OptionKeyValidator.php');
if (!str_contains($validator, "return 'ms3_option_err_invalid_key';")) {
    $fail('Validator must fail closed when rules are missing (#841)');
}

$schema = (string) file_get_contents($root . '/core/components/minishop3/schema/minishop3.mysql.schema.xml');
$map = (string) file_get_contents($root . '/core/components/minishop3/src/Model/mysql/msOption.php');
foreach (['stock', 'vendor_id', 'source_id', 'preview_file_id'] as $key) {
    if (!str_contains($schema, $key . '|') || !str_contains($map, $key . '|')) {
        $fail("Reserved rule must include {$key} in schema and generated map (#841)");
    }
}

fwrite(STDOUT, "OK OptionKeyWiringTest (#841)\n");
exit(0);
