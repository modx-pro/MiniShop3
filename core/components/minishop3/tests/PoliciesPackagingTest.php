<?php

/**
 * Regression #613: miniShopManagerPolicy packaged under template + install resolver.
 *
 * Запуск: php tests/PoliciesPackagingTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$repoRoot = dirname(__DIR__, 4);

$buildPath = $repoRoot . '/_build/build.php';
$buildSrc = file_get_contents($buildPath);
if ($buildSrc === false) {
    $fail('cannot read _build/build.php');
}

if (!str_contains($buildSrc, "'Policies' => [")) {
    $fail('policyTemplates() must declare Policies related object attributes');
}

if (!str_contains($buildSrc, "addMany(\$policies, 'Policies')")) {
    $fail('miniShopManagerPolicy must be nested under miniShopManagerPolicyTemplate');
}

if (!preg_match('/private function policies\(\): void\s*\{[^}]*Packaged via policyTemplates/s', $buildSrc)) {
    if (!str_contains($buildSrc, 'Access policies packaged via policyTemplates()')) {
        $fail('standalone policies() must be no-op with packaging note');
    }
}

$resolverPath = $repoRoot . '/_build/resolvers/resolver_09_policies.php';
if (!is_readable($resolverPath)) {
    $fail('resolver_09_policies.php missing');
}

$resolverSrc = file_get_contents($resolverPath);
if ($resolverSrc === false || !str_contains($resolverSrc, 'manager_access_policy.php')) {
    $fail('resolver must load manager_access_policy.php');
}

$policyConfig = $repoRoot . '/core/components/minishop3/config/manager_access_policy.php';
if (!is_readable($policyConfig)) {
    $fail('manager_access_policy.php missing');
}

/** @var array<string, array<string, mixed>> $definitions */
$definitions = require $policyConfig;
if (!isset($definitions['miniShopManagerPolicy']['data']['msorder_save'])) {
    $fail('miniShopManagerPolicy must grant msorder_save');
}
if (!isset($definitions['miniShopManagerPolicy']['data']['mssetting_save'])) {
    $fail('miniShopManagerPolicy must grant mssetting_save');
}

$elementsPolicies = $repoRoot . '/_build/elements/policies.php';
$elementsSrc = file_get_contents($elementsPolicies);
if ($elementsSrc === false || !str_contains($elementsSrc, 'manager_access_policy.php')) {
    $fail('_build/elements/policies.php must require manager_access_policy.php');
}

fwrite(STDOUT, "OK PoliciesPackagingTest\n");
exit(0);
