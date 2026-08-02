<?php

declare(strict_types=1);

namespace MiniShop3\Services\Product\Import;

use MiniShop3\Controllers\Options\Types\msOptionType;
use MiniShop3\Model\msOption;
use MiniShop3\Services\Option\OptionService;
use MiniShop3\Utils\Utils;
use MODX\Revolution\modX;

/**
 * Parses and persists option.* CSV columns via OptionSyncService contract.
 */
final class ImportCsvOptionHandler
{
    public function __construct(
        private modX $modx,
    ) {
    }

    public function saveForProduct(int $productId, array $optionData): void
    {
        $keys = array_values(array_filter(array_keys($optionData), static fn ($key): bool => $key !== ''));
        if ($keys === []) {
            return;
        }

        $typesByKey = $this->loadOptionTypesByKey($keys);
        $parsedOptions = [];

        foreach ($optionData as $key => $value) {
            if ($key === '') {
                continue;
            }

            $parsedOptions[$key] = Utils::parseImportedOptionValue(
                $value,
                msOptionType::isMultiValueType($typesByKey[$key] ?? null)
            );
        }

        /** @var OptionService $optionService */
        $optionService = $this->modx->services->get('ms3_option_service');
        $optionService->saveProductOptions($productId, $parsedOptions, false);
    }

    /**
     * @param list<string> $keys
     *
     * @return array<string, string>
     */
    private function loadOptionTypesByKey(array $keys): array
    {
        $query = $this->modx->newQuery(msOption::class);
        $query->select('`key`, `type`');
        $query->where(['key:IN' => $keys]);

        if (!$query->prepare() || !$query->stmt->execute()) {
            return [];
        }

        $typesByKey = [];
        while ($row = $query->stmt->fetch(\PDO::FETCH_ASSOC)) {
            if (is_string($row['key'] ?? null) && is_string($row['type'] ?? null)) {
                $typesByKey[$row['key']] = $row['type'];
            }
        }

        return $typesByKey;
    }
}
