<?php

namespace MiniShop3\Utils;

use MiniShop3\MiniShop3;

/**
 * Data formatting class (prices, weights, dates)
 *
 * Optimized for mass usage:
 * - Settings caching on initialization
 * - Single formatNumber() method for price/weight (DRY)
 * - Currency symbols support
 * - Proper date error handling
 */
class Format
{
    /** Default ruble sign (U+20BD), ASCII-safe for transport builds. */
    public const DEFAULT_CURRENCY_SYMBOL = "\u{20BD}";

    /** @var \MODX\Revolution\modX */
    private $modx;

    /** @var MiniShop3 */
    private $ms3;

    /** @var array [decimals, decimal_separator, thousands_separator] */
    private $priceFormat;

    /** @var bool Remove trailing zeros for prices */
    private $priceNoZeros;

    /** @var array [decimals, decimal_separator, thousands_separator] */
    private $weightFormat;

    /** @var bool Remove trailing zeros for weights */
    private $weightNoZeros;

    /** @var string Date format (PHP date format) */
    private $dateFormat;

    /** @var string Currency symbol */
    private $currencySymbol;

    /** @var string Currency symbol position (before/after) */
    private $currencyPosition;

    /** @var string Weight unit label */
    private $weightUnit;

    /**
     * @param MiniShop3 $ms3
     */
    public function __construct(MiniShop3 $ms3)
    {
        $this->ms3 = $ms3;
        $this->modx = $this->ms3->modx;

        $this->loadSettings();
    }

    /**
     * Load formatting settings from system settings
     * Called once on class initialization
     *
     * @return void
     */
    private function loadSettings(): void
    {
        $priceFormatRaw = $this->modx->getOption('ms3_price_format', null, '[2, ".", " "]');
        $this->priceFormat = json_decode($priceFormatRaw, true) ?: [2, '.', ' '];
        $this->priceNoZeros = (bool)$this->modx->getOption('ms3_price_format_no_zeros', null, true);

        $weightFormatRaw = $this->modx->getOption('ms3_weight_format', null, '[3, ".", " "]');
        $this->weightFormat = json_decode($weightFormatRaw, true) ?: [3, '.', ' '];
        $this->weightNoZeros = (bool)$this->modx->getOption('ms3_weight_format_no_zeros', null, true);

        $this->dateFormat = $this->modx->getOption('ms3_date_format', null, 'd.m.Y H:i');

        $this->currencySymbol = self::normalizeCurrencySymbol(
            $this->modx->getOption('ms3_currency_symbol', null, self::DEFAULT_CURRENCY_SYMBOL)
        );
        $this->currencyPosition = $this->modx->getOption('ms3_currency_position', null, 'after');
        $this->weightUnit = $this->modx->getOption('ms3_weight_unit', null, 'kg');
    }

    /**
     * Normalize system setting value for currency display.
     * Non-strings and mojibake "?" become the default ruble; empty string is kept.
     */
    public static function normalizeCurrencySymbol(mixed $value): string
    {
        if (!is_string($value) || $value === '?') {
            return self::DEFAULT_CURRENCY_SYMBOL;
        }

        return $value;
    }

    /**
     * Universal method for number formatting
     *
     * @param float|int $value Number to format
     * @param array $format [decimals, decimal_separator, thousands_separator]
     * @param bool $removeZeros Remove trailing zeros
     * @return string Formatted number
     */
    private function formatNumber($value, array $format, bool $removeZeros = true): string
    {
        $formatted = number_format((float)$value, $format[0], $format[1], $format[2]);

        if ($removeZeros) {
            $parts = explode($format[1], $formatted);

            if (isset($parts[1])) {
                $parts[1] = rtrim($parts[1], '0');

                $formatted = !empty($parts[1])
                    ? $parts[0] . $format[1] . $parts[1]
                    : $parts[0];
            }
        }

        return $formatted;
    }

    /**
     * Format price
     *
     * @param float|int $price Price
     * @param bool $withCurrency Add currency symbol
     * @return string Formatted price
     */
    public function price($price = 0, bool $withCurrency = false): string
    {
        $formatted = $this->formatNumber($price, $this->priceFormat, $this->priceNoZeros);

        if ($withCurrency && !empty($this->currencySymbol)) {
            $formatted = $this->currencyPosition === 'before'
                ? $this->currencySymbol . ' ' . $formatted
                : $formatted . ' ' . $this->currencySymbol;
        }

        return $formatted;
    }

    /**
     * Format weight
     *
     * @param float|int $weight Weight
     * @return string Formatted weight
     */
    public function weight($weight = 0): string
    {
        return $this->formatNumber($weight, $this->weightFormat, $this->weightNoZeros);
    }

    /**
     * Format weight with unit suffix
     *
     * @param float|int $weight Weight
     * @return string Formatted weight with unit (e.g. "1.5 kg")
     */
    public function weightWithUnit($weight = 0): string
    {
        $formatted = $this->formatNumber($weight, $this->weightFormat, $this->weightNoZeros);

        if (!empty($this->weightUnit)) {
            $formatted .= ' ' . $this->weightUnit;
        }

        return $formatted;
    }

    /**
     * Get weight unit label
     *
     * @return string
     */
    public function getWeightUnit(): string
    {
        return $this->weightUnit;
    }

    /**
     * Calculate discount percentage
     *
     * @param float|int $oldPrice Old price
     * @param float|int $newPrice New price
     * @param int $decimals Number of decimal places (default 0)
     * @return float|int Discount percentage (or 0 if no discount)
     */
    public function discount($oldPrice = 0, $newPrice = 0, int $decimals = 0)
    {
        if (empty($oldPrice) || empty($newPrice) || $oldPrice <= 0 || $newPrice <= 0) {
            return 0;
        }

        if ($newPrice >= $oldPrice) {
            return 0;
        }

        $discount = (($oldPrice - $newPrice) / $oldPrice) * 100;

        return $decimals > 0 ? round($discount, $decimals) : (int)round($discount);
    }

    /**
     * Format date
     *
     * @param string $date Source date
     * @return string Formatted date or &nbsp; on error
     */
    public function date($date = ''): string
    {
        if (empty($date) || $date === '0000-00-00 00:00:00' || $date === '0000-00-00') {
            return '&nbsp;';
        }

        try {
            $dateObj = date_create($date);

            if ($dateObj === false) {
                $this->modx->log(
                    \MODX\Revolution\modX::LOG_LEVEL_WARN,
                    "[Format] Invalid date format: {$date}"
                );
                return '&nbsp;';
            }

            return date_format($dateObj, $this->dateFormat);

        } catch (\Exception $e) {
            $this->modx->log(
                \MODX\Revolution\modX::LOG_LEVEL_ERROR,
                "[Format] Date formatting error: {$date} - " . $e->getMessage()
            );
            return '&nbsp;';
        }
    }

    /**
     * Get currency symbol
     *
     * @return string
     */
    public function getCurrencySymbol(): string
    {
        return $this->currencySymbol;
    }

    /**
     * Get formatting settings for JavaScript
     * Useful for frontend formatting synchronization
     *
     * @return array
     */
    public function getSettings(): array
    {
        return [
            'price' => [
                'format' => $this->priceFormat,
                'noZeros' => $this->priceNoZeros,
            ],
            'weight' => [
                'format' => $this->weightFormat,
                'noZeros' => $this->weightNoZeros,
            ],
            'date' => [
                'format' => $this->dateFormat,
            ],
            'currency' => [
                'symbol' => $this->currencySymbol,
                'position' => $this->currencyPosition,
            ],
            'weight_unit' => $this->weightUnit,
        ];
    }
}
