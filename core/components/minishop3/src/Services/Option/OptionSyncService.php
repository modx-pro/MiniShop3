<?php

namespace MiniShop3\Services\Option;

use MiniShop3\Model\msProductOption;
use xPDO\xPDO;

/**
 * Service for synchronizing option values
 *
 * Extracted from msProductOption model to separate concerns
 * Handles saving, updating and removing option values
 *
 * REFACTORED: saveProductOptions() was 66 lines with CC > 15
 * NOW: Split into clear methods with low cyclomatic complexity
 */
class OptionSyncService
{
    /** @var xPDO */
    protected $xpdo;

    /**
     * @param xPDO $xpdo
     */
    public function __construct(xPDO $xpdo)
    {
        $this->xpdo = $xpdo;
    }

    /**
     * Save product options
     *
     * Replaces: msProductOption::saveProductOptions()
     * REFACTORED from 66 lines to clear separated methods
     *
     * @param int $productId Product ID
     * @param array $options Options to save ['color' => ['Red', 'Blue'], 'size' => 'L']
     * @param bool $removeOther Remove options not in $options array
     * @return bool Success status
     */
    public function saveProductOptions(int $productId, array $options, bool $removeOther = true): bool
    {
        if (empty($options) || !is_array($options)) {
            return true;
        }

        $existingOptions = $this->getForProduct($productId);

        // Process each option
        foreach ($options as $key => $values) {
            $values = $this->prepareOptionValues($values);

            if (is_array($values) && !empty($values)) {
                $this->syncOptionValues($productId, $key, $values, $existingOptions);
            }
        }

        // Remove unused options if requested
        if ($removeOther) {
            $this->removeUnusedOptions($productId, array_keys($options), $existingOptions);
        }

        return true;
    }

    /**
     * Sync values for single option (extracted from saveProductOptions for clarity)
     *
     * Handles INSERT/UPDATE logic for one option key
     *
     * @param int $productId Product ID
     * @param string $key Option key
     * @param array $newValues New values to set
     * @param array $existingOptions Existing options from getForProduct()
     */
    protected function syncOptionValues(int $productId, string $key, array $newValues, array $existingOptions): void
    {
        $table = $this->xpdo->getTableName(msProductOption::class);
        $add = $this->xpdo->prepare("INSERT INTO {$table} (`product_id`, `key`, `value`) VALUES (?, ?, ?)");
        $update = $this->xpdo->prepare("UPDATE {$table} SET `value` = ? WHERE `product_id` = ? AND `key` = ?");
        $delete = $this->xpdo->prepare("DELETE FROM {$table} WHERE `product_id` = ? AND `key` = ? AND `value` = ?");

        $existingValues = $existingOptions[$key] ?? [];
        $isMultiple = count($newValues) > 1;

        if ($isMultiple) {
            // Multiple values (ComboMultiple) - handle each value separately
            $this->syncMultipleValues($productId, $key, $newValues, $existingValues, $add, $delete);
        } else {
            // Single value (Textfield, Combobox, etc.)
            $this->syncSingleValue($productId, $key, $newValues[0], $existingValues, $add, $update, $delete);
        }
    }

    /**
     * Sync multiple values for option (e.g. ComboMultiple)
     *
     * @param int $productId Product ID
     * @param string $key Option key
     * @param array $newValues New values
     * @param array $existingValues Existing values
     * @param \PDOStatement $addStmt Prepared INSERT statement
     * @param \PDOStatement $deleteStmt Prepared DELETE statement
     */
    protected function syncMultipleValues(
        int $productId,
        string $key,
        array $newValues,
        array $existingValues,
        \PDOStatement $addStmt,
        \PDOStatement $deleteStmt
    ): void {
        // Add new values that don't exist
        foreach ($newValues as $value) {
            if (!in_array($value, $existingValues, true)) {
                $addStmt->execute([$productId, $key, $value]);
            }
        }

        // Remove old values that are not in new values
        foreach ($existingValues as $oldValue) {
            if (!in_array($oldValue, $newValues, true)) {
                $deleteStmt->execute([$productId, $key, $oldValue]);
            }
        }
    }

