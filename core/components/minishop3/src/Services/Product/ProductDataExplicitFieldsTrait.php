<?php

declare(strict_types=1);

namespace MiniShop3\Services\Product;

use MiniShop3\Model\msProductData;

/**
 * Read raw xPDO `_fields` (POST/explicit payload) without going through msProductData::get().
 */
trait ProductDataExplicitFieldsTrait
{
    /**
     * @return array<string, mixed>
     */
    private function readExplicitFields(msProductData $productData): array
    {
        $reflection = new \ReflectionClass($productData);
        $property = $reflection->getProperty('_fields');
        $property->setAccessible(true);
        $fields = $property->getValue($productData);

        return is_array($fields) ? $fields : [];
    }
}
