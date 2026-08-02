<?php

declare(strict_types=1);

namespace MiniShop3\Services\Product\Import;

use MODX\Revolution\modX;

/**
 * returnedValues contract for import events (#219/#245).
 * Shared layer until EventGate (#358) lands.
 */
final class ImportCsvEventBridge
{
    public static function clearReturnedValues(modX $modx): void
    {
        if (isset($modx->event->returnedValues)) {
            $modx->event->returnedValues = null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function getReturnedValues(modX $modx): array
    {
        return isset($modx->event->returnedValues) && is_array($modx->event->returnedValues)
            ? $modx->event->returnedValues
            : [];
    }

    /**
     * @param array<int|string, mixed> $current
     * @param array<string, mixed>     $returnedValues
     *
     * @return array<int|string, mixed>
     */
    public static function applyReturnedArray(array $current, array $returnedValues, string $key): array
    {
        if (!isset($returnedValues[$key]) || !is_array($returnedValues[$key])) {
            return $current;
        }

        return array_is_list($returnedValues[$key])
            ? $returnedValues[$key]
            : array_replace($current, $returnedValues[$key]);
    }

    public static function isCancelled(mixed $eventResult): bool
    {
        if (!is_array($eventResult)) {
            return false;
        }

        foreach ($eventResult as $result) {
            if ($result === false || $result === 'cancel') {
                return true;
            }
        }

        return false;
    }
}
