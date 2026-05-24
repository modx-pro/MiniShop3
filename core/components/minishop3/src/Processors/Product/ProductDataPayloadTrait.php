<?php

namespace MiniShop3\Processors\Product;

use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductData;

/**
 * Applies msProductData fields from the `Data` block and flat request keys (#297).
 *
 * Resource Create/Update call `$object->fromArray($properties)` without ignoreInvalid,
 * so msProductData columns must be applied in beforeSave().
 *
 * @property \MODX\Revolution\modX $modx
 * @property msProduct $object
 */
trait ProductDataPayloadTrait
{
    private const PRODUCT_DATA_PROPERTY = 'Data';

    /** @var array<string, mixed>|null */
    protected ?array $ms3ProductDataPayload = null;

    protected function captureProductDataPayload(): void
    {
        $this->ms3ProductDataPayload = null;

        $payload = $this->getProperty(self::PRODUCT_DATA_PROPERTY);
        if ($payload === null || $payload === '') {
            return;
        }

        $this->unsetProperty(self::PRODUCT_DATA_PROPERTY);

        if (is_array($payload)) {
            $this->ms3ProductDataPayload = $payload;
        }
    }

    protected function applyProductDataPayload(): void
    {
        if (!$this->object instanceof msProduct) {
            return;
        }

        $this->ensureProductDataFieldMapLoaded();

        $allowedFields = $this->getAllowedProductDataFieldNames();
        $flatFields = $this->collectFlatProductDataFields($allowedFields);
        $nestedFields = $this->collectNestedProductDataFields($allowedFields);

        if ($flatFields === [] && $nestedFields === []) {
            return;
        }

        $productData = $this->object->loadData();
        $this->assignProductDataFields($productData, $flatFields);
        $this->assignProductDataFields($productData, $nestedFields);
    }

    private function ensureProductDataFieldMapLoaded(): void
    {
        if (!$this->modx->services->has('ms3')) {
            return;
        }

        $this->modx->services->get('ms3')->loadMap();
    }

    /**
     * @return array<string, true>
     */
    private function getAllowedProductDataFieldNames(): array
    {
        $fields = array_flip($this->object->getDataFieldsNames());
        unset($fields['id']);

        return $fields;
    }

    /**
     * @param array<string, true> $allowedFields
     * @return array<string, mixed>
     */
    private function collectFlatProductDataFields(array $allowedFields): array
    {
        $fields = [];

        foreach ($this->getProperties() as $key => $value) {
            if (!isset($allowedFields[$key])) {
                continue;
            }
            $fields[$key] = $value;
        }

        return $fields;
    }

    /**
     * @param array<string, true> $allowedFields
     * @return array<string, mixed>
     */
    private function collectNestedProductDataFields(array $allowedFields): array
    {
        if ($this->ms3ProductDataPayload === null) {
            return [];
        }

        return array_intersect_key($this->ms3ProductDataPayload, $allowedFields);
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function assignProductDataFields(msProductData $productData, array $fields): void
    {
        if ($fields === []) {
            return;
        }

        $productData->fromArray($fields, '', true, true);
    }
}
