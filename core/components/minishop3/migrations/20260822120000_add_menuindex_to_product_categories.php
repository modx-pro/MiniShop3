<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Per-category menuindex for additional product categories (#625).
 */
final class AddMenuindexToProductCategories extends AbstractMigration
{
    private const TABLE = 'ms3_product_categories';

    private const INDEX_CATEGORY_MENUINDEX = 'category_menuindex';

    public function up(): void
    {
        $table = $this->table(self::TABLE);
        $columnAdded = false;

        if (!$table->hasColumn('menuindex')) {
            $table->addColumn('menuindex', 'integer', [
                'signed' => false,
                'null' => false,
                'default' => 0,
                'after' => 'category_id',
            ]);
            $table->update();
            $columnAdded = true;
        }

        if ($columnAdded) {
            $this->backfillMenuindexFromProducts();
        }

        $table = $this->table(self::TABLE);
        if (!$table->hasIndexByName(self::INDEX_CATEGORY_MENUINDEX)) {
            $table->addIndex(['category_id', 'menuindex'], [
                'name' => self::INDEX_CATEGORY_MENUINDEX,
            ]);
            $table->update();
        }
    }

    public function down(): void
    {
        $table = $this->table(self::TABLE);

        if ($table->hasIndexByName(self::INDEX_CATEGORY_MENUINDEX)) {
            $table->removeIndexByName(self::INDEX_CATEGORY_MENUINDEX);
        }

        if ($table->hasColumn('menuindex')) {
            $table->removeColumn('menuindex');
        }

        $table->update();
    }

    private function backfillMenuindexFromProducts(): void
    {
        if (!$this->hasTable(self::TABLE)) {
            return;
        }

        $prefix = (string) ($this->getAdapter()->getOption('table_prefix') ?? '');
        $members = '`' . $prefix . self::TABLE . '`';
        $resources = '`' . $prefix . 'site_content`';

        $this->execute(
            "UPDATE {$members} AS m "
            . "INNER JOIN {$resources} AS sc ON sc.id = m.product_id "
            . 'SET m.menuindex = sc.menuindex'
        );
    }
}
