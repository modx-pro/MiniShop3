<?php

/**
 * Regression #922: ACL документа для операций над товаром по id.
 *
 * msProduct наследует modResource, то есть modAccessibleObject, поэтому
 * getObject() сам отказывает по Resource Group — getProductData() и
 * updateProductData() были защищены этим без единой явной проверки.
 * А связи товаров и дерево категорий работали по сырому id и проходили
 * мимо прав: редактор без доступа к товару создавал связи к нему и от него,
 * а листинг связей открытого товара отдавал pagetitle закрытого.
 *
 * Запуск: php tests/ProductDocumentAclTest.php
 */

declare(strict_types=1);

require __DIR__ . '/stubs/ModxStub.php';
require __DIR__ . '/stubs/StubMsProduct.php';
require __DIR__ . '/stubs/ProductDocumentPolicyModxStub.php';
require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Services\Product\ProductDocumentPolicy;
use MiniShop3\Tests\Stubs\ProductDocumentPolicyModxStub;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$assertSame = static function ($expected, $actual, string $case) use ($fail): void {
    if ($actual !== $expected) {
        $fail($case . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
};

// --- Часть 1: поведение хелпера -------------------------------------------

$modx = new ProductDocumentPolicyModxStub();
$modx->products = [
    10 => null,                                  // доступен полностью
    11 => ['view' => true, 'save' => false],     // читать можно, писать нет
    12 => ['view' => false, 'save' => false],    // закрыт
    // 13 отсутствует — getObject() вернёт null, как для закрытого ресурса
];

$assertSame(true, ProductDocumentPolicy::isAllowed($modx, 10, ProductDocumentPolicy::POLICY_VIEW), 'открытый товар, view');
$assertSame(true, ProductDocumentPolicy::isAllowed($modx, 10, ProductDocumentPolicy::POLICY_SAVE), 'открытый товар, save');
$assertSame(true, ProductDocumentPolicy::isAllowed($modx, 11, ProductDocumentPolicy::POLICY_VIEW), 'только чтение, view');
$assertSame(false, ProductDocumentPolicy::isAllowed($modx, 11, ProductDocumentPolicy::POLICY_SAVE), 'только чтение, save');
$assertSame(false, ProductDocumentPolicy::isAllowed($modx, 12, ProductDocumentPolicy::POLICY_VIEW), 'закрытый товар, view');
$assertSame(false, ProductDocumentPolicy::isAllowed($modx, 13, ProductDocumentPolicy::POLICY_VIEW), 'товара нет, view');
$assertSame(false, ProductDocumentPolicy::isAllowed($modx, 0, ProductDocumentPolicy::POLICY_VIEW), 'нулевой id');
$assertSame(false, ProductDocumentPolicy::isAllowed($modx, -5, ProductDocumentPolicy::POLICY_VIEW), 'отрицательный id');

$assertSame(
    true,
    ProductDocumentPolicy::isAllowedAll($modx, [10, 10], ProductDocumentPolicy::POLICY_SAVE),
    'isAllowedAll: оба доступны'
);
$assertSame(
    false,
    ProductDocumentPolicy::isAllowedAll($modx, [10, 12], ProductDocumentPolicy::POLICY_SAVE),
    'isAllowedAll: одна сторона закрыта'
);
$assertSame(
    false,
    ProductDocumentPolicy::isAllowedAll($modx, [10, 13], ProductDocumentPolicy::POLICY_SAVE),
    'isAllowedAll: одной стороны нет'
);

// Нулевой id не должен уходить в getObject().
$modx->lookups = [];
ProductDocumentPolicy::isAllowed($modx, 0, ProductDocumentPolicy::POLICY_VIEW);
$assertSame([], $modx->lookups, 'нулевой id не ходит в getObject');

// --- Часть 2: guard подключён на всех точках входа ------------------------

$controllerSrc = file_get_contents(__DIR__ . '/../src/Controllers/Api/ProductDataController.php');
if ($controllerSrc === false) {
    $fail('unable to read ProductDataController.php');
}

// Непроверяющего помощника больше нет: иначе новый маршрут снова возьмёт сырой id.
if (str_contains($controllerSrc, 'requireProductId')) {
    $fail('ProductDataController must not keep an unchecked requireProductId() (#922)');
}

foreach (
    [
        'getLinks' => 'POLICY_VIEW',
        'getCategoriesTree' => 'POLICY_VIEW',
        'createLink' => 'POLICY_SAVE',
        'removeLinks' => 'POLICY_SAVE',
    ] as $method => $policy
) {
    $pattern = '/function\s+' . $method . '\(array \$params\): Response\s*\{\s*'
        . '\$productId = \$this->requireProduct\(\$params, ProductDocumentPolicy::' . $policy . '\)/';

    if (preg_match($pattern, $controllerSrc) !== 1) {
        $fail("{$method}() must gate on requireProduct(..., {$policy}) (#922)");
    }
}

// Вторая сторона связи проверяется отдельно: меняется и она.
if (
    preg_match(
        '/function createLink[\s\S]*?\$slave = \(int\)[\s\S]*?ProductDocumentPolicy::isAllowed\('
        . '[\s\S]*?\$slave,[\s\S]*?POLICY_SAVE\)/',
        $controllerSrc
    ) !== 1
) {
    $fail('createLink() must check the slave product too (#922)');
}

$serviceSrc = file_get_contents(__DIR__ . '/../src/Services/Product/ProductLinkService.php');
if ($serviceSrc === false) {
    $fail('unable to read ProductLinkService.php');
}

// Сюда ведёт ещё и legacy-процессор с сырыми id, поэтому guard нужен в сервисе.
if (
    preg_match(
        '/function create\([\s\S]*?meshParticipants\([\s\S]*?isAllowedAll\([\s\S]*?POLICY_SAVE[\s\S]*?foreach \(\$pairs/',
        $serviceSrc
    ) !== 1
) {
    $fail('ProductLinkService::create() must check every affected product before writing (#922)');
}

if (
    preg_match(
        '/function remove\([\s\S]*?isAllowedAll\([\s\S]*?\[\$master, \$slave\][\s\S]*?POLICY_SAVE[\s\S]*?command\(.DELETE.\)/',
        $serviceSrc
    ) !== 1
) {
    $fail('ProductLinkService::remove() must check both sides before deleting (#922)');
}

// Листинг: leftJoin ACL не применяет, строки с закрытой стороной убираются в PHP.
if (
    preg_match(
        '/function listForProduct\([\s\S]*?isAllowedAll\([\s\S]*?POLICY_VIEW[\s\S]*?array_slice\(/',
        $serviceSrc
    ) !== 1
) {
    $fail('listForProduct() must drop rows whose counterpart is not viewable (#922)');
}

// total считается после фильтра, иначе страницы и счётчик разойдутся.
if (preg_match('/\$total = \(int\) \$this->modx->getCount\(msProductLink::class/', $serviceSrc) === 1) {
    $fail('listForProduct() must not report a pre-filter total (#922)');
}

// Сообщение об отказе есть на двух языках.
foreach (['ru', 'en'] as $lang) {
    $lex = file_get_contents(__DIR__ . "/../lexicon/{$lang}/default.inc.php");
    if ($lex === false || !str_contains($lex, "ms3_err_product_not_found")) {
        $fail("lexicon {$lang}/default.inc.php must define ms3_err_product_not_found (#922)");
    }
}

fwrite(STDOUT, "OK ProductDocumentAclTest\n");
exit(0);
