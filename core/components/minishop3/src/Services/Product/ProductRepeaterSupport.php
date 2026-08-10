<?php

declare(strict_types=1);

namespace MiniShop3\Services\Product;

use MiniShop3\Model\msProductData;
use MiniShop3\Services\ExtraFields\RepeaterFieldService;
use MODX\Revolution\modX;

/**
 * Repeater extra-field metadata and payload normalization for msProductData.
 */
class ProductRepeaterSupport
{
    protected modX $modx;

    /** @var array<string, array>|null */
    protected ?array $productRepeaterFields = null;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    public function getRepeaterFieldService(): RepeaterFieldService
    {
        /** @var RepeaterFieldService $service */
        $service = $this->modx->services->get('ms3_repeater_field');

        return $service;
    }

    /**
     * @return array<string, array>
     */
    public function getProductRepeaterFields(): array
    {
        return $this->productRepeaterFields ??= $this->getRepeaterFieldService()->getRepeaterFieldsForClass(
            msProductData::class
        );
    }

    /**
     * Validate and normalize repeater extra fields in manager API payload.
     *
     * @param array<string, mixed> $payload
     * @param array<string, array>|null $repeaterFields When null, loads from msProductData repeater config
     */
    public function normalizeRepeaterFieldsInPayload(array &$payload, ?array $repeaterFields = null): ?string
    {
        $repeaterFields ??= $this->getProductRepeaterFields();
        if ($repeaterFields === []) {
            return null;
        }

        $repeaterService = $this->getRepeaterFieldService();
        $this->modx->lexicon->load('minishop3:default');

        foreach ($repeaterFields as $fieldKey => $config) {
            if (!array_key_exists($fieldKey, $payload)) {
                continue;
            }

            try {
                $payload[$fieldKey] = $repeaterService->processValue($payload[$fieldKey], $config);
            } catch (\InvalidArgumentException $e) {
                return $this->modx->lexicon('ms3_repeater_validation_error', [
                    'field' => $fieldKey,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }
}
