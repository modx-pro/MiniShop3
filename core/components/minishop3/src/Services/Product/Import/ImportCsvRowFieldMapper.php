<?php

declare(strict_types=1);

namespace MiniShop3\Services\Product\Import;

use MiniShop3\Services\Import\ImportExtraFieldCatalog;

/**
 * Maps raw CSV cells to product, TV, option and gallery buckets.
 */
final class ImportCsvRowFieldMapper
{
    public function __construct(
        private readonly ?ImportExtraFieldCatalog $extraFieldCatalog = null,
    ) {
    }

    /**
     * @param list<string|null> $keys Column mapping from import params
     * @param list<string>      $csv  Raw CSV row
     *
     * @return array{
     *     data: array<string, mixed>,
     *     gallery: list<string>,
     *     tvData: array<string, string>,
     *     optionData: array<string, string>,
     *     missingField: string|null
     * }
     */
    public function map(array $keys, array $csv, callable $resolveVendor, bool $isUpdate = false): array
    {
        $data = [];
        $gallery = [];
        $tvData = [];
        $optionData = [];
        $missingField = null;

        foreach ($keys as $k => $v) {
            if ($v === null || $v === '' || $v === '-') {
                continue;
            }

            if (!isset($csv[$k])) {
                $missingField = (string) $v;
                break;
            }

            $value = trim($csv[$k]);

            if ($v === 'gallery') {
                if (!empty($value)) {
                    $gallery[] = $value;
                }
                continue;
            }

            if (str_starts_with($v, 'tv.')) {
                $tvData[substr($v, 3)] = $value;
                continue;
            }

            if (str_starts_with($v, 'option.')) {
                $optionData[substr($v, 7)] = $value;
                continue;
            }

            if ($v === 'vendor') {
                $data['vendor_id'] = $resolveVendor($value);
                continue;
            }

            if ($v === 'remains') {
                $data['stock'] = $value;
                continue;
            }

            if ($this->extraFieldCatalog !== null) {
                $normalized = $this->extraFieldCatalog->normalizeCell((string) $v, $value, $isUpdate);
                if ($normalized === null) {
                    continue;
                }
                $value = $normalized;
            }

            if (isset($data[$v]) && !is_array($data[$v])) {
                $data[$v] = [$data[$v], $value];
            } elseif (isset($data[$v])) {
                $data[$v][] = $value;
            } else {
                $data[$v] = $value;
            }
        }

        return [
            'data' => $data,
            'gallery' => $gallery,
            'tvData' => $tvData,
            'optionData' => $optionData,
            'missingField' => $missingField,
        ];
    }
}
