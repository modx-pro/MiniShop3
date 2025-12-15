<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddModelFieldsColumns extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('ms3_model_fields');

        // Add section_id column (FK to ms3_model_field_sections)
        $table->addColumn('section_id', 'integer', [
            'null' => true,
            'after' => 'rank',
            'comment' => 'FK to ms3_model_field_sections',
        ]);

        // Add width column (1-12 grid columns)
        $table->addColumn('width', 'integer', [
            'null' => false,
            'default' => 6,
            'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
            'after' => 'section_id',
            'comment' => 'Field width (1-12 grid columns)',
        ]);

        // Add placeholder column
        $table->addColumn('placeholder', 'string', [
            'limit' => 255,
            'null' => true,
            'after' => 'width',
            'comment' => 'Input placeholder',
        ]);

        // Add description column
        $table->addColumn('description', 'text', [
            'null' => true,
            'after' => 'placeholder',
            'comment' => 'Field description/help text',
        ]);

        // Add index on section_id
        $table->addIndex(['section_id'], [
            'unique' => false,
            'name' => 'idx_section_id',
        ]);

        $table->update();
    }

    public function down(): void
    {
        $table = $this->table('ms3_model_fields');

        $table->removeIndex(['section_id']);
        $table->removeColumn('section_id');
        $table->removeColumn('width');
        $table->removeColumn('placeholder');
        $table->removeColumn('description');

        $table->update();
    }
}
