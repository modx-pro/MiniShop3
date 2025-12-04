<?php

namespace MiniShop3\Interfaces;

/**
 * Interface for computed grid fields
 *
 * Computed fields are executed for each row separately (row-by-row)
 * and should not make additional SQL queries.
 *
 * Used for:
 * - Mathematical calculations (discount, percentage, rounding)
 * - Data formatting (dates, numbers, strings)
 * - Logical operations based on row data
 *
 * Example:
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
     * Calculate value for one grid row
     *
     * @param array $row Grid row data
     * @return mixed Computed value
     */
    public function compute(array $row): mixed;
}
