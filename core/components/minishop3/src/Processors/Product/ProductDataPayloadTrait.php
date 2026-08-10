<?php

namespace MiniShop3\Processors\Product;

use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductData;
use MODX\Revolution\modX;

/**
 * Applies msProductData fields from the `Data` block and flat request keys (#297).
 *
 * Resource Create/Update call `$object->fromArray($properties)` without ignoreInvalid,
 * so msProductData columns must be applied explicitly. On Create the msProductData row
 * needs a resource id — persist in afterSave(); on Update beforeSave() is enough.
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

        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            } else {
                $this->modx->log(
                    modX::LOG_LEVEL_WARN,
                    '[msProduct] malformed Data JSON payload: ' . json_last_error_msg()
                );

                return;
            }
        }

        if (is_array($payload)) {
            $this->ms3ProductDataPayload = $payload;
        }
    }

    /**
     * Assign msProductData fields on the in-memory composite (Update / pre-save).
     */
    protected function applyProductDataPayload(): void
    {
        $this->assignProductDataPayload(false);
    }

    /**
     * Assign and save msProductData after the resource id exists (Create).
     */
    protected function persistProductDataPayload(): bool
    {
        return $this->assignProductDataPayload(true);
    }

    private function assignProductDataPayload(bool $persist): bool
    {
        if (!$this->object instanceof msProduct) {
            return false;
        }

        if ($persist && (int) $this->object->get('id') <= 0) {
            return false;
        }

        $this->ensureProductDataFieldMapLoaded();

        $allowedFields = $this->getAllowedProductDataFieldNames();
        $flatFields = $this->collectFlatProductDataFields($allowedFields);
        $nestedFields = $this->collectNestedProductDataFields($allowedFields);

        if ($flatFields === [] && $nestedFields === []) {
            return true;
        }

        $productData = $this->object->loadData();

        if ($persist) {
            $productData->set('id', (int) $this->object->get('id'));
        }

        $this->assignProductDataFields($productData, $flatFields);
        $this->assignProductDataFields($productData, $nestedFields);

        return $persist ? (bool) $productData->save() : true;
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
        $fields = array_fill_keys($this->object->getDataFieldsNames(), true);
        unset($fields['id'], $fields['preview_file_id']);

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
