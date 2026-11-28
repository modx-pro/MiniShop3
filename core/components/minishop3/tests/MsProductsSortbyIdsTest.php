<?php

/**
 * ms3_products sortby=ids must survive pdoTools::addSort() escaping (#912).
 *
 * pdoTools rewrites every comma-split sortby piece without a backtick using
 * preg_replace('#(.*?)\.(.*?)\s#', '`$1`.`$2`', ...). A bare "msProduct.id "
 * inside our CASE then becomes invalid SQL and the catalog is empty.
 *
 * Run: php tests/MsProductsSortbyIdsTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$root = dirname(__DIR__, 4);
$snippet = (string) file_get_contents($root . '/core/components/minishop3/elements/snippets/ms3_products.php');

if (!str_contains($snippet, "'WHEN `msProduct`.`id` = ")) {
    $fail('sortby=ids CASE must backtick msProduct.id so pdoTools does not rewrite it (#912)');
}
if (!str_contains($snippet, "strtolower(trim(\$_ms3SortBy)) === 'ids'")) {
    $fail('sortby=ids detection must be case/whitespace tolerant (#912)');
}
if (!str_contains($snippet, "\$scriptProperties['sortdir'] = ''")) {
    $fail('sortby=ids must not apply &sortdir, matching pdoTools (#912)');
}

// Same escaping step as pdoTools\Fetch::addSort() (3.0.x).
$pdoToolsEscapeSortby = static function (string $sortby): string {
    $tmp = explode(',', $sortby);
    array_walk($tmp, static function (&$value): void {
        if (strpos($value, '`') === false) {
            $value = preg_replace('#(.*?)\.(.*?)\s#', '`$1`.`$2`', $value);
        }
    });

    return implode(',', $tmp);
};

$caseParts = [];
foreach ([5, 3, 9] as $index => $id) {
    $caseParts[] = 'WHEN `msProduct`.`id` = ' . $id . ' THEN ' . $index;
}
$sortby = 'CASE ' . implode(' ', $caseParts) . ' ELSE 3 END';

if ($pdoToolsEscapeSortby($sortby) !== $sortby) {
    $fail('pdoTools escaping must leave the sortby=ids CASE unchanged: ' . $pdoToolsEscapeSortby($sortby));
}

fwrite(STDOUT, "OK MsProductsSortbyIdsTest (#912)\n");
exit(0);
