<?php

/**
 * #812/#866: class filters must accept short aliases and FQCN at every lookup,
 * and the xPDO extra-field meta cache must be versioned after the null fix.
 *
 * Run: php tests/ExtraFieldsClassAndNullTest.php
 */

declare(strict_types=1);

$root = dirname(__DIR__, 4);
$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$base = $root . '/core/components/minishop3/src/';
foreach ([
    'Services/ExtraFieldsService.php',
    'Services/Order/ManagerOrderPresenter.php',
    'Services/ExtraFields/KeyValueFieldService.php',
    'Services/ExtraFields/RepeaterFieldService.php',
] as $relative) {
    $source = (string) file_get_contents($base . $relative);
    if (!str_contains($source, 'classFilterVariants(')) {
        $fail($relative . ' must normalize class filters (#812)');
    }
}

$utils = (string) file_get_contents($base . 'Utils/ExtraFields.php');
if (!str_contains($utils, "'extra_fields_meta_v2'")) {
    $fail('Extra field meta cache key must be bumped after the null fix (#866)');
}
if (!str_contains($utils, '$nullFlag === null ? true')) {
    $fail('Legacy NULL null-flag must remain nullable (#866)');
}

// Manager assets stay on short aliases: the PHP side accepts both, and this
// keeps new assets compatible with an older backend during rolling deploys.
$vue = (string) file_get_contents($root . '/vueManager/src/composables/useOrderLoad.js');
if (str_contains($vue, 'MiniShop3\\\\Model\\\\msOrder')) {
    $fail('Vue must keep short class aliases for backward compatibility (#812)');
}

fwrite(STDOUT, "OK ExtraFieldsClassAndNullTest (#812/#866)\n");
exit(0);
