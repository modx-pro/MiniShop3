<?php

/**
 * Smoke: every src/Processors class resolves a non-empty $permission
 * unless allowlisted (#660 Gallery, #672 Product + full-tree ratchet).
 *
 * Resolution uses ReflectionClass (inheritance-aware). Regex on source
 * is not used for the permission value.
 *
 * Run: php tests/ProcessorPermissionsSmokeTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/stubs/ModxStub.php';
require __DIR__ . '/stubs/ModxProcessorBasesStub.php';

use MODX\Revolution\modX;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$assertSame = static function ($expected, $actual, string $label) use ($fail): void {
    if ($actual !== $expected) {
        $fail(sprintf(
            "%s:\nexpected: %s\nactual:   %s",
            $label,
            var_export($expected, true),
            var_export($actual, true)
        ));
    }
};

$assertTrue = static function (bool $actual, string $label) use ($assertSame): void {
    $assertSame(true, $actual, $label);
};

$assertFalse = static function (bool $actual, string $label) use ($assertSame): void {
    $assertSame(false, $actual, $label);
};

/**
 * Intentional empty $permission (or connector entrypoints that gate elsewhere).
 * Do not add mutating Sort/Multiple here — they must fail the ratchet.
 *
 * @var array<string, string> relative path => reason
 */
$allowEmptyPermission = [
    // Product reads — no msproduct_list in policy; Vue uses Manager REST (#672)
    'Product/Get.php' => 'read: legacy combo/get; no msproduct_list in policy',
    'Product/GetList.php' => 'read: legacy combo list; no msproduct_list in policy',
    'Product/GetOptions.php' => 'read: option helper; no msproduct_list in policy',

    // Category tree helpers (resource UI)
    'Category/GetCats.php' => 'read: category picker list',
    'Category/GetNodes.php' => 'read: resource tree nodes',

    // System element combos
    'System/Element/Context/GetList.php' => 'read: MODX context combo',
    'System/Element/Chunk/GetList.php' => 'read: chunk combo',
    'System/Element/Resource/GetList.php' => 'read: resource combo',
    'System/User/GetList.php' => 'read: user combo',

    // Customer combobox + legacy Multiple dispatchers (out of #672 scope)
    'Customer/GetListCombobox.php' => 'read: customer combo',
    'Customer/Multiple.php' => 'deferred: Settings/Customer Multiple gate — follow-up',
    'Customer/Address/Multiple.php' => 'deferred: address Multiple gate — follow-up',

    // Settings Multiple dispatchers (same pattern as Gallery pre-#661; out of #672)
    'Settings/Delivery/Multiple.php' => 'deferred: settings Multiple — follow-up',
    'Settings/Payment/Multiple.php' => 'deferred: settings Multiple — follow-up',
    'Settings/Status/Multiple.php' => 'deferred: settings Multiple — follow-up',
    'Settings/Link/Multiple.php' => 'deferred: settings Multiple — follow-up',
    'Settings/Vendor/Multiple.php' => 'deferred: settings Multiple — follow-up',
    'Settings/GetClass.php' => 'read: class-name helper for settings UI',

    // Api connector: Index intentionally empty + checkPermissions true; Customer auth is public API
    'Api/Index.php' => 'connector entry: checkPermissions() always true by design',
    'Api/Router.php' => 'connector router; auth on route handlers',
    'Api/Customer/Login.php' => 'public customer auth processor',
    'Api/Customer/Register.php' => 'public customer auth processor',
    'Api/Customer/Logout.php' => 'public customer auth processor',
    'Api/Customer/ForgotPassword.php' => 'public customer auth processor',
    'Api/Customer/ResetPassword.php' => 'public customer auth processor',
    'Api/Customer/ResendVerification.php' => 'public customer auth processor',
    'Api/Customer/VerifyEmail.php' => 'public customer auth processor',
];

$processorsRoot = dirname(__DIR__) . '/src/Processors';
if (!is_dir($processorsRoot)) {
    $fail("Processors directory not found: {$processorsRoot}");
}

$resolvePermission = static function (string $class): string {
    if (!class_exists($class)) {
        return '';
    }
    $ref = new ReflectionClass($class);
    if (!$ref->hasProperty('permission')) {
        return '';
    }
    $value = $ref->getProperty('permission')->getDefaultValue();

    return is_string($value) ? $value : '';
};

