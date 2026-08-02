<?php

namespace MiniShop3\Utils;

use MODX\Revolution\modX;

/**
 * Canonical helpers for MODX event returnedValues and cancellation signals.
 *
 * Contract (see modx-pro/MiniShop3#219):
 * - Plugins may mutate by-ref event properties in $scriptProperties (legacy MS2 path).
 * - Plugins may set $modx->event->returnedValues as an associative array; callers merge
 *   shallow keys into their working params via mergeReturnedValues().
 * - For named payload channels (ProductImport: params, data, tvData, …) use applyReturnedArray():
 *   list arrays replace the channel; associative arrays patch via array_replace().
 * - Cancellation: plugin returns false or the string "cancel" in invokeEvent response.
 * - success in buildInvokeResult() follows Utils::invokeEvent: empty aggregated message.
 */
final class EventGate
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
        $returnedValues = $modx->event->returnedValues ?? null;

        return is_array($returnedValues) ? $returnedValues : [];
    }

    /**
     * Shallow merge of returnedValues into caller params (Utils::invokeEvent path).
     *
     * @param array<string, mixed> $params
     * @param array<string, mixed> $returnedValues
     *
     * @return array<string, mixed>
     */
    public static function mergeReturnedValues(array $params, array $returnedValues): array
    {
        if ($returnedValues === []) {
            return $params;
        }

        return array_merge($params, $returnedValues);
    }

    /**
     * Apply a named returnedValues channel to a working array.
     *
     * @param array<string|int, mixed> $current
     * @param array<string, mixed> $returnedValues
     *
     * @return array<string|int, mixed>
     */
    public static function applyReturnedArray(array $current, array $returnedValues, string $key): array
    {
        $channel = $returnedValues[$key] ?? null;
        if (!is_array($channel)) {
            return $current;
        }

        return array_is_list($channel)
            ? $channel
            : array_replace($current, $channel);
    }

    /**
     * @param mixed $eventResult modX::invokeEvent() return value
     */
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

    /**
     * Invoke a MODX event and return normalized returnedValues + cancellation flag.
     *
     * Use for direct modX::invokeEvent() call sites that apply named channels via applyReturnedArray().
     *
     * @param array<string, mixed> $properties
     *
     * @return array{result: mixed, returnedValues: array<string, mixed>, cancelled: bool}
     */
    public static function invokeRaw(modX $modx, string $eventName, array $properties): array
    {
        self::clearReturnedValues($modx);
        $result = $modx->invokeEvent($eventName, $properties);

        return [
            'result' => $result,
            'returnedValues' => self::getReturnedValues($modx),
            'cancelled' => self::isCancelled($result),
        ];
    }

    /**
     * @param mixed $response modX::invokeEvent() return value
     */
    public static function normalizeMessage(mixed $response, string $glue = '<br/>'): string
    {
        if (!is_array($response)) {
            return trim((string)$response);
        }

        if (count($response) > 1) {
            $response = array_filter($response, static fn($value) => !empty($value));
        }

        return implode($glue, $response);
    }

    /**
     * Standard invokeEvent result for Order/Cart and other Utils callers.
     *
     * @param array<string, mixed> $params
     * @param array<string, mixed> $returnedValues
     *
     * @return array{success: bool, message: string, data: array<string, mixed>, values: array<string, mixed>}
     */
    public static function buildInvokeResult(array $params, array $returnedValues, string $message): array
    {
        return [
            'success' => empty($message),
            'message' => $message,
            'data' => self::mergeReturnedValues($params, $returnedValues),
            'values' => $returnedValues,
        ];
    }
}
