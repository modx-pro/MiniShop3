<?php

/**
 * #817: the thank-you page must not link through the non-existent ms3.page_id.catalog setting.
 * Catalog page id comes from the package setting ms3_catalog_page_id with a site_start fallback.
 *
 * Standalone (no MODX). Run: php tests/ThanksPageCatalogLinkTest.php
 */

declare(strict_types=1);

$component = dirname(__DIR__);
$root = dirname($component, 3);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$assertTrue = static function (bool $condition, string $case) use ($fail): void {
    if (!$condition) {
        $fail($case);
    }
};

$thanks = (string) file_get_contents($component . '/elements/templates/thanks.tpl');
$assertTrue(!str_contains($thanks, '[[++ms3.page_id.catalog'), 'thanks.tpl drops the undefined setting tag');
$assertTrue(str_contains($thanks, "'ms3_catalog_page_id' | option"), 'thanks.tpl reads ms3_catalog_page_id');
$assertTrue(str_contains($thanks, "'site_start' | option"), 'thanks.tpl falls back to site_start');
$assertTrue(
    str_contains($thanks, '{set $continue_page_id = $catalog_page_id > 0 ? $catalog_page_id : $site_start_id}'),
    'thanks.tpl uses the configured id when positive'
);
$assertTrue(str_contains($thanks, '{$continue_page_id | url}'), 'thanks.tpl renders a URL from the id');

$settings = (string) file_get_contents($root . '/_build/elements/settings.php');
$assertTrue(
    str_contains($settings, "'ms3_catalog_page_id' => [\n        'value' => 0,"),
    'settings.php declares ms3_catalog_page_id with a safe default'
);

foreach (['ru', 'en'] as $lang) {
    $lexicon = (string) file_get_contents($component . "/lexicon/{$lang}/setting.inc.php");
    $assertTrue(
        str_contains($lexicon, "\$_lang['setting_ms3_catalog_page_id']"),
        "{$lang} lexicon has setting_ms3_catalog_page_id"
    );
    $assertTrue(
        str_contains($lexicon, "\$_lang['setting_ms3_catalog_page_id_desc']"),
        "{$lang} lexicon has setting_ms3_catalog_page_id_desc"
    );
}

fwrite(STDOUT, "OK ThanksPageCatalogLinkTest\n");
exit(0);
