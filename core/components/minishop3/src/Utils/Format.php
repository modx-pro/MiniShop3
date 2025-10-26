<?php

namespace MiniShop3\Utils;

use MiniShop3\MiniShop3;

/**
 * Класс для форматирования данных (цены, веса, даты)
 *
 * Оптимизирован для массового использования:
 * - Кеширование настроек при инициализации
 * - Единый метод formatNumber() для price/weight (DRY)
 * - Поддержка валютных символов
 * - Корректная обработка ошибок дат
 */
class Format
{
    /** @var \MODX\Revolution\modX */
    private $modx;

    /** @var MiniShop3 */
    private $ms3;

    // Кешированные настройки форматирования
    /** @var array [decimals, decimal_separator, thousands_separator] */
    private $priceFormat;

    /** @var bool Удалять ли нули после запятой для цен */
    private $priceNoZeros;

    /** @var array [decimals, decimal_separator, thousands_separator] */
    private $weightFormat;

    /** @var bool Удалять ли нули после запятой для веса */
    private $weightNoZeros;

    /** @var string Формат даты (PHP date format) */
    private $dateFormat;

    /** @var string Символ валюты */
    private $currencySymbol;

    /** @var string Позиция символа валюты (before/after) */
    private $currencyPosition;

    /**
     * @param MiniShop3 $ms3
     */
    public function __construct(MiniShop3 $ms3)
    {
        $this->ms3 = $ms3;
        $this->modx = $this->ms3->modx;

        // Загружаем все настройки форматирования один раз при создании объекта
        $this->loadSettings();
    }

    /**
     * Загружает настройки форматирования из системных настроек
     * Вызывается один раз при инициализации класса
     *
     * @return void
     */
    private function loadSettings(): void
    {
        // Настройки форматирования цен
        $priceFormatRaw = $this->modx->getOption('ms3_price_format', null, '[2, ".", " "]');
        $this->priceFormat = json_decode($priceFormatRaw, true) ?: [2, '.', ' '];
        $this->priceNoZeros = (bool)$this->modx->getOption('ms3_price_format_no_zeros', null, true);

        // Настройки форматирования веса
        $weightFormatRaw = $this->modx->getOption('ms3_weight_format', null, '[3, ".", " "]');
        $this->weightFormat = json_decode($weightFormatRaw, true) ?: [3, '.', ' '];
        $this->weightNoZeros = (bool)$this->modx->getOption('ms3_weight_format_no_zeros', null, true);

        // Настройки даты (ИСПРАВЛЕНО: H:M → H:i)
        $this->dateFormat = $this->modx->getOption('ms3_date_format', null, 'd.m.Y H:i');

        // Настройки валюты
        $this->currencySymbol = $this->modx->getOption('ms3_currency_symbol', null, '₽');
        $this->currencyPosition = $this->modx->getOption('ms3_currency_position', null, 'after');
    }

    /**
     * Универсальный метод для форматирования чисел
     *
     * @param float|int $value Число для форматирования
     * @param array $format [decimals, decimal_separator, thousands_separator]
     * @param bool $removeZeros Удалять ли нули после запятой
     * @return string Отформатированное число
     */
    private function formatNumber($value, array $format, bool $removeZeros = true): string
    {
        // number_format(число, количество_знаков_после_запятой, разделитель_дробной_части, разделитель_тысяч)
        $formatted = number_format((float)$value, $format[0], $format[1], $format[2]);

        if ($removeZeros) {
            $parts = explode($format[1], $formatted);

            // Убираем нули справа от дробной части
            if (isset($parts[1])) {
                $parts[1] = rtrim($parts[1], '0');

                // Если после удаления нулей дробная часть пустая - убираем разделитель
                $formatted = !empty($parts[1])
                    ? $parts[0] . $format[1] . $parts[1]
                    : $parts[0];
            }
        }

        return $formatted;
    }

    /**
     * Форматирование цены
     *
     * @param float|int $price Цена
     * @param bool $withCurrency Добавить символ валюты
     * @return string Отформатированная цена
     */
    public function price($price = 0, bool $withCurrency = false): string
    {
        $formatted = $this->formatNumber($price, $this->priceFormat, $this->priceNoZeros);

        // Добавляем символ валюты, если запрошено
        if ($withCurrency && !empty($this->currencySymbol)) {
            $formatted = $this->currencyPosition === 'before'
                ? $this->currencySymbol . ' ' . $formatted
                : $formatted . ' ' . $this->currencySymbol;
        }

        return $formatted;
    }

    /**
     * Форматирование веса
     *
     * @param float|int $weight Вес
     * @return string Отформатированный вес
     */
    public function weight($weight = 0): string
    {
        return $this->formatNumber($weight, $this->weightFormat, $this->weightNoZeros);
    }

    /**
     * Расчет процента скидки
     *
     * @param float|int $oldPrice Старая цена
     * @param float|int $newPrice Новая цена
     * @param int $decimals Количество десятичных знаков (по умолчанию 0)
     * @return float|int Процент скидки (или 0 если нет скидки)
     */
    public function discount($oldPrice = 0, $newPrice = 0, int $decimals = 0)
    {
        // Защита от деления на ноль и некорректных данных
        if (empty($oldPrice) || empty($newPrice) || $oldPrice <= 0 || $newPrice <= 0) {
            return 0;
        }

        // Если новая цена больше или равна старой - скидки нет
        if ($newPrice >= $oldPrice) {
            return 0;
        }

        $discount = (($oldPrice - $newPrice) / $oldPrice) * 100;

        return $decimals > 0 ? round($discount, $decimals) : (int)round($discount);
    }

    /**
     * Форматирование даты
     *
     * @param string $date Исходная дата
     * @return string Отформатированная дата или &nbsp; при ошибке
     */
    public function date($date = ''): string
    {
        // Проверка на пустую дату
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
     * Получить символ валюты
     *
     * @return string
     */
    public function getCurrencySymbol(): string
    {
        return $this->currencySymbol;
    }

    /**
     * Получить настройки форматирования для JavaScript
     * Полезно для синхронизации форматирования на фронтенде
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
        ];
    }
}
