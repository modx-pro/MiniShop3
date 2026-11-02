<?php

/**
 * Загрузка картинки по ссылке: решение «внутренний адрес или внешний».
 *
 * Единственное место, которое это решает, и ошибка здесь означает SSRF —
 * ровно то, что было в Gallery/Upload до #923, когда ссылка уходила
 * в fopen() без всяких проверок.
 *
 * Запуск: php tests/PublicHostPolicyTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Utils\PublicHostPolicy;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$check = static function (string $ip, bool $expected, string $note) use ($fail): void {
    $got = PublicHostPolicy::isPublicIp($ip);
    if ($got !== $expected) {
        $fail(
            sprintf(
                '%s (%s): ожидалось %s, получено %s',
                $ip === '' ? '<пусто>' : $ip,
                $note,
                $expected ? 'внешний' : 'внутренний',
                $got ? 'внешний' : 'внутренний'
            )
        );
    }
};

// --- Внешние адреса: запрос разрешён --------------------------------------

$check('8.8.8.8', true, 'публичный DNS');
$check('1.1.1.1', true, 'публичный DNS');
$check('93.184.216.34', true, 'обычный сайт');
$check('2606:2800:220:1:248:1893:25c8:1946', true, 'обычный сайт IPv6');
$check('100.63.255.255', true, 'сразу перед CGNAT');
$check('100.128.0.0', true, 'сразу после CGNAT');
$check('11.0.0.1', true, 'сразу после приватной 10/8');

// --- Внутренние и служебные: отказ ----------------------------------------

$check('127.0.0.1', false, 'loopback');
$check('127.255.255.254', false, 'loopback, верхний край');
$check('10.0.0.5', false, 'приватная 10/8');
$check('172.16.0.1', false, 'приватная 172.16/12');
$check('172.31.255.255', false, 'приватная 172.16/12, верхний край');
$check('192.168.1.1', false, 'приватная 192.168/16');
$check('169.254.169.254', false, 'метаданные облака');
$check('100.64.0.1', false, 'CGNAT');
$check('0.0.0.0', false, 'этот хост');
$check('224.0.0.1', false, 'multicast');
$check('240.0.0.1', false, 'зарезервировано');
$check('255.255.255.255', false, 'широковещательный');
$check('192.0.2.1', false, 'TEST-NET-1');
$check('198.51.100.7', false, 'TEST-NET-2');
$check('203.0.113.9', false, 'TEST-NET-3');
$check('198.18.0.1', false, 'тестирование производительности');
$check('192.88.99.1', false, '6to4 relay');

// --- IPv6 ------------------------------------------------------------------

$check('::1', false, 'loopback IPv6');
$check('::', false, 'неопределённый');
$check('fe80::1', false, 'link-local');
$check('fc00::1', false, 'unique-local');
$check('fd12:3456::1', false, 'unique-local, другой префикс');
$check('ff02::1', false, 'multicast IPv6');
$check('2001:db8::1', false, 'документационная');
$check('64:ff9b::7f00:1', false, 'NAT64');

// Главная лазейка: IPv4 внутри IPv6 проходит проверку приватности как IPv6,
// поэтому вложенный адрес разбирается отдельно.
$check('::ffff:127.0.0.1', false, 'loopback через отображённый адрес');
$check('::ffff:169.254.169.254', false, 'метаданные через отображённый адрес');
$check('::ffff:10.0.0.1', false, 'приватная через отображённый адрес');
$check('::ffff:192.168.0.1', false, 'приватная через отображённый адрес');
$check('::127.0.0.1', false, 'loopback через совместимый адрес');
$check('::ffff:8.8.8.8', true, 'внешний через отображённый адрес');

// --- Мусор: всё, что не разобралось, считается внутренним ------------------

$check('', false, 'пустая строка');
$check('не адрес', false, 'не адрес');
$check('999.1.1.1', false, 'за пределами диапазона');
$check('127.0.0.1.1', false, 'лишний октет');
$check('localhost', false, 'имя вместо адреса');
$check('0x7f.0.0.1', false, 'шестнадцатеричная запись');
$check('2130706433', false, 'loopback десятичным числом');

// --- Границы диапазонов ----------------------------------------------------

if (!PublicHostPolicy::inRange('10.1.2.3', '10.0.0.0', 8)) {
    $fail('inRange: 10.1.2.3 должен попадать в 10.0.0.0/8');
}
if (PublicHostPolicy::inRange('11.1.2.3', '10.0.0.0', 8)) {
    $fail('inRange: 11.1.2.3 не должен попадать в 10.0.0.0/8');
}
if (!PublicHostPolicy::inRange('100.64.0.0', '100.64.0.0', 10)) {
    $fail('inRange: нижняя граница CGNAT');
}
if (!PublicHostPolicy::inRange('100.127.255.255', '100.64.0.0', 10)) {
    $fail('inRange: верхняя граница CGNAT');
}
if (PublicHostPolicy::inRange('100.128.0.0', '100.64.0.0', 10)) {
    $fail('inRange: сразу за верхней границей CGNAT');
}
// Разные семейства сравнивать нельзя.
if (PublicHostPolicy::inRange('10.0.0.1', 'fc00::', 7)) {
    $fail('inRange: IPv4 не должен попадать в IPv6-сеть');
}

// --- Имя, записанное адресом ----------------------------------------------

if (PublicHostPolicy::resolveHost('8.8.8.8') !== ['8.8.8.8']) {
    $fail('resolveHost: литеральный IPv4 возвращается как есть');
}
if (PublicHostPolicy::resolveHost('[::1]') !== ['::1']) {
    $fail('resolveHost: литеральный IPv6 в скобках разбирается');
}
if (PublicHostPolicy::resolveHost('') !== []) {
    $fail('resolveHost: пустое имя');
}

fwrite(STDOUT, "OK PublicHostPolicyTest\n");
exit(0);
