<?php

namespace MiniShop3\Computed;

use MiniShop3\Interfaces\ComputedFieldInterface;

/**
 * Вычисляемое поле: Категоризация клиентов по сумме покупок
 *
 * Определяет уровень клиента на основе общей суммы покупок:
 * - VIP: >= 100,000
 * - Regular: >= 10,000
 * - New: < 10,000
 *
 * Используется для сегментации клиентов, персонализации предложений,
 * VIP-обслуживания и маркетинговых кампаний.
 *
 * @package MiniShop3\Computed
 */
class CustomerTier implements ComputedFieldInterface
{
    /**
     * Вычислить категорию клиента
     *
     * @param array $row Данные строки грида (клиента)
     * @return string Категория: 'VIP', 'Regular' или 'New'
     */
    public function compute(array $row): string
    {
        $totalSpent = (float)($row['total_spent'] ?? 0);

        if ($totalSpent >= 100000) {
            return 'VIP';
        } elseif ($totalSpent >= 10000) {
            return 'Regular';
        } else {
            return 'New';
        }
    }
}
