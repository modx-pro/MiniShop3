<?php

/**
 * Regression #922 (галерея): процессоры Gallery/* проверяют ACL документа.
 *
 * Права msproductfile_* ничего не знают про Resource Group конкретного товара.
 * Процессоры работают либо через msProductData, либо по id файла — ни то,
 * ни другое не является modAccessibleObject, поэтому ACL сам не применяется:
 * редактор без доступа к товару читал список его файлов, менял превью,
 * пересортировывал, перегенерировал превьюшки, переименовывал и удалял файлы.
 *
 * Поведение самого ProductDocumentPolicy проверяется в ProductDocumentAclTest.
 *
 * Запуск: php tests/GalleryDocumentAclTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$dir = __DIR__ . '/../src/Processors/Gallery';

$read = static function (string $name) use ($dir, $fail): string {
    $src = file_get_contents($dir . '/' . $name . '.php');
    if ($src === false || $src === '') {
        $fail("unable to read Gallery/{$name}.php");
    }

    return $src;
};

// --- Трейт ------------------------------------------------------------------

$trait = $read('GuardsProductDocumentTrait');

foreach (['productDocumentAllowed', 'fileDocumentAllowed', 'productDocumentDeniedMessage'] as $method) {
    if (!str_contains($trait, 'function ' . $method . '(')) {
        $fail("GuardsProductDocumentTrait must expose {$method}() (#922)");
    }
}

if (!str_contains($trait, 'ProductDocumentPolicy::isAllowed(')) {
    $fail('GuardsProductDocumentTrait must delegate to ProductDocumentPolicy (#922)');
}

// Товар у файла берётся из product_id, иначе проверять нечего.
if (!str_contains($trait, "\$file->get('product_id')")) {
    $fail('fileDocumentAllowed() must resolve the product from the file (#922)');
}

// product_id = 0 — это «документа нет», а не «закрытый товар»: так выглядят
// файлы-сироты и выборка GetList по product_id = 0, чей запрос фильтрует
// ровно ноль и ничего чужого не отдаёт. Отказ здесь ломал живой
// ProcessorCoverageTest и удаление сирот через интерфейс.
if (
    preg_match(
        '/function productDocumentAllowed\([\s\S]*?if \(\$productId === 0\) \{\s*return true;/',
        $trait
    ) !== 1
) {
    $fail('productDocumentAllowed() must treat product_id = 0 as "no document to check" (#922)');
}

// --- Процессоры, работающие по product_id ----------------------------------

$byProductId = [
    'GetList' => 'POLICY_VIEW',
    'SetPreview' => null,
    'SortByName' => null,
    'RemoveAll' => null,
    'GenerateAll' => null,
    'Sort' => null,
];

foreach ($byProductId as $name => $policy) {
    $src = $read($name);

    if (!str_contains($src, 'use GuardsProductDocumentTrait;')) {
        $fail("Gallery/{$name} must use GuardsProductDocumentTrait (#922)");
    }

    if (!str_contains($src, 'productDocumentAllowed(')) {
        $fail("Gallery/{$name} must check productDocumentAllowed() (#922)");
    }

    // GetList только читает, поэтому ему view, остальным хватает умолчания save.
    if ($policy !== null && !str_contains($src, 'ProductDocumentPolicy::' . $policy)) {
        $fail("Gallery/{$name} must check {$policy} (#922)");
    }
}

// --- Процессоры, работающие по id файла ------------------------------------

foreach (['Generate', 'Remove', 'Update'] as $name) {
    $src = $read($name);

    if (!str_contains($src, 'use GuardsProductDocumentTrait;')) {
        $fail("Gallery/{$name} must use GuardsProductDocumentTrait (#922)");
    }

    if (!str_contains($src, 'fileDocumentAllowed(')) {
        $fail("Gallery/{$name} must resolve the product from the file id (#922)");
    }
}

// --- Порядок проверок -------------------------------------------------------

// Remove::process() и Update::afterSave() не смотрят на результат родителя,
// поэтому проверка обязана стоять в initialize(): оттуда отказ останавливает
// процессор штатно.
foreach (['Remove', 'Update'] as $name) {
    $src = $read($name);

    if (
        preg_match(
            '/function initialize\(\)[\s\S]*?fileDocumentAllowed\([\s\S]*?return \$this->productDocumentDeniedMessage\(\)/',
            $src
        ) !== 1
    ) {
        $fail("Gallery/{$name} must deny inside initialize(), not later (#922)");
    }
}

// RemoveAll сносил файлы до того, как вообще загружал товар.
$removeAll = $read('RemoveAll');
if (
    preg_match(
        '/productDocumentAllowed\([\s\S]*?getCollection\(msProductFile::class/',
        $removeAll
    ) !== 1
) {
    $fail('Gallery/RemoveAll must check access before deleting files (#922)');
}

// --- Sort: принадлежность файлов и падение на null -------------------------

$sort = $read('Sort');

if (
    preg_match(
        "/\\\$source->get\('product_id'\)[\s\S]*?!==[\s\S]*?\\\$product_id/",
        $sort
    ) !== 1
) {
    $fail('Gallery/Sort must verify the source file belongs to the product (#922)');
}

if (
    preg_match(
        "/\\\$target->get\('product_id'\)[\s\S]*?!==[\s\S]*?\\\$product_id/",
        $sort
    ) !== 1
) {
    $fail('Gallery/Sort must verify the target file belongs to the product (#922)');
}

// ->get('id') звался до проверки на null и падал на отсутствующем товаре.
if (
    preg_match(
        "/\\\$product = \\\$this->modx->getObject\(msProductData::class[\s\S]*?"
        . "if \(empty\(\\\$source\) \|\| empty\(\\\$target\) \|\| !\\\$product\)[\s\S]*?"
        . "\\\$product_id = \(int\) \\\$product->get\('id'\)/",
        $sort
    ) !== 1
) {
    $fail('Gallery/Sort must null-check the product before calling get() (#922)');
}

// --- Multiple: белый список подпроцессоров ---------------------------------

$multiple = $read('Multiple');

if (!str_contains($multiple, 'ALLOWED_METHODS')) {
    $fail('Gallery/Multiple must whitelist sub-processor names (#922)');
}

if (
    preg_match(
        '/in_array\(\$method, self::ALLOWED_METHODS, true\)[\s\S]*?runProcessor\(/',
        $multiple
    ) !== 1
) {
    $fail('Gallery/Multiple must check the whitelist before dispatching (#922)');
}

// --- Лексиконы на двух языках ----------------------------------------------

foreach (['ru', 'en'] as $lang) {
    $lex = file_get_contents(__DIR__ . "/../lexicon/{$lang}/default.inc.php");
    if ($lex === false) {
        $fail("unable to read lexicon/{$lang}/default.inc.php");
    }

    foreach (['ms3_gallery_err_no_product', 'ms3_gallery_err_file_not_in_product'] as $key) {
        if (!str_contains($lex, "\$_lang['{$key}']")) {
            $fail("lexicon {$lang} must define {$key} (#922)");
        }
    }
}

fwrite(STDOUT, "OK GalleryDocumentAclTest\n");
exit(0);
