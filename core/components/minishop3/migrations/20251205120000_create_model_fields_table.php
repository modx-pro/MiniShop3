<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateModelFieldsTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('ms3_model_fields', [
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
            ->addColumn('name', 'string', [
                'limit' => 100,
                'null' => false,
                'comment' => 'Field name in model',
            ])
            ->addColumn('label', 'string', [
                'limit' => 255,
                'null' => true,
                'comment' => 'Lexicon key or display label',
            ])
            ->addColumn('xtype', 'string', [
                'limit' => 50,
                'null' => false,
                'default' => 'textfield',
                'comment' => 'Widget type: textfield, numberfield, combo, etc.',
            ])
            ->addColumn('visible', 'boolean', [
                'null' => false,
                'default' => true,
                'comment' => 'Show/hide field',
            ])
            ->addColumn('required', 'boolean', [
                'null' => false,
                'default' => false,
                'comment' => 'Field is required',
            ])
            ->addColumn('rank', 'integer', [
                'null' => false,
                'default' => 0,
                'comment' => 'Sort order',
            ])
            ->addColumn('config', 'text', [
                'null' => true,
                'comment' => 'Additional JSON config',
            ])
            ->addIndex(['model', 'name'], [
                'unique' => true,
                'name' => 'idx_model_name',
            ])
            ->addIndex(['model', 'visible', 'rank'], [
                'unique' => false,
                'name' => 'idx_model_visible_rank',
            ])
            ->create();
    }
}
