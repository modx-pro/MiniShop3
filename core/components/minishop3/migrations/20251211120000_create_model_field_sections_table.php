<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateModelFieldSectionsTable extends AbstractMigration
{
    public function change(): void
    {
        // Skip if table already created by InitialSchema (xPDO)
        if ($this->hasTable('ms3_model_field_sections')) {
            return;
        }

        // Create sections table
        $table = $this->table('ms3_model_field_sections', [
            'id' => true,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'encoding' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]);

        $table
            ->addColumn('model', 'string', [
                'limit' => 100,
                'null' => false,
                'comment' => 'Model class name: msOrder, msOrderAddress, msOrderProduct',
            ])
            ->addColumn('section_key', 'string', [
                'limit' => 100,
                'null' => false,
                'comment' => 'Unique section key within model',
            ])
            ->addColumn('label', 'string', [
                'limit' => 255,
                'null' => true,
                'comment' => 'Direct label text',
            ])
            ->addColumn('lexicon_key', 'string', [
                'limit' => 255,
                'null' => true,
                'comment' => 'Lexicon key for translation',
            ])
            ->addColumn('hidden', 'boolean', [
                'null' => false,
                'default' => false,
                'comment' => 'Hidden from display',
            ])
            ->addColumn('sort_order', 'integer', [
                'null' => false,
                'default' => 0,
                'comment' => 'Sort order',
            ])
            ->addColumn('is_default', 'boolean', [
                'null' => false,
                'default' => false,
                'comment' => 'Is default/system section',
            ])
            ->addIndex(['model', 'section_key'], [
                'unique' => true,
                'name' => 'idx_model_section',
            ])
            ->addIndex(['model', 'sort_order'], [
                'unique' => false,
                'name' => 'idx_model_sort',
            ])
            ->create();
    }
}
