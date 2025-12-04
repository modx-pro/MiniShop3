<?php

namespace MiniShop3\Computed;

use MiniShop3\Interfaces\ComputedFieldInterface;

/**
 * Computed field: Customer categorization by purchase amount
 *
 * Determines customer level based on total purchase amount:
 * - VIP: >= 100,000
 * - Regular: >= 10,000
 * - New: < 10,000
 *
 * Used for customer segmentation, personalized offers,
 * VIP service and marketing campaigns.
 *
 * @package MiniShop3\Computed
 */
class CustomerTier implements ComputedFieldInterface
{
    /**
     * Calculate customer tier
     *
     * @param array $row Grid row data (customer)
     * @return string Tier: 'VIP', 'Regular' or 'New'
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
