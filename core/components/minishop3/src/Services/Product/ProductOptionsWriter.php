<?php

declare(strict_types=1);

namespace MiniShop3\Services\Product;

use MiniShop3\Model\msProductData;
use MiniShop3\Model\msProductOption;
use MODX\Revolution\modX;

/**
 * Synchronizes msProductOption rows from JSON fields or explicit option arrays.
 */
class ProductOptionsWriter
{
    use ProductDataExplicitFieldsTrait;

    protected modX $modx;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * @param list<string> $repeaterKeys Repeater/key-value JSON field keys to exclude from option sync
     */
    public function saveOptions(
        msProductData $productData,
        ?array $options = null,
        bool $removeOther = true,
        array $repeaterKeys = []
    ): void {
        $optionsExplicit = $options !== null;

        if (!$optionsExplicit) {
            $options = $this->collectJsonFieldOptions($productData, $repeaterKeys);
        }

        // When options=null we only sync JSON fields — do not remove custom category options
        $removeOther = $optionsExplicit ? $removeOther : false;

        /** @var msProductOption $optionInstance */
        $optionInstance = $this->modx->newObject(msProductOption::class);
        $optionInstance->saveProductOptions((int) $productData->get('id'), $options, $removeOther);
    }

    /**
     * @param list<string> $repeaterKeys JSON keys excluded from option sync (repeaters, key-value)
     * @return array<string, mixed>
     */
    private function collectJsonFieldOptions(msProductData $productData, array $repeaterKeys): array
    {
        $rawFields = $this->readExplicitFields($productData);
        $repeaterKeySet = array_fill_keys($repeaterKeys, true);
        $options = [];

        foreach ($productData->_fieldMeta as $key => $meta) {
            if (($meta['phptype'] ?? '') !== 'json') {
                continue;
            }
            if (isset($repeaterKeySet[$key]) || !array_key_exists($key, $rawFields)) {
                continue;
            }

            $fieldValue = $productData->get($key);
            $options[$key] = !empty($fieldValue) ? $fieldValue : null;
        }

        return $options;
    }
}
