<?php

/**
 * Static checks for CatalogResourceGroupVisibility (#659).
 *
 * Run: php tests/CatalogResourceGroupVisibilityTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/stubs/ModxStub.php';

use MiniShop3\Services\Catalog\CatalogQuery;
use MiniShop3\Services\Catalog\CatalogResourceGroupVisibility;
use MODX\Revolution\modX;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$assertSame = static function ($expected, $actual, string $case) use ($fail): void {
    if ($actual !== $expected) {
        $fail($case . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
};

$assertTrue = static function (bool $actual, string $case) use ($fail): void {
    if (!$actual) {
        $fail($case . ': expected true');
    }
};

$assertFalse = static function (bool $actual, string $case) use ($fail): void {
    if ($actual) {
        $fail($case . ': expected false');
    }
};

$dgTable = '`modx_document_groups`';
$argTable = '`modx_access_resource_groups`';
$sql = CatalogResourceGroupVisibility::buildNotExistsSql($dgTable, $argTable, 'msProduct', "'web'");

$assertTrue(str_contains($sql, 'NOT EXISTS'), 'SQL contains NOT EXISTS');
$assertTrue(str_contains($sql, $dgTable), 'SQL contains document_groups table');
$assertTrue(str_contains($sql, $argTable), 'SQL contains access_resource_groups table');
$assertTrue(str_contains($sql, 'dg.`document` = msProduct.`id`'), 'SQL joins document to resource id');
$assertTrue(str_contains($sql, 'arg.`target` = dg.`document_group`'), 'SQL joins ACL target to group');
$assertTrue(
    str_contains($sql, "arg.`context_key` = 'web' OR arg.`context_key` = '' OR arg.`context_key` IS NULL"),
    'SQL scopes context with empty and NULL fallbacks'
);
$assertTrue(str_contains($sql, 'arg.`principal` <> 0'), 'SQL ignores anonymous principal on protect path');
$assertTrue(str_contains($sql, 'arg_anon.`principal` = 0'), 'SQL keeps explicit anonymous grant visible');
$assertTrue(str_contains($sql, 'principal_class` IN ('), 'SQL filters principal_class like core');
$assertTrue(
    str_contains($sql, 'MODX\\\\Revolution\\\\modUserGroup') || str_contains($sql, "MODX\\Revolution\\modUserGroup"),
    'SQL includes FQCN principal_class'
);

$makeModx = static function (array $options): modX {
    return new class ($options) extends modX {
        /** @param array<string, mixed> $options */
        public function __construct(private array $options)
        {
        }

        public function getOption(string $key, $options = null, $default = null)
        {
            return $this->options[$key] ?? $default;
        }
    };
};

$settingCases = [
    [
        'options' => [],
        'enabled' => true,
        'cacheKey' => '1',
        'label' => 'enabled by default',
    ],
    [
        'options' => [CatalogResourceGroupVisibility::SETTING_KEY => false],
        'enabled' => false,
        'cacheKey' => '0',
        'label' => 'disabled when ms3 setting off',
    ],
    [
        'options' => ['access_resource_group_enabled' => false],
        'enabled' => false,
        'cacheKey' => '0',
        'label' => 'disabled when MODX access_resource_group_enabled off',
    ],
    [
        'options' => [
            CatalogResourceGroupVisibility::SETTING_KEY => '0',
            'access_resource_group_enabled' => 'no',
        ],
        'enabled' => false,
        'cacheKey' => '0',
        'label' => 'disabled when both settings off',
    ],
    [
        'options' => [
            CatalogResourceGroupVisibility::SETTING_KEY => 'yes',
            'access_resource_group_enabled' => '1',
        ],
        'enabled' => true,
        'cacheKey' => '1',
        'label' => 'enabled when both settings on',
    ],
];

foreach ($settingCases as $case) {
    $service = new CatalogResourceGroupVisibility($makeModx($case['options']));
    if ($case['enabled']) {
        $assertTrue($service->isEnabled(), $case['label']);
    } else {
        $assertFalse($service->isEnabled(), $case['label']);
    }
    $assertSame($case['cacheKey'], $service->appliesToCacheKey(), $case['label'] . ' cache key');
}

$assertSame(true, CatalogQuery::toBool('yes'), 'toBool parity for setting values');

fwrite(STDOUT, "OK CatalogResourceGroupVisibilityTest\n");
exit(0);
