<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Add duplicate and publish actions to category-products grid (issue #143)
 *
 * The initial seed had only view, edit, delete. This migration updates the actions
 * column config so that "Duplicate" and "Publish" buttons appear in the category
 * products grid, matching the behavior of the Vue component's default actions.
 */
final class AddDuplicatePublishToCategoryProductsActions extends AbstractMigration
{
    private string $prefix;

    public function up(): void
    {
        $this->prefix = $this->getAdapter()->getOption('table_prefix') ?? '';
        $table = $this->prefix . 'ms3_grid_fields';

        // Defensive: skip if table doesn't exist
        if (!$this->hasTable('ms3_grid_fields')) {
            $this->output->writeln('<comment>Table ms3_grid_fields does not exist, skipping</comment>');
            return;
        }

        $now = date('Y-m-d H:i:s');

        $config = [
            'type' => 'actions',
            'actions' => [
                ['name' => 'view', 'handler' => 'view', 'icon' => 'pi-eye', 'label' => 'view'],
                ['name' => 'edit', 'handler' => 'edit', 'icon' => 'pi-pencil', 'label' => 'edit'],
                [
                    'name' => 'publish',
                    'handler' => 'publish',
                    'icon' => 'pi-check',
                    'iconOff' => 'pi-times',
                    'label' => 'publish',
                    'labelOff' => 'unpublish',
                    'toggleField' => 'published',
                ],
                ['name' => 'duplicate', 'handler' => 'duplicate', 'icon' => 'pi-copy', 'label' => 'duplicate'],
                [
                    'name' => 'delete',
                    'handler' => 'delete',
                    'icon' => 'pi-trash',
                    'label' => 'delete',
                    'severity' => 'danger',
                    'confirm' => true,
                    'confirmMessage' => 'product_delete_confirm_message',
                ],
            ],
        ];

        $configJson = json_encode($config, JSON_UNESCAPED_UNICODE);
        $quoted = $this->getAdapter()->getConnection()->quote($configJson);

        $this->execute(
            "UPDATE `{$table}` SET config = {$quoted}, updated_at = '{$now}' " .
            "WHERE grid_key = 'category-products' AND field_name = 'actions'"
        );
    }

    public function down(): void
    {
        $this->prefix = $this->getAdapter()->getOption('table_prefix') ?? '';
        $table = $this->prefix . 'ms3_grid_fields';

        // Defensive: skip if table doesn't exist
        if (!$this->hasTable('ms3_grid_fields')) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        $config = [
            'type' => 'actions',
            'actions' => [
                ['name' => 'view', 'handler' => 'view', 'icon' => 'pi-eye', 'label' => 'view'],
                ['name' => 'edit', 'handler' => 'edit', 'icon' => 'pi-pencil', 'label' => 'edit'],
                [
                    'name' => 'delete',
                    'handler' => 'delete',
                    'icon' => 'pi-trash',
                    'label' => 'delete',
                    'severity' => 'danger',
                    'confirm' => true,
                    'confirmMessage' => 'product_delete_confirm_message',
                ],
            ],
        ];

        $configJson = json_encode($config, JSON_UNESCAPED_UNICODE);
        $quoted = $this->getAdapter()->getConnection()->quote($configJson);

        $this->execute(
            "UPDATE `{$table}` SET config = {$quoted}, updated_at = '{$now}' " .
            "WHERE grid_key = 'category-products' AND field_name = 'actions'"
        );
    }
}
