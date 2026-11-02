<?php

/**
 * Regression #923: Gallery/Upload больше не ходит по ссылкам и не выходит
 * за каталог сайта, а размер и число пикселей ограничены.
 *
 * Процессор принимал параметр `file` и открывал его через fopen(), если строка
 * содержала `://` либо указывала на существующий файл. Это давало SSRF (сервер
 * делал запрос по любому адресу, который прислал пользователь), чтение
 * локальных файлов, в том числе вне каталога сайта, и копирование потока
 * до EOF без ограничения по размеру.
 *
 * Запуск: php tests/GalleryUploadSourceTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Utils\PathBoundary;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$assertSame = static function ($expected, $actual, string $case) use ($fail): void {
    if ($actual !== $expected) {
        $fail($case . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
};

// --- Часть 1: граница каталога, поведением ---------------------------------

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ms3_boundary_' . getmypid();
$base = $tmp . DIRECTORY_SEPARATOR . 'site';
$sibling = $tmp . DIRECTORY_SEPARATOR . 'site-evil';

@mkdir($base . DIRECTORY_SEPARATOR . 'assets', 0777, true);
@mkdir($sibling, 0777, true);

$inside = $base . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'ok.png';
$outside = $sibling . DIRECTORY_SEPARATOR . 'secret.png';

file_put_contents($inside, 'inside');
file_put_contents($outside, 'outside');

$cleanup = static function () use ($inside, $outside, $base, $sibling, $tmp): void {
    @unlink($inside);
    @unlink($outside);
    @rmdir($base . DIRECTORY_SEPARATOR . 'assets');
    @rmdir($base);
    @rmdir($sibling);
    @rmdir($tmp);
};

$resolvedInside = PathBoundary::resolveInside($inside, $base);
if ($resolvedInside === null) {
    $cleanup();
    $fail('файл внутри каталога должен разрешаться');
}

// Главная ловушка: realpath() срезает завершающий разделитель, и сравнение
// префиксов без него пропускает каталог-сосед — именно на этом ошибается
// ImportCsvPathGuard (#924).
$resolvedSibling = PathBoundary::resolveInside($outside, $base);
$assertSame(null, $resolvedSibling, 'каталог-сосед с общим началом имени');

$assertSame(
    null,
    PathBoundary::resolveInside($base . DIRECTORY_SEPARATOR . 'assets/../../site-evil/secret.png', $base),
    'выход через ..'
);
$assertSame(null, PathBoundary::resolveInside($base . '/нет-такого.png', $base), 'несуществующий путь');
$assertSame(null, PathBoundary::resolveInside('', $base), 'пустой путь');
$assertSame(null, PathBoundary::resolveInside($inside, ''), 'пустой базовый каталог');

// Сам каталог своей границей не считается: внутрь — значит строго ниже.
$assertSame(null, PathBoundary::resolveInside($base, $base), 'сам базовый каталог');

$assertSame(
    true,
    PathBoundary::isInside($base . DIRECTORY_SEPARATOR . 'a.png', $base),
    'isInside: внутри'
);
$assertSame(false, PathBoundary::isInside($base . '-evil' . DIRECTORY_SEPARATOR . 'a.png', $base), 'isInside: сосед');

$cleanup();

// --- Часть 2: защиты подключены в процессоре -------------------------------

$src = file_get_contents(__DIR__ . '/../src/Processors/Gallery/Upload.php');
if ($src === false || $src === '') {
    $fail('unable to read Gallery/Upload.php');
}

// Загрузки по ссылке больше нет: ни http, ни обёрток php:// и data://.
if (preg_match('/str_contains\(\$file, \'\:\/\/\'\)/', $src) !== 1) {
    $fail('Upload must reject any source containing a scheme (#923)');
}

// Прежнее условие пускало в fopen() всё, что содержало `://`.
if (preg_match('/strpos\(\$file, \'\:\/\/\'\) !== false/', $src) === 1) {
    $fail('Upload must not open remote sources any more (#923)');
}

if (!str_contains($src, 'PathBoundary::resolveInside(')) {
    $fail('Upload must confine the local source under base path via PathBoundary (#923)');
}

// Ограничение размера берётся из медиа-источника и применяется на обоих путях.
if (!str_contains($src, "\$properties['maxUploadSize']")) {
    $fail('Upload must read maxUploadSize from the media source (#923)');
}

if (
    preg_match(
        '/function copyWithSizeLimit[\s\S]*?\$written \+= strlen\(\$chunk\)[\s\S]*?'
        . '\$written > \$maxSize/',
        $src
    ) !== 1
) {
    $fail('copyWithSizeLimit() must abort mid-stream, not after the copy (#923)');
}

if (
    preg_match('/filesize\(\$tf\) > \$maxSize/', $src) !== 1
) {
    $fail('Upload must also size-check the multipart path (#923)');
}

// Потолок пикселей — предохранитель против декомпрессионных бомб.
if (!str_contains($src, 'MAX_IMAGE_PIXELS')) {
    $fail('Upload must cap total pixels before decoding (#923)');
}

if (preg_match('/withinPixelCeiling\(/', $src) !== 1) {
    // Один вызов — это объявление; нужны ещё и применения.
    $fail('withinPixelCeiling() must be declared (#923)');
}

$ceilingCalls = preg_match_all('/\$this->withinPixelCeiling\(/', $src);
if ($ceilingCalls < 3) {
    $fail("pixel ceiling must guard every path that learns the dimensions, got {$ceilingCalls} (#923)");
}

// --- Часть 3: сообщения на двух языках -------------------------------------

foreach (['ru', 'en'] as $lang) {
    $lex = file_get_contents(__DIR__ . "/../lexicon/{$lang}/default.inc.php");
    if ($lex === false) {
        $fail("unable to read lexicon/{$lang}/default.inc.php");
    }

    foreach (
        [
            'ms3_err_gallery_remote_source',
            'ms3_err_gallery_source_not_found',
            'ms3_err_gallery_source_outside_base',
            'ms3_err_gallery_too_large',
            'ms3_err_gallery_too_many_pixels',
        ] as $key
    ) {
        if (!str_contains($lex, "\$_lang['{$key}']")) {
            $fail("lexicon {$lang} must define {$key} (#923)");
        }
    }
}

fwrite(STDOUT, "OK GalleryUploadSourceTest\n");
exit(0);
