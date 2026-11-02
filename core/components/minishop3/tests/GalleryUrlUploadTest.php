<?php

/**
 * Фича «загрузка картинки по ссылке»: обвязка на месте.
 *
 * Возможность существовала и раньше, но как побочный эффект `fopen($url)`
 * без проверок (#923). Здесь проверяется, что восстановлена она уже
 * с защитой, а не возвратом прежнего поведения.
 *
 * Решение «внутренний адрес или внешний» проверяется поведением
 * в PublicHostPolicyTest.
 *
 * Запуск: php tests/GalleryUrlUploadTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$read = static function (string $path) use ($fail): string {
    $src = file_get_contents(__DIR__ . '/../' . $path);
    if ($src === false || $src === '') {
        $fail("unable to read {$path}");
    }

    return $src;
};

$fetcher = $read('src/Services/Product/RemoteImageFetcher.php');
$upload = $read('src/Processors/Gallery/Upload.php');

// --- Защита от SSRF --------------------------------------------------------

if (!str_contains($fetcher, 'PublicHostPolicy::resolveHost(')) {
    $fail('fetcher must resolve the host itself before connecting');
}

if (!str_contains($fetcher, 'PublicHostPolicy::isPublicIp(')) {
    $fail('fetcher must check every resolved address');
}

// Отказ целиком, если хотя бы один адрес внутренний: выбирать «хороший»
// из набора — это и есть лазейка.
if (
    preg_match(
        '/foreach \(\$ips as \$ip\) \{\s*if \(!PublicHostPolicy::isPublicIp\(\$ip\)\) \{\s*return \$this->fail\(/',
        $fetcher
    ) !== 1
) {
    $fail('fetcher must reject when any resolved address is internal');
}

// Закрепление адреса. Без него проверка бесполезна: имя переразрешается
// между проверкой и подключением (DNS rebinding).
if (!str_contains($fetcher, 'CURLOPT_RESOLVE')) {
    $fail('fetcher must pin the validated address via CURLOPT_RESOLVE');
}

if (preg_match('/CURLOPT_FOLLOWLOCATION => false/', $fetcher) !== 1) {
    $fail('fetcher must not follow redirects automatically — each hop needs revalidation');
}

if (!str_contains($fetcher, 'MAX_REDIRECTS')) {
    $fail('fetcher must cap the number of redirect hops');
}

// Протоколы, учётные данные в ссылке, таймауты.
if (!str_contains($fetcher, 'CURLPROTO_HTTP | CURLPROTO_HTTPS')) {
    $fail('fetcher must restrict curl protocols to http and https');
}

if (
    preg_match("/in_array\(\\\$scheme, \['http', 'https'\], true\)/", $fetcher) !== 1
) {
    $fail('fetcher must accept only http and https schemes');
}

if (preg_match("/isset\(\\\$parts\['user'\]\) \|\| isset\(\\\$parts\['pass'\]\)/", $fetcher) !== 1) {
    $fail('fetcher must reject credentials embedded in the url');
}

foreach (['CURLOPT_CONNECTTIMEOUT', 'CURLOPT_TIMEOUT'] as $option) {
    if (!str_contains($fetcher, $option)) {
        $fail("fetcher must set {$option}");
    }
}

// Объём обрывается в обработчике записи: Content-Length может отсутствовать
// и может врать.
if (
    preg_match(
        '/CURLOPT_WRITEFUNCTION[\s\S]*?\$written \+= strlen\(\$chunk\)[\s\S]*?'
        . '\$written > \$maxBytes[\s\S]*?return 0;/',
        $fetcher
    ) !== 1
) {
    $fail('fetcher must abort the transfer from the write callback on size overflow');
}

// --- Подключение к процессору ---------------------------------------------

// Ссылки идут отдельным параметром: `file` остался локальным, его использует
// CSV-импорт, и расширять его до ссылок нельзя.
if (preg_match("/\\\$url = trim\(\(string\) \\\$this->getProperty\('url', ''\)\)/", $upload) !== 1) {
    $fail('Upload must take the remote source from a separate `url` property');
}

if (str_contains($upload, "resolveLocalSource((string) \$this->getProperty('url'")) {
    $fail('the local-path guard must not be fed the url property');
}

// Выключатель для установок, которым нельзя ходить наружу.
if (!str_contains($upload, 'ms3_gallery_allow_url_upload')) {
    $fail('Upload must honour the ms3_gallery_allow_url_upload setting');
}

if (
    preg_match(
        '/ms3_gallery_allow_url_upload[\s\S]{0,200}?ms3_err_gallery_url_disabled/',
        $upload
    ) !== 1
) {
    $fail('a disabled setting must produce the dedicated message');
}

// --- Настройка и сервис зарегистрированы ----------------------------------

$settings = file_get_contents(__DIR__ . '/../../../../_build/elements/settings.php');
if ($settings === false || !str_contains($settings, 'ms3_gallery_allow_url_upload')) {
    $fail('_build/elements/settings.php must declare ms3_gallery_allow_url_upload');
}

// Включена по умолчанию: приёмом пользовались до того, как он стал фичей.
if (
    preg_match(
        "/'ms3_gallery_allow_url_upload' => \[\s*'value' => true/",
        (string) $settings
    ) !== 1
) {
    $fail('the setting must default to on');
}

$registry = $read('src/ServiceRegistry.php');
if (!str_contains($registry, 'ms3_remote_image_fetcher')) {
    $fail('ServiceRegistry must register ms3_remote_image_fetcher');
}

// --- Сообщения на двух языках ---------------------------------------------

$keys = [
    'ms3_err_gallery_url_disabled',
    'ms3_err_gallery_url_invalid',
    'ms3_err_gallery_url_scheme',
    'ms3_err_gallery_url_unresolved',
    'ms3_err_gallery_url_private',
    'ms3_err_gallery_url_too_many_redirects',
    'ms3_err_gallery_url_failed',
    'ms3_err_gallery_url_no_curl',
];

foreach (['ru', 'en'] as $lang) {
    $lex = $read("lexicon/{$lang}/default.inc.php");
    foreach ($keys as $key) {
        if (!str_contains($lex, "\$_lang['{$key}']")) {
            $fail("lexicon {$lang} must define {$key}");
        }
    }

    $setting = $read("lexicon/{$lang}/setting.inc.php");
    foreach (
        [
            'setting_ms3_gallery_allow_url_upload',
            'setting_ms3_gallery_allow_url_upload_desc',
        ] as $key
    ) {
        if (!str_contains($setting, "\$_lang['{$key}']")) {
            $fail("lexicon {$lang} must describe {$key}");
        }
    }
}

// Все ключи, которые возвращает загрузчик, должны существовать.
preg_match_all("/\\\$this->fail\('([a-z0-9_]+)'/", $fetcher, $matches);
$ruLex = $read('lexicon/ru/default.inc.php');
foreach (array_unique($matches[1]) as $key) {
    if (!str_contains($ruLex, "\$_lang['{$key}']")) {
        $fail("fetcher returns {$key}, but the lexicon has no such key");
    }
}

fwrite(STDOUT, "OK GalleryUrlUploadTest\n");
exit(0);
