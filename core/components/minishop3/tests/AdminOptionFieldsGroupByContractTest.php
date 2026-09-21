<?php

/**
 * AdminOptionFields GROUP BY contract (#615 / #4).
 *
 * Under MySQL ONLY_FULL_GROUP_BY the previous getFieldsForProduct() query selected
 * bare msCategoryOption columns (position/required/value) alongside `groupby(msOption.id)`
 * and `sortby(msCategoryOption.position)`, which made the query fail and left
 * option_fields empty. This test pins the fix: category columns must be aggregated
 * (MIN/MAX) and OptionGroup.name must be in the GROUP BY.
 *
 * Run: php tests/AdminOptionFieldsGroupByContractTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$file = dirname(__DIR__) . '/src/Services/Option/AdminOptionFields.php';
$src = is_file($file) ? file_get_contents($file) : false;
if ($src === false) {
    $fail('cannot read src/Services/Option/AdminOptionFields.php');
}

// The bare msCategoryOption column select (second getSelectColumns) must be gone.
if (preg_match('/getSelectColumns\(\s*msCategoryOption::class/', $src) === 1) {
    $fail('getFieldsForProduct must not select msCategoryOption columns via getSelectColumns');
}

// position must be aggregated and sorted by alias, not bare.
if (preg_match("/sortby\(\s*'msCategoryOption\.position'/", $src) === 1) {
    $fail('must not sortby bare msCategoryOption.position (ONLY_FULL_GROUP_BY violation)');
}
if (preg_match('/MIN\(\s*`msCategoryOption`\.position\s*\)\s*AS\s*`min_category_position`/', $src) !== 1) {
    $fail('must select MIN(msCategoryOption.position) AS min_category_position');
}
if (preg_match("/sortby\(\s*'`min_category_position`'/", $src) !== 1) {
    $fail('must sortby the min_category_position alias');
}

// required + value must be aggregated so they stay valid under ONLY_FULL_GROUP_BY.
if (preg_match('/MAX\(\s*`msCategoryOption`\.required\s*\)\s*AS\s*`required`/', $src) !== 1) {
    $fail('must select MAX(msCategoryOption.required) AS required');
}
if (preg_match('/MIN\(\s*`msCategoryOption`\.value\s*\)\s*AS\s*`value`/', $src) !== 1) {
    $fail('must select MIN(msCategoryOption.value) AS value');
}

// OptionGroup.name is functionally dependent on msOption.id via option_group_id,
// but ONLY_FULL_GROUP_BY cannot infer that across the join → must be grouped.
if (preg_match("/groupby\(\s*'`OptionGroup`\.name'/", $src) !== 1) {
    $fail('must groupby OptionGroup.name (cross-join functional dependency is not inferred)');
}

// MariaDB does not infer msOption columns from the PK (MySQL 5.7+ does).
if (preg_match('/function groupBySelectedOptionColumns/', $src) !== 1) {
    $fail('must group every selected msOption column (MariaDB ONLY_FULL_GROUP_BY)');
}
if (preg_match('/\$this->groupBySelectedOptionColumns\(\$c\)/', $src) !== 1) {
    $fail('selectCategoryOptionAggregates must call groupBySelectedOptionColumns');
}
if (preg_match("/groupby\(\s*'`msOption`\.`key`'/", $src) !== 1) {
    $fail('getOptionKeys must groupby msOption.key (MariaDB rejects PK-only GROUP BY)');
}

// Failed SQL must be logged: getIterator() yields nothing and hides the error.
if (preg_match('/function logOptionQueryFailure/', $src) !== 1) {
    $fail('must log when the option-fields query fails');
}
if (preg_match('/LOG_LEVEL_ERROR/', $src) !== 1) {
    $fail('query failure must be logged at error level');
}

// Multi-category semantics are intentional, not an accident of MIN/MAX.
if (preg_match('/required if any category/i', $src) !== 1) {
    $fail('document MAX(required): required if any category marks the option required');
}
if (preg_match('/one deterministic default/i', $src) !== 1) {
    $fail('document MIN(value): one deterministic default, not a merge');
}

fwrite(STDOUT, "OK AdminOptionFieldsGroupByContractTest\n");
exit(0);
