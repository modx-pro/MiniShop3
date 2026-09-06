<?php

/**
 * Smoke: MigrationGenerator resolves unprefixed ms3_* table names from xPDO metaMap (#645).
 *
 * Run: php tests/MigrationGeneratorTableResolveTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

if (!defined('MODX_CORE_PATH')) {
    define('MODX_CORE_PATH', dirname(__DIR__) . '/');
}

require_once __DIR__ . '/support/xpdo_om_stub.php';
require_once __DIR__ . '/support/modresource_stub.php';

use MiniShop3\Model\msCategory;
use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductData;
use MiniShop3\Model\msVendor;
use MiniShop3\Services\MigrationGenerator;
use MODX\Revolution\modX;

if (!class_exists(modX::class, false)) {
    require __DIR__ . '/stubs/ModxStub.php';
}

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL MigrationGeneratorTableResolveTest: {$message}\n");
    exit(1);
};

$assertSame = static function ($expected, $actual, string $case) use ($fail): void {
    if ($actual !== $expected) {
        $fail($case . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
};

$assertTrue = static function (bool $value, string $case) use ($fail): void {
    if (!$value) {
        $fail($case);
    }
};

$assertFalse = static function (bool $value, string $case) use ($fail): void {
    if ($value) {
        $fail($case);
    }
};

$modx = new class extends modX {
    public function loadClass($className, $fqn = true, $path = '', $force = false)
    {
        return class_exists($className);
    }

    public function getOption($key, $options = null, $default = null)
    {
        if ($key === 'dbtype') {
            return 'mysql';
        }

        return $default;
    }

    public function log($level, $msg, $target = '', $def = '', $file = '', $line = ''): void
    {
    }
};

$generator = new MigrationGenerator($modx);

$productDataTable = $generator->resolveTableName(msProductData::class);
$assertSame('ms3_products', $productDataTable, 'msProductData → ms3_products');
$assertFalse(str_contains($productDataTable, '`'), 'table name must not contain backticks');
$assertFalse(str_contains($productDataTable, 'site_content'), 'must not resolve to modResource table');

$assertSame('ms3_vendors', $generator->resolveTableName(msVendor::class), 'msVendor → ms3_vendors');

$assertTrue($generator->canHostExtraField(msProductData::class), 'msProductData can host extra fields');
$assertFalse($generator->canHostExtraField(msProduct::class), 'msProduct must be rejected (modResource STI)');
$assertFalse($generator->canHostExtraField(msCategory::class), 'msCategory must be rejected (modResource STI)');

foreach ([msProduct::class, msCategory::class] as $unsupportedClass) {
    try {
        $generator->resolveTableName($unsupportedClass);
        $fail('resolveTableName must throw for ' . $unsupportedClass);
    } catch (\InvalidArgumentException) {
        // expected
    }
}

echo "OK MigrationGeneratorTableResolveTest\n";
