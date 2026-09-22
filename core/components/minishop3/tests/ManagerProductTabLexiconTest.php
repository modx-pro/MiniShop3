<?php

/**
 * Product tab lexicon keys must stay localized in ru/manager (#758 / #766).
 *
 * English strings under the ru topic cache made Ext/Vue tabs say Product/Gallery
 * while the rest of the manager was Russian.
 *
 * Run: php tests/ManagerProductTabLexiconTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$keys = [
    'ms3_tab_product' => ['en' => 'Product', 'ru' => 'Товар'],
    'ms3_tab_product_data' => ['en' => 'Product Properties', 'ru' => 'Свойства товара'],
    'ms3_tab_product_gallery' => ['en' => 'Gallery', 'ru' => 'Галерея'],
    'ms3_tab_product_categories' => ['en' => 'Categories', 'ru' => 'Категории'],
    'ms3_tab_product_links' => ['en' => 'Links', 'ru' => 'Связи'],
];

foreach (['en', 'ru'] as $lang) {
    $path = __DIR__ . '/../lexicon/' . $lang . '/manager.inc.php';
    if (!is_readable($path)) {
        $fail("Missing lexicon file: {$path}");
    }

    $_lang = [];
    include $path;

    foreach ($keys as $key => $expected) {
        if (!array_key_exists($key, $_lang)) {
            $fail("Missing {$key} in {$lang}/manager.inc.php");
        }
        if ($_lang[$key] !== $expected[$lang]) {
            $fail(
                "{$lang}/manager.inc.php {$key}: expected '{$expected[$lang]}', got '{$_lang[$key]}'"
            );
        }
    }
}

$heal = __DIR__ . '/../controllers/lexicon_cache_heal.inc.php';
if (!is_readable($heal)) {
    $fail('Missing controllers/lexicon_cache_heal.inc.php');
}
$healSrc = file_get_contents($heal);
if ($healSrc === false || !str_contains($healSrc, 'ms3_heal_stale_manager_lexicon_cache')) {
    $fail('lexicon_cache_heal.inc.php must define ms3_heal_stale_manager_lexicon_cache');
}
if (!str_contains($healSrc, 'ms3_is_stale_lexicon_topic_cache')) {
    $fail('heal must compare cache to getFileTopic via ms3_is_stale_lexicon_topic_cache');
}
if (!str_contains($healSrc, 'MS3_LEXICON_HEAL_PENDING_KEY')) {
    $fail('heal must gate on the cache flag MS3_LEXICON_HEAL_PENDING_KEY');
}
if (!str_contains($healSrc, "MS3_LEXICON_HEAL_TOPIC = 'manager'")) {
    $fail('heal must target only the manager topic');
}
if (str_contains($healSrc, 'scandir') || str_contains($healSrc, 'modSystemSetting')) {
    $fail('heal must not scan lexicon dirs or use a system setting');
}

foreach (['resource_update.class.php', 'resource_create.class.php'] as $controller) {
    $path = __DIR__ . '/../controllers/' . $controller;
    $src = file_get_contents($path);
    if ($src === false) {
        $fail("Cannot read {$controller}");
    }
    if (!str_contains($src, 'ms3_heal_stale_manager_lexicon_cache')) {
        $fail("{$controller} must call ms3_heal_stale_manager_lexicon_cache in prepareLanguage (#766)");
    }
}

$plugin = __DIR__ . '/../elements/plugins/minishop3.php';
$pluginSrc = file_get_contents($plugin);
if ($pluginSrc === false || str_contains($pluginSrc, 'lexicon_cache_heal')) {
    $fail('plugin must not heal lexicon cache on OnManagerPageBeforeRender');
}

$resolver = dirname(__DIR__, 4) . '/_build/resolvers/resolver_11_lexicon_cache.php';
if (!is_readable($resolver)) {
    $fail('Missing _build/resolvers/resolver_11_lexicon_cache.php');
}
$resolverSrc = file_get_contents($resolver);
if ($resolverSrc === false || !str_contains($resolverSrc, 'lexicon_topics')) {
    $fail('resolver_11_lexicon_cache.php must refresh lexicon_topics');
}
if (!str_contains($resolverSrc, 'lexicon_heal_pending') || str_contains($resolverSrc, 'modSystemSetting')) {
    $fail('resolver must arm a cache flag, not a system setting');
}

$settings = dirname(__DIR__, 4) . '/_build/elements/settings.php';
$settingsSrc = file_get_contents($settings);
if ($settingsSrc === false || str_contains($settingsSrc, 'lexicon_heal_pending') || str_contains($settingsSrc, 'ms3_lexicon_cache_heal_pending')) {
    $fail('settings.php must not publish the heal flag');
}

fwrite(STDOUT, 'OK ManagerProductTabLexiconTest (' . count($keys) . " keys × 2 langs + heal wiring #766)\n");
exit(0);