    /**
     * Sync single value for option (e.g. Textfield, Combobox)
     *
     * @param int $productId Product ID
     * @param string $key Option key
     * @param string $newValue New value
     * @param array $existingValues Existing values
     * @param \PDOStatement $addStmt Prepared INSERT statement
     * @param \PDOStatement $updateStmt Prepared UPDATE statement
     * @param \PDOStatement $deleteStmt Prepared DELETE statement
     */
    protected function syncSingleValue(
        int $productId,
        string $key,
        string $newValue,
        array $existingValues,
        \PDOStatement $addStmt,
        \PDOStatement $updateStmt,
        \PDOStatement $deleteStmt
    ): void {
        $existingCount = count($existingValues);

        if ($existingCount === 0) {
            // No existing value - INSERT
            $addStmt->execute([$productId, $key, $newValue]);
        } elseif ($existingCount === 1) {
            if ($existingValues[0] !== $newValue) {
                // One existing value, different - UPDATE
                $updateStmt->execute([$newValue, $productId, $key]);
            }
            // If same value - do nothing
        } else {
            // Multiple existing values, convert to single - DELETE all, then INSERT one
            foreach ($existingValues as $oldValue) {
                $deleteStmt->execute([$productId, $key, $oldValue]);
            }
            $addStmt->execute([$productId, $key, $newValue]);
        }
    }

    /**
     * Remove unused options (extracted from saveProductOptions)
     *
     * Removes options that exist in DB but not in provided $usedKeys
     *
     * @param int $productId Product ID
     * @param array $usedKeys Keys of options that should remain
     * @param array $existingOptions Existing options from getForProduct()
     */
    protected function removeUnusedOptions(int $productId, array $usedKeys, array $existingOptions): void
    {
        $table = $this->xpdo->getTableName(msProductOption::class);
        $remove = $this->xpdo->prepare("DELETE FROM {$table} WHERE `product_id` = ? AND `key` = ?");

        foreach ($existingOptions as $key => $values) {
            if (!in_array($key, $usedKeys, true)) {
                $remove->execute([$productId, $key]);
            }
        }
    }

    /**
     * Get current option values for product
     *
     * Replaces: msProductOption::getForProduct()
     *
     * @param int $productId Product ID
     * @param array $keys Optional: filter by specific keys
     * @return array ['color' => ['Red', 'Blue'], 'size' => ['L']]
     */
    public function getForProduct(int $productId, array $keys = []): array
    {
        $c = $this->xpdo->newQuery(msProductOption::class, ['product_id' => $productId]);
        $c->select('key,value');
        $c->sortby('value');

        if (!empty($keys)) {
            $c->where(['key:IN' => $keys]);
        }

        $values = [];
        if ($c->prepare() && $c->stmt->execute()) {
            while ($row = $c->stmt->fetch(\PDO::FETCH_ASSOC)) {
                if (isset($values[$row['key']])) {
                    $values[$row['key']][] = $row['value'];
                } else {
                    $values[$row['key']] = [$row['value']];
                }
            }
        }

        return $values;
    }

    /**
     * Prepare and sanitize option values
     *
     * Replaces: msProductOption::prepareOptionValues()
     *
     * Cleans up values:
     * - Converts to array if needed
     * - Trims whitespace
     * - Removes duplicates
     * - Removes empty values
     *
     * @param mixed $values Input values (string, array, or null)
     * @return array|null Cleaned array of values or null
     */
    protected function prepareOptionValues($values): ?array
    {
        if ($values === null) {
            return null;
        }

        // Convert to array
        if (!is_array($values)) {
            $values = [$values];
        }

        // Clean up: trim, remove duplicates, remove empty
        $values = array_map('trim', $values);
        $values = array_keys(array_flip($values));  // Remove duplicates
        $values = array_diff($values, ['']);  // Remove empty strings

        if (empty($values)) {
            return null;
        }

        return $values;
    }

    /**
     * Update option key in all product option values
     *
     * Replaces SQL logic from: Settings\Option\Update::updateOldKeys()
     * Used when option key is renamed (e.g. "size" → "product_size")
     *
     * @param string $oldKey Old option key
     * @param string $newKey New option key
     * @return bool Success status
     */
    public function updateOptionKey(string $oldKey, string $newKey): bool
    {
        if (empty($oldKey) || empty($newKey) || $oldKey === $newKey) {
            return true;
        }

        // Update all msProductOption records with this key
        $table = $this->xpdo->getTableName(msProductOption::class);
        $sql = "UPDATE {$table} SET `key` = ? WHERE `key` = ?";

        try {
            $stmt = $this->xpdo->prepare($sql);
            $result = $stmt->execute([$newKey, $oldKey]);
            $stmt->closeCursor();
            return $result;
        } catch (\Exception $e) {
            $this->xpdo->log(
                \modX::LOG_LEVEL_ERROR,
                '[OptionSyncService::updateOptionKey] Error: ' . $e->getMessage()
            );
            return false;
        }
    }
}
