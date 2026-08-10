<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Adds preview_file_id to ms3_products so gallery preview can differ from sort order (#130).
 */
final class AddPreviewFileIdToProductData extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('ms3_products');

        if (!$table->hasColumn('preview_file_id')) {
            $table->addColumn('preview_file_id', 'integer', [
                'signed' => false,
                'null' => true,
                'default' => null,
                'after' => 'thumb',
                'comment' => 'msProductFile id used as product preview (nullable)',
            ]);
            $table->update();
        }
    }

    public function down(): void
    {
        $table = $this->table('ms3_products');

        if ($table->hasColumn('preview_file_id')) {
            $table->removeColumn('preview_file_id');
            $table->update();
        }
    }
}
