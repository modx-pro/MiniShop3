<?php

/**
 * Regression #921: /references/customers закрыт правами на заказы, limit ограничен сверху.
 *
 * До правки группового view_document хватало любому редактору контента, чтобы
 * перебором двухбуквенных префиксов вытащить ФИО, почту и телефоны всех
 * покупателей, а `?limit=100000` отдавал таблицу одним вызовом.
 *
 * Запуск: php tests/ReferencesCustomersAclTest.php
 */

declare(strict_types=1);

require __DIR__ . '/stubs/ModxStub.php';
require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Controllers\Api\ReferencesController;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$assertSame = static function ($expected, $actual, string $case) use ($fail): void {
    if ($actual !== $expected) {
        $fail($case . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
};

// --- Часть 1: права на маршруте ---------------------------------------------

$routesFile = dirname(__DIR__) . '/config/routes/manager.php';
$src = file_get_contents($routesFile);
if ($src === false || $src === '') {
    $fail('cannot read manager.php routes');
}

// `(?:\s*\/\/[^\n]*)*` — внутри массива middleware лежат комментарии со ссылками
// на issue, без этого регулярка проскакивает группу и цепляет следующую.
$comments = '(?:\s*\/\/[^\n]*)*';

$groupPattern =
    "/\\\$router->group\(\s*'\/references'\s*,\s*function\s*\([^)]*\)\s*use\s*\([^)]*\)"
    . "\s*\{(.*?)\}\s*,\s*\[{$comments}\s*new\s+PermissionMiddleware\(\s*\\\$modx\s*,\s*'([^']+)'\s*\)\s*\]\s*\)\s*;/s";

if (!preg_match($groupPattern, $src, $group)) {
    $fail('no /references route group with a single PermissionMiddleware floor found');
}

$groupBody = $group[1];

// Нижний порог группы остаётся прежним: справочники товаров, поставщиков и
// опций нужны при работе с каталогом (#415). Если порог однажды поднимут
// осознанно, этот тест надо пересмотреть вместе с правкой.
$assertSame('view_document', $group[2], 'floor of /references group');

$customersPattern = "/\\\$router->get\(\s*'\/customers'\s*,(.*?)\}\s*,\s*\[(.*?)\]\s*\)\s*;/s";
if (!preg_match($customersPattern, $groupBody, $customers)) {
    $fail('/references/customers must carry its own middleware array (#921)');
}

$middlewares = $customers[2];

if (!preg_match("/new\s+AnyPermissionMiddleware\(\s*\\\$modx\s*,\s*\[(.*?)\]\s*\)/s", $middlewares, $anyPerm)) {
    $fail('/references/customers must be guarded by AnyPermissionMiddleware, got: ' . trim($middlewares));
}

preg_match_all("/'([^']+)'/", $anyPerm[1], $permMatches);
$actualPerms = $permMatches[1];
sort($actualPerms, SORT_STRING);

$expectedPerms = ['msorder_list', 'msorder_save', 'msorder_view'];
$assertSame($expectedPerms, $actualPerms, 'permissions guarding /references/customers');

// --- Часть 2: потолок автодополнения ----------------------------------------

$clamp = new ReflectionMethod(ReferencesController::class, 'clampAutocompleteLimit');
$clamp->setAccessible(true);

/** @return int */
$limitFor = static fn($raw): int => $clamp->invoke(null, $raw);

$assertSame(20, $limitFor(null), 'limit не передан');
$assertSame(20, $limitFor(''), 'пустой limit');
$assertSame(20, $limitFor('0'), 'limit=0');
$assertSame(20, $limitFor('-5'), 'отрицательный limit');
$assertSame(20, $limitFor('abc'), 'нечисловой limit');
$assertSame(5, $limitFor('5'), 'limit в пределах потолка');
$assertSame(50, $limitFor('50'), 'limit ровно по потолку');
$assertSame(50, $limitFor('1000'), 'limit выше потолка');
$assertSame(50, $limitFor('100000'), 'выгрузка всей таблицы');

// Оба автодополнения справочников берут limit через ограничитель, а не из $_GET.
$controllerSrc = file_get_contents(dirname(__DIR__) . '/src/Controllers/Api/ReferencesController.php');
if ($controllerSrc === false || $controllerSrc === '') {
    $fail('cannot read ReferencesController.php');
}

$rawLimit = preg_match_all("/\\\$limit\s*=\s*\(int\)\s*\(\s*\\\$_GET\['limit'\]/", $controllerSrc);
$assertSame(0, $rawLimit, 'необработанный $_GET[limit] в ReferencesController');

$clamped = preg_match_all('/\$limit\s*=\s*self::clampAutocompleteLimit\(/', $controllerSrc);
$assertSame(2, $clamped, 'вызовов clampAutocompleteLimit (покупатели и товары)');

fwrite(STDOUT, "OK ReferencesCustomersAclTest\n");
exit(0);
