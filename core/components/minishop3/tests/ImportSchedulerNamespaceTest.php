<?php

/**
 * Regression #904: CSV-импорт ищет задачу Scheduler в правильном namespace.
 *
 * Установщик (resolver_07_scheduler.php) регистрирует задачу с namespace `minishop3`.
 * Процессор импорта должен искать задачу тем же namespace, а не `MiniShop3`.
 * Если задача не найдена, процессор создаёт fallback с тем же namespace и путём.
 *
 * Запуск: php tests/ImportSchedulerNamespaceTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

// 1. Проверка resolver_07_scheduler.php — namespace должен быть lowercase 'minishop3'
$resolverPath = dirname(__DIR__, 4) . '/_build/resolvers/resolver_07_scheduler.php';
$resolverSrc = is_readable($resolverPath) ? file_get_contents($resolverPath) : false;
if ($resolverSrc === false) {
    $fail('cannot read resolver_07_scheduler.php');
}

if (!str_contains($resolverSrc, "'namespace' => 'minishop3'")) {
    $fail('resolver_07_scheduler.php must create tasks with namespace "minishop3" (lowercase)');
}

// 2. getTask в resolver ищет по 'minishop3'
if (!str_contains($resolverSrc, "getTask('minishop3'")) {
    $fail('resolver_07_scheduler.php must lookup tasks with namespace "minishop3"');
}

// 3. Проверка процессора Import.php — getTask должен искать по 'minishop3'
$importPath = dirname(__DIR__) . '/src/Processors/Utilities/Import/Import.php';
$importSrc = is_readable($importPath) ? file_get_contents($importPath) : false;
if ($importSrc === false) {
    $fail('cannot read Import.php');
}

// getTask должен использовать 'minishop3' (lowercase), а не 'MiniShop3'
if (preg_match("/getTask\\s*\\(\\s*['\"]MiniShop3['\"]\\s*,/", $importSrc)) {
    $fail('Import.php must NOT use namespace "MiniShop3" in getTask (#904). Use "minishop3".');
}

if (!preg_match("/getTask\\s*\\(\\s*['\"]minishop3['\"]\\s*,\\s*['\"]ms3_csv_import['\"]\\s*\\)/", $importSrc)) {
    $fail('Import.php must call getTask("minishop3", "ms3_csv_import") to match resolver (#904)');
}

// 4. createImportTask должен создавать задачу с namespace 'minishop3'
if (preg_match("/['\"]namespace['\"]\\s*=>\\s*['\"]MiniShop3['\"]/", $importSrc)) {
    $fail('Import::createImportTask must create task with namespace "minishop3" (lowercase) (#904)');
}

if (!preg_match("/['\"]namespace['\"]\\s*=>\\s*['\"]minishop3['\"]/", $importSrc)) {
    $fail('Import::createImportTask must create task with namespace "minishop3" (#904)');
}

// 5. Путь к контенту должен быть без ведущего слеша (чтобы совпадать с resolver)
if (preg_match("/['\"]content['\"]\\s*=>\\s*['\"]\\/elements\\/tasks\\/csvImport.php['\"]/", $importSrc)) {
    $fail('Import::createImportTask must NOT use leading slash in content path "#/elements/tasks/csvImport.php" (#904)');
}

if (!preg_match("/['\"]content['\"]\\s*=>\\s*['\"]elements\\/tasks\\/csvImport.php['\"]/", $importSrc)) {
    $fail('Import::createImportTask must use content path "elements/tasks/csvImport.php" (no leading slash) to match resolver (#904)');
}

fwrite(STDOUT, "OK ImportSchedulerNamespaceTest\n");
exit(0);
