<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Migration: Add Selectors.js to ms3_frontend_assets setting
 *
 * Adds the Selectors.js file to the frontend assets list.
 * Must be loaded before ApiClient.js and other core modules.
 *
 * @see https://github.com/modx-pro/MiniShop3/issues/18
 */
class AddSelectorsToFrontendAssets extends AbstractMigration
{
    /**
     * The new JS file to add
     */
    private const NEW_FILE = '[[+jsUrl]]web/core/Selectors.js';

    /**
     * The file after which to insert (Selectors.js goes first among core modules)
     */
    private const INSERT_AFTER = '[[+jsUrl]]web/modules/message.js';

    public function up(): void
    {
        $prefix = $this->getAdapter()->getOption('table_prefix');
        $table = $prefix . 'system_settings';

        $row = $this->fetchRow(
            "SELECT `value` FROM `{$table}` WHERE `key` = 'ms3_frontend_assets'"
        );

        if (!$row) {
            return;
        }

        $assets = json_decode($row['value'], true);
        if (!is_array($assets)) {
            return;
        }

        $normalizedAssets = array_map(function ($path) {
            return str_replace('\\/', '/', $path);
        }, $assets);

        $newFileNormalized = str_replace('\\/', '/', self::NEW_FILE);
        $insertAfterNormalized = str_replace('\\/', '/', self::INSERT_AFTER);

        if (in_array($newFileNormalized, $normalizedAssets, true)) {
            return;
        }

        $insertPosition = array_search($insertAfterNormalized, $normalizedAssets, true);

        if ($insertPosition !== false) {
            array_splice($assets, $insertPosition + 1, 0, [self::NEW_FILE]);
        } else {
            $assets[] = self::NEW_FILE;
        }

        $newValue = json_encode($assets, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        $this->execute(
            "UPDATE `{$table}` SET `value` = " . $this->getAdapter()->getConnection()->quote($newValue) .
            " WHERE `key` = 'ms3_frontend_assets'"
        );
    }

    public function down(): void
    {
        $prefix = $this->getAdapter()->getOption('table_prefix');
        $table = $prefix . 'system_settings';

        $row = $this->fetchRow(
            "SELECT `value` FROM `{$table}` WHERE `key` = 'ms3_frontend_assets'"
        );

        if (!$row) {
            return;
        }

        $assets = json_decode($row['value'], true);
        if (!is_array($assets)) {
            return;
        }

        $assets = array_values(array_filter($assets, function ($path) {
            $normalized = str_replace('\\/', '/', $path);
            return $normalized !== str_replace('\\/', '/', self::NEW_FILE);
        }));

        $newValue = json_encode($assets, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        $this->execute(
            "UPDATE `{$table}` SET `value` = " . $this->getAdapter()->getConnection()->quote($newValue) .
            " WHERE `key` = 'ms3_frontend_assets'"
        );
    }
}
