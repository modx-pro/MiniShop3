<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Migration: Add confirm.js and AuthUI.js to ms3_frontend_assets setting
 *
 * Adds new JS files to the frontend assets list:
 * - confirm.js after message.js (utility module)
 * - AuthUI.js before ms3.js (UI module)
 */
class AddAuthUiToFrontendAssets extends AbstractMigration
{
    /**
     * Files to add: [file => insertAfter/insertBefore]
     */
    private const FILES_TO_ADD = [
        [
            'file' => '[[+jsUrl]]web/modules/confirm.js',
            'after' => '[[+jsUrl]]web/modules/message.js',
        ],
        [
            'file' => '[[+jsUrl]]web/ui/AuthUI.js',
            'before' => '[[+jsUrl]]web/ms3.js',
        ],
    ];

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

        $changed = false;

        foreach (self::FILES_TO_ADD as $entry) {
            $newFile = $entry['file'];

            // Normalize for comparison
            $normalizedAssets = array_map(fn($p) => str_replace('\\/', '/', $p), $assets);
            $newFileNorm = str_replace('\\/', '/', $newFile);

            if (in_array($newFileNorm, $normalizedAssets, true)) {
                continue;
            }

            if (isset($entry['after'])) {
                $anchorNorm = str_replace('\\/', '/', $entry['after']);
                $pos = array_search($anchorNorm, $normalizedAssets, true);
                if ($pos !== false) {
                    array_splice($assets, $pos + 1, 0, [$newFile]);
                } else {
                    $assets[] = $newFile;
                }
            } elseif (isset($entry['before'])) {
                $anchorNorm = str_replace('\\/', '/', $entry['before']);
                $pos = array_search($anchorNorm, $normalizedAssets, true);
                if ($pos !== false) {
                    array_splice($assets, $pos, 0, [$newFile]);
                } else {
                    $assets[] = $newFile;
                }
            } else {
                $assets[] = $newFile;
            }

            $changed = true;
        }

        if (!$changed) {
            return;
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

        $filesToRemove = array_map(
            fn($entry) => str_replace('\\/', '/', $entry['file']),
            self::FILES_TO_ADD
        );

        $assets = array_values(array_filter($assets, function ($path) use ($filesToRemove) {
            $normalized = str_replace('\\/', '/', $path);
            return !in_array($normalized, $filesToRemove, true);
        }));

        $newValue = json_encode($assets, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        $this->execute(
            "UPDATE `{$table}` SET `value` = " . $this->getAdapter()->getConnection()->quote($newValue) .
            " WHERE `key` = 'ms3_frontend_assets'"
        );
    }
}
