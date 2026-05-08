<?php

namespace MiniShop3\Utils;

final class PriceAdjustment
{
    public static function normalize($value = 0): string
    {
        $value = preg_replace(['#[^\d%\-,\.]#', '#,#'], ['', '.'], (string)$value);

        if ($value === '') {
            return '0';
        }

        $isNegative = str_contains($value, '-');
        $isPercent = str_contains($value, '%');
        $value = str_replace(['-', '%'], '', $value);

        if ($value === '') {
            return '0';
        }

        return ($isNegative ? '-' : '') . $value . ($isPercent ? '%' : '');
    }

    public static function isPercent($value): bool
    {
        return str_ends_with(trim((string)$value), '%');
    }

    public static function getPercent($value): float
    {
        return (float)str_replace('%', '', (string)$value);
    }

    public static function isAllowedPercent(float $percent): bool
    {
        return $percent >= -100 && $percent <= 100;
    }

    public static function calculate(float $baseCost, $value): float
    {
        $value = trim((string)$value);

        if ($value === '') {
            return 0.0;
        }

        if (self::isPercent($value)) {
            return $baseCost / 100 * self::getPercent($value);
        }

        return (float)$value;
    }
}
