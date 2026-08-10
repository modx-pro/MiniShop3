<?php

declare(strict_types=1);

namespace MiniShop3\Services\Product;

use MiniShop3\Model\msProductData;
use MiniShop3\Utils\EventGate;
use MODX\Revolution\modX;

/**
 * Plugin event hooks for price, weight and field modifiers (EventGate / #219).
 */
class ProductModifierHooks
{
    protected modX $modx;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * @param array<string, mixed> $data
     * @return mixed|string
     */
    public function getModifiedPrice(msProductData $productData, array $data = [])
    {
        $price = !empty($data['price']) ? $data['price'] : $productData->get('price');

        return $this->invokeProductModifier(
            'msOnGetProductPrice',
            ['price' => $price, 'data' => $data],
            ['price' => $price, 'data' => $data],
            'price',
            $price,
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return mixed|string
     */
    public function getModifiedWeight(msProductData $productData, array $data = [])
    {
        $weight = !empty($data['weight']) ? $data['weight'] : $productData->get('weight');

        return $this->invokeProductModifier(
            'msOnGetProductWeight',
            ['weight' => $weight, 'data' => $data],
            ['weight' => $weight, 'data' => $data],
            'weight',
            $weight,
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function getModifiedFields(msProductData $productData, array $data = []): array
    {
        /** @var array<string, mixed> */
        return $this->invokeProductModifier(
            'msOnGetProductFields',
            ['data' => $data],
            ['data' => $data],
            'data',
            $data,
            true,
        );
    }

    /**
     * Invoke product modifier event with eventData chaining and returnedValues fallback.
     *
     * @param array<string, mixed> $eventData
     * @param array<string, mixed> $properties
     */
    private function invokeProductModifier(
        string $eventName,
        array $eventData,
        array $properties,
        string $valueKey,
        mixed $default,
        bool $patchViaApplyReturnedArray = false,
    ): mixed {
        if (empty($this->modx->eventMap[$eventName])) {
            return $default;
        }

        // MODX runtime bag for plugin chaining (#219); not declared on modX stubs.
        // @phpstan-ignore property.notFound
        $eventDataBag = &$this->modx->eventData;
        $eventDataBag[$eventName] = $eventData;
        EventGate::clearReturnedValues($this->modx);
        $this->modx->invokeEvent($eventName, $properties);

        $value = $default;
        if (isset($eventDataBag[$eventName][$valueKey])) {
            $fromEventData = $eventDataBag[$eventName][$valueKey];
            if ($patchViaApplyReturnedArray) {
                if (is_array($fromEventData)) {
                    $value = $fromEventData;
                }
            } else {
                $value = $fromEventData;
            }
        }

        $returnedValues = EventGate::getReturnedValues($this->modx);
        if ($patchViaApplyReturnedArray && is_array($default)) {
            $value = EventGate::applyReturnedArray(is_array($value) ? $value : $default, $returnedValues, $valueKey);
        } elseif (array_key_exists($valueKey, $returnedValues)) {
            $value = $returnedValues[$valueKey];
        }

        unset($eventDataBag[$eventName]);

        return $value;
    }
}
