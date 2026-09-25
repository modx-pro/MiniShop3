<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Native SEO overrides for msProduct and msCategory resources (#790).
 *
 * Creates `ms3_resource_seo` with `resource_id` PK and nullable string columns. Empty
 * values mean "fall back to the public SEO builder defaults"; non-empty values overlay
 * the public seo payload after the TV map and before the msOnGetPublicSeo event.
 */
final class CreateResourceSeo extends AbstractMigration
{
    public function up(): void
    {
        $prefix = $this->getAdapter()->getOption('table_prefix') ?? '';
        $fqn = $prefix . 'ms3_resource_seo';

        if ($this->hasTable('ms3_resource_seo')) {
            $this->output->writeln('<comment>Table ' . $fqn . ' already exists, skipping create</comment>');

            return;
        }

        require_once __DIR__ . '/_modx.php';
        $modx = ms3MigrationBootstrap();
        if ($modx === null) {
            throw new \RuntimeException('Cannot bootstrap MODX for ms3_resource_seo');
        }

        $created = $modx->getManager()->createObjectContainer(\MiniShop3\Model\msResourceSeo::class);
        if (!$created) {
            throw new \RuntimeException('Failed to create table ' . $fqn);
        }

        ms3MigrationRefreshPhinxTransaction($this->getAdapter());
        $this->output->writeln('<info>Created table ' . $fqn . '</info>');
    }

    public function down(): void
    {
        if ($this->hasTable('ms3_resource_seo')) {
            $this->table('ms3_resource_seo')->drop()->save();
        }
    }
}
