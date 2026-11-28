<?php

/**
 * #865: shipped date format must use Y (four-digit year) and i (minutes),
 * and upgrades must repair the stored broken value without touching custom formats.
 *
 * Run: php tests/DateFormatSettingTest.php
 */

declare(strict_types=1);

$root = dirname(__DIR__, 4);
$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$settings = require $root . '/_build/elements/settings.php';
if (($settings['ms3_date_format']['value'] ?? null) !== 'd.m.Y H:i') {
    $fail('Installer must ship d.m.Y H:i (#865)');
}
foreach (['en', 'ru'] as $lang) {
    $lexicon = (string) file_get_contents($root . "/core/components/minishop3/lexicon/{$lang}/setting.inc.php");
    if (!str_contains($lexicon, 'd.m.Y H:i')) {
        $fail("{$lang} lexicon must describe the new default (#865)");
    }
}

$resolver = (string) file_get_contents($root . '/_build/resolvers/resolver_03_settings.php');
if (!str_contains($resolver, "'d.m.y H:M'") || !str_contains($resolver, "'d.m.Y H:i'")) {
    $fail('Upgrade resolver must repair the old broken value only (#865)');
}

$stamp = strtotime('2026-11-28 14:07:00');
if (date('d.m.Y H:i', $stamp) !== '28.11.2026 14:07') {
    $fail('New format must produce a four-digit year and minutes');
}

fwrite(STDOUT, "OK DateFormatSettingTest (#865)\n");
exit(0);
