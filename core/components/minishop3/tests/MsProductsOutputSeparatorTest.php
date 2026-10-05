<?php

/**
 * msProducts must not put the debug log into the row list before implode() (#806).
 *
 * $log is only filled for a manager session with &showLog, but it was added to
 * $output anyway, so every visitor got a trailing outputSeparator — visible as a
 * dangling ", " for any custom separator — and the manager got the separator glued
 * between the last product and the <pre> block.
 *
 * Run: php tests/MsProductsOutputSeparatorTest.php
 */

declare(strict_types=1);

$snippetPath = __DIR__ . '/../elements/snippets/ms3_products.php';
$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL MsProductsOutputSeparatorTest: {$message}\n");
    exit(1);
};

if (!is_readable($snippetPath)) {
    $fail("Cannot read {$snippetPath}");
}

$source = (string)file_get_contents($snippetPath);

if (!str_contains($source, 'implode($outputSeparator, $output) . $log')) {
    $fail('rows must be imploded first and the log appended afterwards (#806)');
}

// The log belongs to a placeholder only in the toSeparatePlaceholders branch.
$logAssignments = preg_match_all('/\\$output\[\'log\'\] = \\$log;/', $source);
if ($logAssignments !== 1) {
    $fail(
        "expected exactly one \$output['log'] assignment (toSeparatePlaceholders branch),"
        . " found {$logAssignments} (#806)"
    );
}

if (!preg_match(
    '/\\$output\[\'log\'\] = \\$log;\s*\n\s*\\$modx->setPlaceholders\(\\$output, \\$toSeparatePlaceholders\);/',
    $source
)) {
    $fail('$output[\'log\'] must only feed the separate placeholders branch (#806)');
}

// The separator default is still applied before imploding.
if (!str_contains($source, '$outputSeparator = "\n";')) {
    $fail('the default outputSeparator must be kept');
}

fwrite(STDOUT, "OK MsProductsOutputSeparatorTest\n");
exit(0);