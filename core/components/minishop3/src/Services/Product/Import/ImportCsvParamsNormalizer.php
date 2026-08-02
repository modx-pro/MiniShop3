<?php

declare(strict_types=1);

namespace MiniShop3\Services\Product\Import;

final class ImportCsvParamsNormalizer
{
    /**
     * @return array<string, mixed>
     */
    public static function normalize(array $params): array
    {
        $normalized = [
            'file' => $params['file'] ?? null,
            'fields' => $params['fields'] ?? null,
            'mapping' => $params['mapping'] ?? null,
            'update' => !empty($params['update']),
            'key' => $params['key'] ?? null,
            'skip_header' => $params['skip_header'] ?? false,
            'is_debug' => !empty($params['debug']),
            'delimiter' => $params['delimiter'] ?? ';',
            'keys' => [],
            'tv_enabled' => false,
            'option_enabled' => false,
        ];

        if (!empty($normalized['mapping']) && is_array($normalized['mapping'])) {
            $normalized['keys'] = $normalized['mapping'];
        } elseif (!empty($normalized['fields'])) {
            $normalized['keys'] = array_map('trim', explode(',', $normalized['fields']));
        }

        foreach ($normalized['keys'] as $v) {
            if ($v === null || $v === '' || $v === '-') {
                continue;
            }
            if (preg_match('/^tv\d+$/', $v) || str_starts_with($v, 'tv.')) {
                $normalized['tv_enabled'] = true;
            }
            if (str_starts_with($v, 'option.')) {
                $normalized['option_enabled'] = true;
            }
        }

        return $normalized;
    }
}
