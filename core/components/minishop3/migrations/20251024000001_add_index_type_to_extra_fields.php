<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Adds index_type field to ms3_extra_fields table
 * This allows specifying index type (NONE, INDEX, UNIQUE, FULLTEXT) for extra fields
 */
final class AddIndexTypeToExtraFields extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('ms3_extra_fields');

        $table->addColumn('index_type', 'string', [
            'limit' => 50,
            'null' => true,
            'default' => 'NONE',
            'after' => 'attributes',
            'comment' => 'Index type: NONE, INDEX, UNIQUE, FULLTEXT',
        ])
        ->update();
    }
}
