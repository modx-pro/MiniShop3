<?php

/**
 * #704: obsolete Extra files are listed and purged only under the component root.
 *
 * Run: php tests/ObsoletePackageFilesTest.php
 */

declare(strict_types=1);

use MiniShop3\Utils\ObsoletePackageFiles;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL ObsoletePackageFilesTest: {$message}\n");
    exit(1);
};

$repoRoot = dirname(__DIR__, 4);
$coreRoot = dirname(__DIR__);
$configPath = $coreRoot . '/config/obsolete_package_files.php';
$resolverPath = $repoRoot . '/_build/resolvers/resolver_10_obsolete_files.php';
$helperPath = $coreRoot . '/src/Utils/ObsoletePackageFiles.php';

foreach ([$configPath, $resolverPath, $helperPath] as $path) {
    if (!is_readable($path)) {
        $fail(basename($path) . ' missing');
    }
}

$resolverSrc = file_get_contents($resolverPath);
if (!is_string($resolverSrc)) {
    $fail('cannot read resolver_10_obsolete_files.php');
}
if (!str_contains($resolverSrc, 'ACTION_UPGRADE') || !str_contains($resolverSrc, 'ObsoletePackageFiles')) {
    $fail('resolver must run on ACTION_UPGRADE via ObsoletePackageFiles');
}

require_once $helperPath;

$list = ObsoletePackageFiles::load($configPath);
$core = $list['core'];

// Frozen set from git history of deleted Processors + Autocomplete (#690 / #688).
$expectedCore = [
    'src/Processors/Category/GetList.php',
    'src/Processors/Category/Option/Activate.php',
    'src/Processors/Category/Option/Add.php',
    'src/Processors/Category/Option/Deactivate.php',
    'src/Processors/Category/Option/Duplicate.php',
    'src/Processors/Category/Option/GetList.php',
    'src/Processors/Category/Option/Multiple.php',
    'src/Processors/Category/Option/Remove.php',
    'src/Processors/Category/Option/Required.php',
    'src/Processors/Category/Option/Unrequired.php',
    'src/Processors/Category/Option/Update.php',
    'src/Processors/Category/Option/UpdateFromGrid.php',
    'src/Processors/Config/Read.php',
    'src/Processors/Gallery/RemoveCatalogs.php',
    'src/Processors/Order/Create.php',
    'src/Processors/Order/Get.php',
    'src/Processors/Order/GetList.php',
    'src/Processors/Order/GetLog.php',
    'src/Processors/Order/Multiple.php',
    'src/Processors/Order/Product/Create.php',
    'src/Processors/Order/Product/Get.php',
    'src/Processors/Order/Product/GetList.php',
    'src/Processors/Order/Product/Remove.php',
    'src/Processors/Order/Product/Update.php',
    'src/Processors/Order/Remove.php',
    'src/Processors/Order/ToggleDraft.php',
    'src/Processors/Order/Update.php',
    'src/Processors/Product/Autocomplete.php',
    'src/Processors/Product/ProductLink/GetList.php',
    'src/Processors/Product/ProductLink/Multiple.php',
    'src/Processors/Settings/Delivery/Payments/Disable.php',
    'src/Processors/Settings/Delivery/Payments/Enable.php',
    'src/Processors/Settings/Delivery/Payments/GetList.php',
    'src/Processors/Settings/Delivery/Payments/Multiple.php',
    'src/Processors/Settings/Option/Assign.php',
    'src/Processors/Settings/Option/Create.php',
    'src/Processors/Settings/Option/Duplicate.php',
    'src/Processors/Settings/Option/Get.php',
    'src/Processors/Settings/Option/GetCategories.php',
    'src/Processors/Settings/Option/GetList.php',
    'src/Processors/Settings/Option/GetNodes.php',
    'src/Processors/Settings/Option/GetTypes.php',
    'src/Processors/Settings/Option/Multiple.php',
    'src/Processors/Settings/Option/Remove.php',
    'src/Processors/Settings/Option/Update.php',
    'src/Processors/Settings/Payment/Deliveries/Disable.php',
    'src/Processors/Settings/Payment/Deliveries/Enable.php',
    'src/Processors/Settings/Payment/Deliveries/GetList.php',
    'src/Processors/Settings/Payment/Deliveries/Multiple.php',
    'src/Processors/Utilities/ExtraField/Create.php',
    'src/Processors/Utilities/ExtraField/Get.php',
    'src/Processors/Utilities/ExtraField/GetClassNodes.php',
    'src/Processors/Utilities/ExtraField/GetList.php',
    'src/Processors/Utilities/ExtraField/Multiple.php',
    'src/Processors/Utilities/ExtraField/Remove.php',
    'src/Processors/Utilities/ExtraField/Update.php',
];

sort($core);
$expectedSorted = $expectedCore;
sort($expectedSorted);
if ($core !== $expectedSorted) {
    $fail('obsolete core list must exactly match the frozen 55 deleted processors + Autocomplete');
}

foreach ($core as $path) {
    if (ObsoletePackageFiles::resolveUnderRoot($coreRoot, $path) === null) {
        $fail("unsafe obsolete path: {$path}");
    }
}

// Autocomplete still ships until #690 removes it from the tree; upgrades purge it.
$allowedStillShipped = ['src/Processors/Product/Autocomplete.php'];

foreach ($core as $path) {
    if (is_file($coreRoot . '/' . $path) && !in_array($path, $allowedStillShipped, true)) {
        $fail("obsolete path still ships in the Extra: {$path}");
    }
}

fwrite(STDOUT, "OK ObsoletePackageFilesTest\n");
exit(0);
