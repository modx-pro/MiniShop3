<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Migration: Add QuantityUI.js to ms3_frontend_assets setting
 *
 * Adds the new QuantityUI.js file to the frontend assets list.
 * The file should be added before ms3.js since ms3.js initializes all modules.
 */
class AddQuantityUiToFrontendAssets extends AbstractMigration
{
    /**
     * The new JS file to add
     */
    private const NEW_FILE = '[[+jsUrl]]web/ui/QuantityUI.js';

    /**
     * The file before which to insert (ms3.js is last and initializes all modules)
     */
    private const INSERT_BEFORE = '[[+jsUrl]]web/ms3.js';

    public function up(): void
    {
        $prefix = $this->getAdapter()->getOption('table_prefix');
        $table = $prefix . 'system_settings';

        // Get current setting value
        $row = $this->fetchRow(
            "SELECT `value` FROM `{$table}` WHERE `key` = 'ms3_frontend_assets'"
        );

        if (!$row) {
            // Setting doesn't exist, skip
            return;
        }

        $currentValue = $row['value'];

        // Parse JSON
        $assets = json_decode($currentValue, true);
        if (!is_array($assets)) {
            // Invalid JSON or not an array, skip
            return;
        }

        // Normalize paths (remove escaped slashes for comparison)
        $normalizedAssets = array_map(function ($path) {
            return str_replace('\\/', '/', $path);
        }, $assets);

        $newFileNormalized = str_replace('\\/', '/', self::NEW_FILE);
        $insertBeforeNormalized = str_replace('\\/', '/', self::INSERT_BEFORE);

        // Check if already exists
        if (in_array($newFileNormalized, $normalizedAssets, true)) {
            // Already exists, skip
            return;
        }

        // Find position of ms3.js
        $insertPosition = array_search($insertBeforeNormalized, $normalizedAssets, true);

        if ($insertPosition !== false) {
            // Insert before ms3.js
            array_splice($assets, $insertPosition, 0, [self::NEW_FILE]);
        } else {
            // ms3.js not found, just append
            $assets[] = self::NEW_FILE;
        }

        // Encode back to JSON with pretty formatting
        $newValue = json_encode($assets, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        // Update the setting
        $this->execute(
            "UPDATE `{$table}` SET `value` = " . $this->getAdapter()->getConnection()->quote($newValue) .
            " WHERE `key` = 'ms3_frontend_assets'"
        );
    }

    public function down(): void
    {
        $prefix = $this->getAdapter()->getOption('table_prefix');
        $table = $prefix . 'system_settings';

        // Get current setting value
        $row = $this->fetchRow(
            "SELECT `value` FROM `{$table}` WHERE `key` = 'ms3_frontend_assets'"
        );

        if (!$row) {
            return;
        }

        $currentValue = $row['value'];
        $assets = json_decode($currentValue, true);

        if (!is_array($assets)) {
            return;
        }

        // Remove QuantityUI.js (check both escaped and unescaped versions)
        $assets = array_filter($assets, function ($path) {
            $normalized = str_replace('\\/', '/', $path);
            $newFileNormalized = str_replace('\\/', '/', self::NEW_FILE);
            return $normalized !== $newFileNormalized;
        });

        // Re-index array
        $assets = array_values($assets);

        // Encode back to JSON
        $newValue = json_encode($assets, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        // Update the setting
        $this->execute(
            "UPDATE `{$table}` SET `value` = " . $this->getAdapter()->getConnection()->quote($newValue) .
            " WHERE `key` = 'ms3_frontend_assets'"
        );
    }
}
