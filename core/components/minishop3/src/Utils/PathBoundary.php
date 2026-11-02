<?php

declare(strict_types=1);

namespace MiniShop3\Utils;

/**
 * Проверка, что путь лежит внутри разрешённого каталога.
 *
 * Отдельным примитивом, потому что проверка тонкая и ошибиться в ней легко:
 * `realpath()` срезает завершающий разделитель, поэтому сравнение префиксов
 * без него пропускает каталог-сосед — `/var/www/site-evil/x.png` начинается
 * с `/var/www/site`. Ровно на этом ошибается ImportCsvPathGuard (#924);
 * здесь то же место сделано один раз и покрыто тестом.
 */
final class PathBoundary
{
    /**
     * Канонический путь, если он существует и лежит внутри базового каталога.
     *
     * @return string|null null, если путь не существует либо выходит за границу
     */
    public static function resolveInside(string $candidate, string $basePath): ?string
    {
        if ($candidate === '' || $basePath === '') {
            return null;
        }

        $realBase = realpath($basePath);
        $realPath = realpath($candidate);

        if ($realBase === false || $realPath === false) {
            return null;
        }

        return self::isInside($realPath, $realBase) ? $realPath : null;
    }

    /**
     * Лежит ли уже канонический путь внутри уже канонического каталога.
     *
     * Сам каталог своей границей не считается: внутрь — значит строго ниже.
     */
    public static function isInside(string $realPath, string $realBase): bool
    {
        $prefix = rtrim($realBase, '/\\') . DIRECTORY_SEPARATOR;

        return str_starts_with($realPath, $prefix);
    }
}
