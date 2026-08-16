<?php

/**
 * Smoke + unit helpers for product Links Vue tab (#114 / #350).
 *
 * Run: php tests/ProductLinksVueTabTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Services\Product\ProductLinkService;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL ProductLinksVueTabTest: {$message}\n");
    exit(1);
};

$assertTrue = static function (bool $cond, string $case) use ($fail): void {
    if (!$cond) {
        $fail($case);
    }
};

$assertSame = static function ($expected, $actual, string $case) use ($fail): void {
    if ($actual !== $expected) {
        $fail($case . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
};

$root = dirname(__DIR__);
$src = $root . '/src';
$vue = dirname(__DIR__, 4) . '/vueManager/src';
$assetsProduct = dirname(__DIR__, 4) . '/assets/components/minishop3/js/mgr/product';

$serviceFile = $src . '/Services/Product/ProductLinkService.php';
$assertTrue(is_file($serviceFile), 'ProductLinkService missing');

$assertSame(true, ProductLinkService::belongsToProduct(10, 10, 20), 'master match');
$assertSame(true, ProductLinkService::belongsToProduct(10, 20, 10), 'slave match');
$assertSame(false, ProductLinkService::belongsToProduct(10, 20, 30), 'unrelated pair');
$assertSame(false, ProductLinkService::belongsToProduct(0, 0, 1), 'invalid product id');

$registry = (string) file_get_contents($src . '/ServiceRegistry.php');
$assertTrue(
    str_contains($registry, 'ms3_product_link_service'),
    'ServiceRegistry missing ms3_product_link_service'
);

$routes = (string) file_get_contents($root . '/config/routes/manager.php');
$assertTrue(str_contains($routes, '/{id}/links'), 'manager.php missing product-data links');
$assertTrue(str_contains($routes, '/link-types'), 'manager.php missing references/link-types');
$assertTrue(!str_contains($routes, '/{id}/link-types'), 'product-scoped link-types must be removed');

$controller = (string) file_get_contents($src . '/Controllers/Api/ProductDataController.php');
foreach (['getLinks', 'createLink', 'removeLinks'] as $method) {
    $assertTrue(str_contains($controller, "function {$method}"), "ProductDataController::{$method}");
}
$assertTrue(
    str_contains($controller, 'ms3_err_link_not_in_product_scope'),
    'removeLinks must use lexicon for scope errors'
);
$assertTrue(
    str_contains($controller, 'ms3_err_link_batch_not_supported'),
    'removeLinks must reject batch ids[]'
);
$assertTrue(
    !str_contains($controller, 'function getLinkTypes'),
    'getLinkTypes must live on ReferencesController'
);

$refs = (string) file_get_contents($src . '/Controllers/Api/ReferencesController.php');
$assertTrue(str_contains($refs, 'function getLinkTypes'), 'ReferencesController::getLinkTypes');

$tabs = (string) file_get_contents($vue . '/components/product/ProductTabs.vue');
$assertTrue(str_contains($tabs, 'ProductLinksTab'), 'ProductTabs must mount ProductLinksTab');
$assertTrue(str_contains($tabs, 'builtInVueComponents'), 'ProductTabs must use built-in component map');
$assertTrue(!str_contains($tabs, "xtype: 'ms3-product-links'"), 'ProductTabs must not use Ext links xtype');
$assertTrue(
    !str_contains($tabs, "tab.component === 'ProductLinksTab'"),
    'ProductTabs must not use per-tab v-else-if for built-in Vue tabs'
);

$linksTab = (string) file_get_contents($vue . '/components/product/ProductLinksTab.vue');
$assertTrue(is_file($vue . '/components/product/ProductLinksTab.vue'), 'ProductLinksTab.vue missing');
$assertTrue(str_contains($linksTab, 'ConfirmDialog'), 'ConfirmDialog belongs in ProductLinksTab');
$assertTrue(str_contains($linksTab, '/api/mgr/references/link-types'), 'link-types via references');
$assertTrue(
    !str_contains($linksTab, 'ms3_menu_remove_title'),
    'confirm header must not use missing ms3_menu_remove_title (#556)'
);
$assertTrue(
    str_contains($linksTab, "header: _('ms3_menu_remove')"),
    'confirm header must reuse existing ms3_menu_remove (#556)'
);

$updateCtrl = (string) file_get_contents($root . '/controllers/product/update.class.php');
$assertTrue(
    !str_contains($updateCtrl, 'links.grid.js'),
    'update.class.php must not load Ext links.grid.js'
);

$assertTrue(!is_file($assetsProduct . '/links.grid.js'), 'orphan Ext links.grid.js must be deleted');
$assertTrue(!is_file($assetsProduct . '/links.window.js'), 'orphan Ext links.window.js must be deleted');
$assertTrue(
    !is_file($src . '/Processors/Product/ProductLink/GetList.php'),
    'orphan ProductLink\\GetList must be deleted'
);
$assertTrue(
    !is_file($src . '/Processors/Product/ProductLink/Multiple.php'),
    'orphan ProductLink\\Multiple must be deleted'
);

fwrite(STDOUT, "OK ProductLinksVueTabTest\n");
exit(0);
