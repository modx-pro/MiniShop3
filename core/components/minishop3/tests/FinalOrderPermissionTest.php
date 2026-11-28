<?php

/**
 * #801: editing order composition in a final status requires msorder_edit_final,
 * and existing installs must receive the permission in their policy template.
 *
 * Run: php tests/FinalOrderPermissionTest.php
 */

declare(strict_types=1);

$root = dirname(__DIR__, 4);
$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$template = (string) file_get_contents($root . '/_build/elements/policyTemplates.php');
if (!str_contains($template, "'msorder_edit_final' => []")) {
    $fail('Policy template must expose msorder_edit_final (#801)');
}

$resolver = (string) file_get_contents($root . '/_build/resolvers/resolver_09_policies.php');
if (!str_contains($resolver, "'name' => 'msorder_edit_final'")) {
    $fail('Upgrade resolver must add msorder_edit_final to existing templates (#801)');
}

foreach (['en', 'ru'] as $lang) {
    $permissions = (string) file_get_contents($root . "/core/components/minishop3/lexicon/{$lang}/permissions.inc.php");
    if (!str_contains($permissions, "['msorder_edit_final']")) {
        $fail("{$lang} permissions lexicon misses msorder_edit_final (#801)");
    }
    $default = (string) file_get_contents($root . "/core/components/minishop3/lexicon/{$lang}/default.inc.php");
    foreach (['add', 'update', 'delete'] as $operation) {
        if (!str_contains($default, "['ms3_err_order_final_{$operation}_denied']")) {
            $fail("{$lang} default lexicon misses final-order {$operation} message (#801)");
        }
    }
}

$service = (string) file_get_contents($root . '/core/components/minishop3/src/Services/Order/ManagerOrderProductsService.php');
if (str_contains($service, "lexicon('order_cannot_add_to_finalized')")) {
    $fail('API must not use a vue-topic-only lexicon key (#801)');
}
if (substr_count($service, 'canEditFinal()') < 3) {
    $fail('All order-product mutators must check msorder_edit_final (#801)');
}

fwrite(STDOUT, "OK FinalOrderPermissionTest (#801)\n");
exit(0);
