<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Rename 'rank' column to 'sort_order' in ms3_model_fields table
 *
 * 'rank' is a reserved word in MySQL 8.0+ (window function RANK())
 * which causes SQL errors when used without backtick escaping.
 *
 * This migration renames the column to avoid conflicts with reserved words.
 */
final class RenameModelFieldsRankToSortOrder extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('ms3_model_fields');

        // Check if 'rank' column exists and 'sort_order' doesn't
        if ($table->hasColumn('rank') && !$table->hasColumn('sort_order')) {
            // Rename column
            $table->renameColumn('rank', 'sort_order')->update();

            // Update index (drop old, create new)
            if ($table->hasIndex(['model', 'visible', 'rank'])) {
                $table->removeIndex(['model', 'visible', 'rank'])->update();
            }

            $table->addIndex(['model', 'visible', 'sort_order'], [
                'unique' => false,
                'name' => 'idx_model_visible_sort_order',
            ])->update();
        }
    }

    public function down(): void
    {
        $table = $this->table('ms3_model_fields');

        // Reverse: rename 'sort_order' back to 'rank'
        if ($table->hasColumn('sort_order') && !$table->hasColumn('rank')) {
            $table->renameColumn('sort_order', 'rank')->update();

            // Update index back
            if ($table->hasIndex(['model', 'visible', 'sort_order'])) {
                $table->removeIndex(['model', 'visible', 'sort_order'])->update();
            }

            $table->addIndex(['model', 'visible', 'rank'], [
                'unique' => false,
                'name' => 'idx_model_visible_rank',
            ])->update();
        }
    }
}
