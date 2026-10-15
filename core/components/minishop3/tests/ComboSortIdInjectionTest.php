<?php

/**
 * Regression #882: id из запроса не склеивается с SQL в combo-процессорах.
 *
 * Оба процессора ставят выбранную строку первой через ORDER BY FIELD(id, …).
 * Выражение собирается строкой, поэтому параметр `id` должен быть приведён к
 * списку положительных целых, а не подставлен как есть.
 *
 * Запуск: php tests/ComboSortIdInjectionTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Utils\IntArrayDecoder;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$srcDir = dirname(__DIR__) . '/src/Processors';

/**
 * Процессор => колонка, по которой он строит FIELD().
 *
 * @var array<string, string>
 */
$processors = [
    'Customer/GetListCombobox.php' => 'msCustomer.id',
    'System/User/GetList.php' => 'modUser.id',
];

foreach ($processors as $relative => $column) {
    $path = $srcDir . '/' . $relative;
    $source = is_readable($path) ? file_get_contents($path) : false;
    if ($source === false) {
        $fail('cannot read ' . $relative);
    }

    if (!str_contains($source, 'FIELD (' . $column)) {
        $fail("{$relative}: expected an ORDER BY FIELD({$column}, …) to guard");
    }

    // Интерполяция переменной внутри строки запроса — ровно то, что чинил #882.
    if (preg_match('/FIELD\s*\(' . preg_quote($column, '/') . ',\s*\{?\$/', $source)) {
        $fail("{$relative}: id must not be interpolated into the FIELD() expression (#882)");
    }

    if (!str_contains($source, 'IntArrayDecoder::decode')) {
        $fail("{$relative}: id must be normalised through IntArrayDecoder::decode() (#882)");
    }
}

// Поведение нормализатора, на котором держится защита. Пары вида
// [вход, ожидание] — именно списком, иначе строковые ключи массива PHP
// привёл бы к int и часть случаев подменилась.
$cases = [
    ['5', [5]],
    [5, [5]],
    ['5,7', [5, 7]],
    // Мусор отбрасывается целиком: is_numeric() ложно для всей строки,
    // поэтому «подчищенного» остатка вроде 5 в списке не появляется.
    ['5) UNION SELECT 1 -- ', []],
    ['9) OR 1=1 -- ', []],
    ['9) OR 1=1 -- ,4', [4]],
    ['abc', []],
    ['0', []],
    ['-3', []],
    ['', []],
    [null, []],
    ['1,1,2', [1, 2]],
    [[3, '4', 'x'], [3, 4]],
];

foreach ($cases as [$input, $expected]) {
    $actual = IntArrayDecoder::decode($input);
    if ($actual !== $expected) {
        $fail(sprintf(
            'IntArrayDecoder::decode(%s): expected %s, got %s',
            var_export($input, true),
            json_encode($expected),
            json_encode($actual)
        ));
    }
}

// Собранное выражение не должно содержать ничего, кроме цифр и запятых.
$list = implode(',', IntArrayDecoder::decode('9) OR 1=1 -- ,4'));
if (!preg_match('/^[0-9,]*$/', $list)) {
    $fail('normalised id list must contain digits and commas only, got: ' . $list);
}

fwrite(STDOUT, "OK ComboSortIdInjectionTest\n");
exit(0);
