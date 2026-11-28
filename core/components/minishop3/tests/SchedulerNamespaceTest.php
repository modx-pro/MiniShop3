<?php

/**
 * #904: every Scheduler task uses the lowercase 'minishop3' namespace and
 * pre-fix 'MiniShop3' tasks are cleaned up on upgrade.
 *
 * Run: php tests/SchedulerNamespaceTest.php
 */

declare(strict_types=1);

$root = dirname(__DIR__, 4);
$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$read = static function (string $relative) use ($root, $fail): string {
    $path = $root . $relative;
    if (!is_readable($path)) {
        $fail("Missing {$path}");
    }

    return (string) file_get_contents($path);
};

$import = $read('/core/components/minishop3/src/Processors/Utilities/Import/Import.php');
if (!str_contains($import, "getTask('minishop3', 'ms3_csv_import')")) {
    $fail('Import processor must look up the lowercase task namespace (#904)');
}
if (!str_contains($import, "'namespace' => 'minishop3'")) {
    $fail('createImportTask() must create the task under minishop3 (#904)');
}

$resolver = $read('/_build/resolvers/resolver_07_scheduler.php');
if (!str_contains($resolver, "'namespace' => 'MiniShop3'")) {
    $fail('Resolver must detect legacy MiniShop3 tasks for upgrade cleanup (#904)');
}
if (!str_contains($resolver, "set('namespace', 'minishop3')")) {
    $fail('Resolver must rename legacy tasks instead of leaving stale duplicates (#904)');
}

$settings = $read('/_build/resolvers/resolver_03_settings.php');
if (str_contains($settings, "'namespace' => 'MiniShop3'")) {
    $fail('Uninstall must remove settings from the minishop3 namespace (#904)');
}

fwrite(STDOUT, "OK SchedulerNamespaceTest (#904)\n");
exit(0);
