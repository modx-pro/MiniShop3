<?php

declare(strict_types=1);

namespace MiniShop3\Utils;

/**
 * Можно ли серверу обращаться по этому адресу (защита от SSRF).
 *
 * Фильтры PHP закрывают приватные и зарезервированные диапазоны, но не всё:
 * CGNAT, multicast и документационные сети они пропускают, а IPv4-адрес,
 * записанный как IPv6 (`::ffff:127.0.0.1`), проверку приватности проходит.
 * Поэтому к фильтрам добавлен явный список и разбор отображённых адресов.
 *
 * Отдельным классом, потому что это единственное место, которое решает
 * «внутренний адрес или внешний», и ошибка здесь означает SSRF.
 */
final class PublicHostPolicy
{
    /** @var list<array{0: string, 1: int}> сеть и длина префикса */
    private const BLOCKED_V4 = [
        ['0.0.0.0', 8],         // этот хост
        ['10.0.0.0', 8],        // приватная
        ['100.64.0.0', 10],     // CGNAT
        ['127.0.0.0', 8],       // loopback
        ['169.254.0.0', 16],    // link-local
        ['172.16.0.0', 12],     // приватная
        ['192.0.0.0', 24],      // назначения IETF
        ['192.0.2.0', 24],      // TEST-NET-1
        ['192.88.99.0', 24],    // 6to4 relay
        ['192.168.0.0', 16],    // приватная
        ['198.18.0.0', 15],     // тестирование производительности
        ['198.51.100.0', 24],   // TEST-NET-2
        ['203.0.113.0', 24],    // TEST-NET-3
        ['224.0.0.0', 4],       // multicast
        ['240.0.0.0', 4],       // зарезервировано
    ];

    /**
     * Механизмы перехода IPv4 → IPv6 блокируются целиком, а не разбором
     * вложенного адреса. Внутри такого адреса сидит IPv4, и `2002:a9fe:a9fe::`
     * — это 169.254.169.254, то есть метаданные облака; вытаскивать его
     * побитово значит завести ещё одно место, где легко ошибиться.
     *
     * Плата: блокируются и те адреса этих диапазонов, внутри которых внешний
     * IPv4, например `2002:808:808::`. Это осознанно: 6to4 объявлен
     * устаревшим (RFC 7526), Teredo и SIIT для раздачи картинок не
     * используются, а цена ошибки здесь — SSRF.
     *
     * @var list<array{0: string, 1: int}>
     */
    private const BLOCKED_V6 = [
        ['::', 128],            // неопределённый
        ['::1', 128],           // loopback
        ['::ffff:0:0:0', 96],   // IPv4-translated (SIIT)
        ['64:ff9b::', 96],      // NAT64
        ['64:ff9b:1::', 48],    // NAT64 локальный (RFC 8215)
        ['100::', 64],          // discard-only
        ['2001::', 32],         // Teredo
        ['2001:db8::', 32],     // документационная
        ['2002::', 16],         // 6to4
        ['fc00::', 7],          // unique-local
        ['fe80::', 10],         // link-local
        ['ff00::', 8],          // multicast
    ];

    /**
     * Внешний ли это адрес.
     *
     * Всё, что не разобралось как IP, считается непубличным: решать такие
     * случаи в пользу запроса нельзя.
     */
    public static function isPublicIp(string $ip): bool
    {
        $ip = trim($ip);

        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        // IPv4 внутри IPv6: `::ffff:127.0.0.1` проходит проверку приватности
        // как IPv6-адрес, поэтому разбираем вложенный адрес отдельно.
        $mapped = self::mappedIpv4($ip);
        if ($mapped !== null) {
            return self::isPublicIp($mapped);
        }

        $isV4 = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;

        foreach ($isV4 ? self::BLOCKED_V4 : self::BLOCKED_V6 as [$network, $prefix]) {
            if (self::inRange($ip, $network, $prefix)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Адреса имени. Пустой список означает «не разрешилось» — тоже отказ.
     *
     * @return list<string>
     */
    public static function resolveHost(string $host): array
    {
        $host = trim($host);
        if ($host === '') {
            return [];
        }

        // Адрес мог быть задан напрямую, в том числе в квадратных скобках.
        $literal = trim($host, '[]');
        if (filter_var($literal, FILTER_VALIDATE_IP) !== false) {
            return [$literal];
        }

        $ips = [];

        $v4 = gethostbynamel($host);
        if (is_array($v4)) {
            $ips = $v4;
        }

        $v6 = @dns_get_record($host, DNS_AAAA);
        if (is_array($v6)) {
            foreach ($v6 as $record) {
                if (!empty($record['ipv6'])) {
                    $ips[] = (string) $record['ipv6'];
                }
            }
        }

        return array_values(array_unique($ips));
    }

    /**
     * Вложенный IPv4 из отображённого IPv6-адреса, иначе null.
     */
    private static function mappedIpv4(string $ip): ?string
    {
        $packed = @inet_pton($ip);
        if ($packed === false || strlen($packed) !== 16) {
            return null;
        }

        // ::ffff:0:0/96 — отображённый, ::/96 — совместимый
        $prefixMapped = str_repeat("\0", 10) . "\xff\xff";
        $prefixCompat = str_repeat("\0", 12);

        if (str_starts_with($packed, $prefixMapped) || str_starts_with($packed, $prefixCompat)) {
            $tail = substr($packed, 12, 4);
            if ($tail === str_repeat("\0", 4)) {
                return null;
            }

            return inet_ntop($tail) ?: null;
        }

        return null;
    }

    /**
     * Попадает ли адрес в сеть с указанной длиной префикса.
     */
    public static function inRange(string $ip, string $network, int $prefix): bool
    {
        $packedIp = @inet_pton($ip);
        $packedNetwork = @inet_pton($network);

        if ($packedIp === false || $packedNetwork === false) {
            return false;
        }

        if (strlen($packedIp) !== strlen($packedNetwork)) {
            return false;
        }

        $wholeBytes = intdiv($prefix, 8);
        $restBits = $prefix % 8;

        if ($wholeBytes > 0 && strncmp($packedIp, $packedNetwork, $wholeBytes) !== 0) {
            return false;
        }

        if ($restBits === 0) {
            return true;
        }

        $mask = 0xff << (8 - $restBits) & 0xff;

        return (ord($packedIp[$wholeBytes]) & $mask) === (ord($packedNetwork[$wholeBytes]) & $mask);
    }
}
