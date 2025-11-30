<?php

namespace MiniShop3\Interfaces;

/**
 * Интерфейс для вычисляемых полей грида
 *
 * Computed поля выполняются для каждой строки отдельно (row-by-row)
 * и не должны делать дополнительных SQL запросов.
 *
 * Используются для:
 * - Математических вычислений (скидка, процент, округление)
 * - Форматирования данных (даты, числа, строки)
 * - Логических операций на основе данных строки
 *
 * Пример:
 * ```php
 * class DiscountPercent implements ComputedFieldInterface {
 *     public function compute(array $row): float {
 *         if ($row['old_price'] <= 0) return 0;
 *         return round(($row['old_price'] - $row['price']) / $row['old_price'] * 100, 2);
 *     }
 * }
 * ```
 */
interface ComputedFieldInterface
{
    /**
     * Вычислить значение для одной строки грида
     *
     * @param array $row Данные строки грида
     * @return mixed Вычисленное значение
     */
    public function compute(array $row): mixed;
}
