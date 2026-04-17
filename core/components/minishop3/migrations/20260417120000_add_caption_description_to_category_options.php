<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Optional per-category caption/description for options (issue #200).
 * NULL or empty means inherit from ms3_options.
 */
final class AddCaptionDescriptionToCategoryOptions extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('ms3_category_options');

        if (!$table->hasColumn('caption')) {
            $table->addColumn('caption', 'string', [
                'limit' => 191,
                'null' => true,
                'default' => null,
                'after' => 'required',
                'comment' => 'Override option caption for this category; NULL inherits global',
            ]);
            $table->update();
        }

        if (!$table->hasColumn('description')) {
            $table = $this->table('ms3_category_options');
            $table->addColumn('description', 'text', [
                'null' => true,
                'default' => null,
                'after' => 'caption',
                'comment' => 'Override option description for this category; NULL inherits global',
            ]);
            $table->update();
        }
    }

    public function down(): void
    {
        $table = $this->table('ms3_category_options');

        if ($table->hasColumn('description')) {
            $table->removeColumn('description')->update();
        }

        $table = $this->table('ms3_category_options');
        if ($table->hasColumn('caption')) {
            $table->removeColumn('caption')->update();
        }
    }
}