$relativeToClass = static function (string $relative): string {
    $path = preg_replace('/\.php$/', '', str_replace('\\', '/', $relative));

    return 'MiniShop3\\Processors\\' . str_replace('/', '\\', $path);
};

$declared = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($processorsRoot, FilesystemIterator::SKIP_DOTS)
);

/** @var SplFileInfo $file */
foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $absolute = $file->getPathname();
    $relative = substr($absolute, strlen($processorsRoot) + 1);
    $relative = str_replace('\\', '/', $relative);

    $source = file_get_contents($absolute);
    if ($source === false) {
        $fail("Cannot read {$relative}");
    }
    if (!preg_match('/\bclass\s+\w+/', $source)) {
        continue;
    }

    $class = $relativeToClass($relative);
    if (!class_exists($class)) {
        $fail("{$relative}: class {$class} failed to autoload");
    }

    $permission = $resolvePermission($class);
    $declared[$relative] = $permission;

    if ($permission !== '') {
        continue;
    }

    if (!isset($allowEmptyPermission[$relative])) {
        $fail("{$relative}: empty \$permission (resolved via reflection) — add gate or allowlist with reason");
    }
}

// Stale allowlist entries must not hide removals forever
foreach (array_keys($allowEmptyPermission) as $path) {
    if (!isset($declared[$path])) {
        $fail("Allowlist entry missing on disk: {$path}");
    }
    if ($declared[$path] !== '') {
        $fail("Allowlist entry {$path} now has permission {$declared[$path]} — remove from allowlist");
    }
}

$expectedPermissions = [
    'Gallery/Sort.php' => 'msproductfile_save',
    'Gallery/Multiple.php' => 'msproductfile_save',
    'Product/Sort.php' => 'msproduct_save',
    'Product/Multiple.php' => 'msproduct_save',
    'Product/Hide.php' => 'msproduct_save',
    'Product/Show.php' => 'msproduct_save',
    'Product/UpdateFromGrid.php' => 'msproduct_save',
];
foreach ($expectedPermissions as $path => $expected) {
    $assertSame($expected, $declared[$path] ?? null, "{$path} \$permission");
}

/**
 * Mirrors MODX ModelProcessor::checkPermissions().
 */
$checkPermissions = static function (object $modx, string $permission): bool {
    return $permission !== '' ? $modx->hasPermission($permission) : true;
};

$modxDenied = new modX();
$modxDenied->setPermissions([]);
foreach ([
    'Product/Sort.php' => 'msproduct_save',
    'Product/Multiple.php' => 'msproduct_save',
    'Gallery/Sort.php' => 'msproductfile_save',
] as $path => $permission) {
    $assertFalse(
        $checkPermissions($modxDenied, $declared[$path]),
        "{$path}: manager without {$permission} is denied"
    );
}

foreach ([
    'Product/Sort.php' => ['msproduct_save'],
    'Product/Multiple.php' => ['msproduct_save'],
    'Gallery/Sort.php' => ['msproductfile_save'],
] as $path => $permissions) {
    $modx = new modX();
    $modx->setPermissions($permissions);
    $assertTrue(
        $checkPermissions($modx, $declared[$path]),
        "{$path}: manager with {$permissions[0]} is allowed"
    );
}

$lexiconKeys = ['ms3_gallery_err_ns', 'ms3_gallery_err_no_product'];
foreach (['en', 'ru'] as $lang) {
    $lexiconPath = dirname(__DIR__) . "/lexicon/{$lang}/default.inc.php";
    $lexicon = file_get_contents($lexiconPath);
    if ($lexicon === false) {
        $fail("Cannot read lexicon {$lang}/default.inc.php");
    }
    foreach ($lexiconKeys as $key) {
        if (!str_contains($lexicon, "\$_lang['{$key}']")) {
            $fail("Missing lexicon key {$key} in {$lang}/default.inc.php");
        }
    }
}

fwrite(
    STDOUT,
    'OK ProcessorPermissionsSmokeTest (' . count($declared) . ' processors, '
    . count($allowEmptyPermission) . " allowlisted empty)\n"
);
exit(0);
