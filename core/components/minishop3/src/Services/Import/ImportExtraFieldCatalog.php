<?php

namespace MiniShop3\Services\Import;

use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductData;
use MiniShop3\Services\ExtraFields\RepeaterFieldService;
use MiniShop3\Services\ExtraFieldsService;
use MiniShop3\Utils\Utils;
use MODX\Revolution\modX;

/**
 * Resolves msExtraField (Object Extension) columns for product CSV import (#291).
 */
class ImportExtraFieldCatalog
{
    public const CLASS_PRODUCT = msProduct::class;
    public const CLASS_PRODUCT_DATA = msProductData::class;

    /** @var array<string, array{class: string, phptype: string, label: string}>|null */
    private ?array $index = null;

    /** @var array<string, true>|null */
    private ?array $reservedKeys = null;

    public function __construct(
        private readonly modX $modx,
        private readonly ?ExtraFieldsService $extraFieldsService = null,
        private readonly ?array $staticImportConfig = null,
    ) {
    }

    /**
     * @param array<string, mixed>|null $staticConfig import-fields.php (defaults to file)
     * @return list<array{value: string, label: string, group: string, type: string, required: bool}>
     */
    public function listImportFields(?array $staticConfig = null): array
    {
        if ($staticConfig !== null) {
            $this->reservedKeys = $this->collectReservedKeys($staticConfig);
            $this->index = null;
        }

        $fields = [];
        foreach ($this->loadIndex() as $key => $meta) {
            $fields[] = [
                'value' => $key,
                'label' => $meta['label'] !== '' ? $meta['label'] : $key,
                'group' => $meta['class'] === self::CLASS_PRODUCT ? 'resource' : 'product',
                'type' => $this->mapPhpTypeToImportType($meta['phptype']),
                'required' => false,
            ];
        }

        return $fields;
    }

    /**
     * Normalize a mapped CSV cell for an extra field.
     * Returns null when the cell must be omitted from the save payload.
     * Static import-fields keys are never treated as extra fields.
     */
    public function normalizeCell(string $key, string $value, bool $isUpdate): mixed
    {
        $meta = $this->loadIndex()[$key] ?? null;
        if ($meta === null) {
            return $value;
        }

        if ($isUpdate && $value === '') {
            return null;
        }

        return $this->castByPhptype($meta['phptype'], $value);
    }

    public function castValue(string $key, string $value): mixed
    {
        $phptype = $this->loadIndex()[$key]['phptype'] ?? null;

        return $phptype === null ? $value : $this->castByPhptype($phptype, $value);
    }

    /**
     * @return array<string, array{class: string, phptype: string, label: string}>
     */
    public function loadIndex(): array
    {
        if ($this->index !== null) {
            return $this->index;
        }

        $this->index = [];
        $reserved = $this->getReservedKeys();
        $service = $this->extraFieldsService ?? new ExtraFieldsService($this->modx);

        foreach ([self::CLASS_PRODUCT, self::CLASS_PRODUCT_DATA] as $class) {
            foreach ($service->getFields(['class' => $class, 'active' => 1]) as $row) {
                $key = trim((string) ($row['key'] ?? ''));
                if ($key === '' || isset($reserved[$key])) {
                    continue;
                }

                if (array_key_exists('column_exists', $row) && !$row['column_exists']) {
                    continue;
                }

                // Repeaters need RepeaterFieldService validation — out of CSV import scope (#291).
                if (($row['xtype'] ?? null) === RepeaterFieldService::XTYPE) {
                    continue;
                }

                if (isset($this->index[$key])) {
                    $this->modx->log(
                        modX::LOG_LEVEL_WARN,
                        sprintf(
                            '[ms3 import] Duplicate extra field key "%s" for %s ignored; already mapped to %s',
                            $key,
                            (string) ($row['class'] ?? $class),
                            $this->index[$key]['class']
                        )
                    );
                    continue;
                }

                $this->index[$key] = [
                    'class' => (string) ($row['class'] ?? $class),
                    'phptype' => (string) ($row['phptype'] ?? 'string'),
                    'label' => trim((string) ($row['label'] ?? '')),
                ];
            }
        }

        return $this->index;
    }

    /**
     * @return array<string, true>
     */
    private function getReservedKeys(): array
    {
        if ($this->reservedKeys !== null) {
            return $this->reservedKeys;
        }

        $config = $this->staticImportConfig ?? $this->loadImportFieldsConfig();
        $this->reservedKeys = $this->collectReservedKeys($config);

        return $this->reservedKeys;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadImportFieldsConfig(): array
    {
        $path = dirname(__DIR__, 3) . '/config/import-fields.php';
        if (!is_file($path)) {
            return [];
        }

        $config = include $path;

        return is_array($config) ? $config : [];
    }

    /**
     * @param array<string, mixed> $staticConfig
     * @return array<string, true>
     */
    private function collectReservedKeys(array $staticConfig): array
    {
        $keys = [];
        foreach (['resource', 'product_data', 'special'] as $group) {
            foreach (array_keys($staticConfig[$group] ?? []) as $key) {
                $keys[(string) $key] = true;
            }
        }

        return $keys;
    }

    private function mapPhpTypeToImportType(string $phptype): string
    {
        return match ($phptype) {
            'integer', 'int' => 'integer',
            'float', 'decimal', 'numeric' => 'float',
            'boolean', 'bool' => 'boolean',
            'json', 'array' => 'json',
            'datetime', 'timestamp' => 'datetime',
            default => 'string',
        };
    }

    private function castByPhptype(string $phptype, string $value): mixed
    {
        return match ($phptype) {
            'integer', 'int' => $this->castInteger($value),
            'float', 'decimal', 'numeric' => $this->castFloat($value),
            'boolean', 'bool' => in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'y', 'on'], true),
            'json', 'array' => $this->castJson($value),
            default => $value,
        };
    }

    private function castInteger(string $value): mixed
    {
        $trimmed = trim($value);
        if ($trimmed === '' || !preg_match('/^-?\d+$/', $trimmed)) {
            return $value;
        }

        return (int) $trimmed;
    }

    private function castFloat(string $value): mixed
    {
        $trimmed = trim($value);
        if ($trimmed === '' || !is_numeric($trimmed)) {
            return $value;
        }

        return (float) $trimmed;
    }

    private function castJson(string $value): mixed
    {
        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        return str_contains($value, ',')
            ? Utils::parseImportedOptionValue($value, true)
            : $value;
    }
}
