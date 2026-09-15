<?php

/**
 * #704 / #716: obsolete Extra files are listed and purged only under the component root.
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
$assetsRoot = $repoRoot . '/assets/components/minishop3';
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
if (!str_contains($resolverSrc, 'ms3_frontend_assets') || !str_contains($resolverSrc, 'isReferencedByFrontendAssets')) {
    $fail('resolver must skip assets still listed in ms3_frontend_assets');
}

require_once $helperPath;

$list = ObsoletePackageFiles::load($configPath);
$core = $list['core'];
$assets = $list['assets'];

// Frozen sets from git history (#704 processors + #716 assets/config/mgr).
$expectedCore = [
    'config/mgr/product/data-tab-left.json',
    'config/mgr/product/data-tab-right.json',
    'config/mgr/settings/delivery/grid.json',
    'config/mgr/settings/delivery/window-info.json',
    'config/mgr/settings/delivery/window-settings.json',
    'config/mgr/settings/vendor/grid.json',
    'config/mgr/settings/vendor/window.json',
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

$expectedAssets = [
    'css/mgr/help.css',
    'css/mgr/utilities/_plugin-vue_export-helper.min.css',
    'css/mgr/utilities/extra-fields.min.css',
    'css/mgr/utilities/fields-management.min.css',
    'css/mgr/utilities/gallery-uploader.min.css',
    'css/mgr/utilities/main.min.css',
    'css/mgr/utilities/request.min.css',
    'css/mgr/utilities/useLexicon.min.css',
    'img/mgr/ms2_logo.png',
    'img/mgr/ms2_thumb.png',
    'img/web/ms2_big.png',
    'img/web/ms2_big@2x.png',
    'img/web/ms2_medium.png',
    'img/web/ms2_medium@2x.png',
    'img/web/ms2_small.png',
    'img/web/ms2_small@2x.png',
    'js/mgr/category/option.grid.js',
    'js/mgr/category/option.windows.js',
    'js/mgr/category/product.grid.js',
    'js/mgr/customers/customers.grid.addresses.js',
    'js/mgr/customers/customers.grid.js',
    'js/mgr/customers/customers.js',
    'js/mgr/customers/customers.panel.js',
    'js/mgr/customers/customers.window.address.js',
    'js/mgr/customers/customers.window.js',
    'js/mgr/customers/customers.wrapper.js',
    'js/mgr/minishop.js',
    'js/mgr/misc/default.grid.js',
    'js/mgr/misc/default.window.js',
    'js/mgr/misc/ext.ddview.js',
    'js/mgr/misc/ms3.combo.js',
    'js/mgr/misc/ms3.utils.js',
    'js/mgr/misc/plupload/Moxie.swf',
    'js/mgr/misc/plupload/Moxie.xap',
    'js/mgr/misc/plupload/i18n.js',
    'js/mgr/misc/plupload/i18n/de.js',
    'js/mgr/misc/plupload/i18n/en.js',
    'js/mgr/misc/plupload/i18n/fr.js',
    'js/mgr/misc/plupload/i18n/ru.js',
    'js/mgr/misc/plupload/moxie.js',
    'js/mgr/misc/plupload/moxie.min.js',
    'js/mgr/misc/plupload/plupload.dev.js',
    'js/mgr/misc/plupload/plupload.full.min.js',
    'js/mgr/misc/plupload/plupload.min.js',
    'js/mgr/misc/sortable/sortable.min.js',
    'js/mgr/misc/strftime-min-1.3.js',
    'js/mgr/model-fields/model-fields.wrapper.js',
    'js/mgr/notifications/notifications.wrapper.js',
    'js/mgr/orders/order.wrapper.js',
    'js/mgr/orders/orders.form.js',
    'js/mgr/orders/orders.grid.js',
    'js/mgr/orders/orders.grid.logs.js',
    'js/mgr/orders/orders.grid.products.js',
    'js/mgr/orders/orders.js',
    'js/mgr/orders/orders.panel.js',
    'js/mgr/orders/orders.window.js',
    'js/mgr/orders/orders.window.product.js',
    'js/mgr/orders/orders.wrapper.js',
    'js/mgr/product/category.tree.js',
    'js/mgr/product/gallery/gallery.panel.js',
    'js/mgr/product/gallery/gallery.toolbar.js',
    'js/mgr/product/gallery/gallery.view.js',
    'js/mgr/product/gallery/gallery.window.js',
    'js/mgr/product/links.grid.js',
    'js/mgr/product/links.window.js',
    'js/mgr/settings/delivery/grid.js',
    'js/mgr/settings/delivery/members.js',
    'js/mgr/settings/delivery/window.js',
    'js/mgr/settings/link/grid.js',
    'js/mgr/settings/link/window.js',
    'js/mgr/settings/option/grid.js',
    'js/mgr/settings/option/tree.js',
    'js/mgr/settings/option/types/combobox-colors.grid.js',
    'js/mgr/settings/option/types/combobox.grid.js',
    'js/mgr/settings/option/window.js',
    'js/mgr/settings/payment/grid.js',
    'js/mgr/settings/payment/members.js',
    'js/mgr/settings/payment/window.js',
    'js/mgr/settings/settings.js',
    'js/mgr/settings/settings.panel.js',
    'js/mgr/settings/status/grid.js',
    'js/mgr/settings/status/window.js',
    'js/mgr/settings/vendor/grid.js',
    'js/mgr/settings/vendor/grid_back.js',
    'js/mgr/settings/vendor/window.js',
    'js/mgr/settings/vendor/window_back.js',
    'js/mgr/utilities/_plugin-vue_export-helper.min.js',
    'js/mgr/utilities/extra-fields.min.js',
    'js/mgr/utilities/extrafield/grid.js',
    'js/mgr/utilities/extrafield/tree.classes.js',
    'js/mgr/utilities/extrafield/window.js',
    'js/mgr/utilities/fields-management.min.js',
    'js/mgr/utilities/fields.min.js',
    'js/mgr/utilities/gallery-uploader.min.js',
    'js/mgr/utilities/gallery/panel.js',
    'js/mgr/utilities/import/panel.js',
    'js/mgr/utilities/index.min.js',
    'js/mgr/utilities/index.min2.js',
    'js/mgr/utilities/main.min.js',
    'js/mgr/utilities/panel.js',
    'js/mgr/utilities/request.min.js',
    'js/mgr/utilities/useLexicon.min.js',
    'js/mgr/utilities/utilities.js',
    'js/mgr/utilities/utilities.panel.js',
    'js/mgr/utilities/xtypes.min.js',
    'js/web/default.js',
    'js/web/message_settings.js',
    'js/web/modules/auth-forms.js',
    'js/web/modules/callback.js',
    'js/web/modules/cart.js',
    'js/web/modules/customer-addresses.js',
    'js/web/modules/customer.js',
    'js/web/modules/form.js',
    'js/web/modules/order-cancel.js',
    'js/web/modules/order.js',
    'js/web/modules/request.js',
    'plugins/.placeholder',
];

sort($core);
$expectedCoreSorted = $expectedCore;
sort($expectedCoreSorted);
if ($core !== $expectedCoreSorted) {
    $fail('obsolete core list must exactly match frozen processors + config/mgr leftovers');
}

sort($assets);
$expectedAssetsSorted = $expectedAssets;
sort($expectedAssetsSorted);
if ($assets !== $expectedAssetsSorted) {
    $fail('obsolete assets list must exactly match frozen deleted assets from git history');
}

foreach ($core as $path) {
    if (ObsoletePackageFiles::resolveUnderRoot($coreRoot, $path) === null) {
        $fail("unsafe obsolete core path: {$path}");
    }
}

foreach ($assets as $path) {
    if (ObsoletePackageFiles::resolveUnderRoot($assetsRoot, $path) === null) {
        $fail("unsafe obsolete assets path: {$path}");
    }
}

// Autocomplete still ships until #690 removes it from the tree; upgrades purge it.
$allowedStillShipped = ['src/Processors/Product/Autocomplete.php'];

foreach ($core as $path) {
    if (is_file($coreRoot . '/' . $path) && !in_array($path, $allowedStillShipped, true)) {
        $fail("obsolete core path still ships in the Extra: {$path}");
    }
}

foreach ($assets as $path) {
    if (is_file($assetsRoot . '/' . $path)) {
        $fail("obsolete assets path still ships in the Extra: {$path}");
    }
}

fwrite(STDOUT, "OK ObsoletePackageFilesTest\n");
exit(0);
