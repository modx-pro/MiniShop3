<?php

declare(strict_types=1);

namespace MiniShop3\Services\Product;

use MiniShop3\Model\msProductData;
use MiniShop3\Services\ExtraFields\KeyValueFieldService;
use MODX\Revolution\modX;

/**
 * Key-value extra-field metadata and payload normalization for msProductData.
 */
class ProductKeyValueSupport
{
    protected modX $modx;

    /** @var array<string, array>|null */
    protected ?array $productKeyValueFields = null;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    public function getKeyValueFieldService(): KeyValueFieldService
    {
        /** @var KeyValueFieldService $service */
        $service = $this->modx->services->get('ms3_key_value_field');

        return $service;
    }

    /**
     * @return array<string, array>
     */
    public function getProductKeyValueFields(): array
    {
        return $this->productKeyValueFields ??= $this->getKeyValueFieldService()->getKeyValueFieldsForClass(
            msProductData::class
        );
    }

    /**
     * Validate and normalize key-value extra fields in manager API payload.
     *
     * @param array<string, mixed> $payload
     * @param array<string, array>|null $keyValueFields When null, loads from msProductData key-value config
     */
    public function normalizeKeyValueFieldsInPayload(array &$payload, ?array $keyValueFields = null): ?string
    {
        $keyValueFields ??= $this->getProductKeyValueFields();
        if ($keyValueFields === []) {
            return null;
        }

        $keyValueService = $this->getKeyValueFieldService();
        $this->modx->lexicon->load('minishop3:default');

        foreach ($keyValueFields as $fieldKey => $config) {
            if (!array_key_exists($fieldKey, $payload)) {
                continue;
            }

            try {
                $payload[$fieldKey] = $keyValueService->processValue($payload[$fieldKey], $config);
            } catch (\InvalidArgumentException $e) {
                return $this->modx->lexicon('ms3_key_value_validation_error', [
                    'field' => $fieldKey,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }
}
