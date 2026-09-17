<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Add missing FK ms3_product_fields.section → ms3_page_sections.id (#711).
 *
 * Self-contained: no MiniShop3 imports (transport Phinx may run early).
 */
final class RepairProductFieldsSectionFk extends AbstractMigration
{
    private const CONSTRAINT = 'fk_product_fields_section';

    public function up(): void
    {
        if (!$this->hasTable('ms3_product_fields') || !$this->hasTable('ms3_page_sections')) {
            throw new RuntimeException(
                'Cannot repair ' . self::CONSTRAINT . ': ms3_product_fields and ms3_page_sections must both exist'
            );
        }

        $table = $this->table('ms3_product_fields');
        if ($table->hasForeignKey('section', self::CONSTRAINT)) {
            return;
        }

        $prefix = (string) $this->getAdapter()->getOption('table_prefix');
        $fields = $prefix . 'ms3_product_fields';
        $sections = $prefix . 'ms3_page_sections';

        // Legacy writes stored section as 0 (INT cast of "main"); not a valid autoincrement id.
        $this->execute("UPDATE `{$fields}` SET `section` = NULL WHERE `section` = 0");

        $orphans = $this->fetchAll(
            "SELECT f.`id`, f.`name`, f.`section`"
            . " FROM `{$fields}` f"
            . " LEFT JOIN `{$sections}` s ON s.`id` = f.`section`"
            . " WHERE f.`section` IS NOT NULL AND s.`id` IS NULL"
            . " ORDER BY f.`id`"
        );

        if ($orphans !== []) {
            $details = [];
            foreach ($orphans as $row) {
                $details[] = sprintf(
                    'id=%s name=%s section=%s',
                    (string) ($row['id'] ?? ''),
                    (string) ($row['name'] ?? ''),
                    (string) ($row['section'] ?? '')
                );
            }

            throw new RuntimeException(
                'Cannot add ' . self::CONSTRAINT . ': orphan ms3_product_fields.section values must be fixed first: '
                . implode('; ', $details)
            );
        }

        $table->addForeignKey('section', 'ms3_page_sections', 'id', [
            'delete' => 'RESTRICT',
            'update' => 'CASCADE',
            'constraint' => self::CONSTRAINT,
        ])->update();
    }

    public function down(): void
    {
        if (!$this->hasTable('ms3_product_fields')) {
            return;
        }

        $table = $this->table('ms3_product_fields');
        if (!$table->hasForeignKey('section', self::CONSTRAINT)) {
            return;
        }

        $table->dropForeignKey('section', self::CONSTRAINT)->update();
    }
}
