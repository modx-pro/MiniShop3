<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Open product title link in the same tab (MODX manager category products grid).
 */
final class CategoryProductsPagetitleLinkSameTab extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->gridFieldsTable();
        $stmt = $this->getAdapter()->getConnection()->prepare(
            "UPDATE `{$table}` SET config = ? WHERE id = ?"
        );

        foreach ($this->fetchPagetitleFieldRows() as $row) {
            $config = json_decode($row['config'], true);
            if (!is_array($config) || empty($config['template']) || !is_string($config['template'])) {
                continue;
            }
            $template = $config['template'];
            if (!$this->templateHasBlankTarget($template)) {
                continue;
            }
            $config['template'] = $this->stripBlankTargetFromTemplate($template);
            $stmt->execute([json_encode($config, JSON_UNESCAPED_UNICODE), (int) $row['id']]);
        }
    }

    public function down(): void
    {
        $table = $this->gridFieldsTable();
        $stmt = $this->getAdapter()->getConnection()->prepare(
            "UPDATE `{$table}` SET config = ? WHERE id = ?"
        );

        foreach ($this->fetchPagetitleFieldRows() as $row) {
            $config = json_decode($row['config'], true);
            if (!is_array($config) || empty($config['template']) || !is_string($config['template'])) {
                continue;
            }
            $template = $config['template'];
            if ($this->templateHasBlankTarget($template)) {
                continue;
            }
            $restored = $this->restoreBlankTargetBeforeProductLinkClass($template);
            if ($restored === null) {
                continue;
            }
            $config['template'] = $restored;
            $stmt->execute([json_encode($config, JSON_UNESCAPED_UNICODE), (int) $row['id']]);
        }
    }

    private function gridFieldsTable(): string
    {
        $prefix = $this->getAdapter()->getOption('table_prefix');

        return $prefix . 'ms3_grid_fields';
    }

    /**
     * @return list<array{id: int|string, config: string|null}>
     */
    private function fetchPagetitleFieldRows(): array
    {
        $table = $this->gridFieldsTable();

        return $this->fetchAll(
            "SELECT id, config FROM `{$table}` WHERE grid_key = 'category-products' AND field_name = 'pagetitle'"
        );
    }

    private function templateHasBlankTarget(string $template): bool
    {
        return str_contains($template, 'target="_blank"')
            || str_contains($template, "target='_blank'");
    }

    private function stripBlankTargetFromTemplate(string $template): string
    {
        return str_replace([' target="_blank"', " target='_blank'"], '', $template);
    }

    /**
     * Re-inserts target="_blank" only for templates that use class product-link (stock layout).
     */
    private function restoreBlankTargetBeforeProductLinkClass(string $template): ?string
    {
        if (str_contains($template, 'class="product-link"')) {
            return str_replace(' class="product-link"', ' target="_blank" class="product-link"', $template);
        }
        if (str_contains($template, "class='product-link'")) {
            return str_replace(" class='product-link'", " target='_blank' class='product-link'", $template);
        }

        return null;
    }
}
