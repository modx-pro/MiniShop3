<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Modx\Support;

use ModxKit\Testbench\Database\SchemaInventory;
use ModxKit\Testbench\Environment\LockFile;
use ModxKit\Testbench\Environment\TestbenchKernel;
use MODX\Revolution\modX;
use Phinx\Config\Config as PhinxConfig;
use Phinx\Migration\Manager as PhinxManager;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Once-per-process Phinx migrate + testbench snapshot recapture for live tests (#695).
 *
 * Mirrors resolver_02_migrations.php. Always migrates (no-op when pending is empty) and
 * recaptures the RefreshesDatabase baseline so a crash between migrate and capture cannot
 * leave a bare-MODX snapshot while ms3 tables already exist.
 */
final class PhinxSchemaBootstrap
{
    private static bool $ready = false;

    public static function ensure(string $componentCorePath): void
    {
        if (self::$ready) {
            return;
        }

        $kernel = TestbenchKernel::instance();
        $modx = $kernel->modx();
        $modx->setOption('ms3_core_path', $componentCorePath);

        self::migrate($modx, $componentCorePath);
        $kernel->snapshots()->capture();
        self::syncLockTableCount($kernel);

        self::$ready = true;
    }

    public static function migrate(modX $modx, string $componentCorePath): void
    {
        $GLOBALS['modx'] = $modx;
        $configArray = require rtrim($componentCorePath, '/\\') . '/phinx.php';

        if (!isset($configArray['paths']['migrations'])) {
            throw new \RuntimeException('MiniShop3 phinx.php did not return a migrations path');
        }

        $manager = new PhinxManager(
            new PhinxConfig($configArray),
            new StringInput(''),
            new BufferedOutput(),
        );
        $manager->migrate('production');
    }

    private static function syncLockTableCount(TestbenchKernel $kernel): void
    {
        $workspace = $kernel->workspace();
        $lock = $workspace->readLock();
        if ($lock === null) {
            return;
        }

        $tableCount = SchemaInventory::countTablesWithPrefix($kernel->config()->database);
        $format = $kernel->snapshots()->format();
        $workspace->writeLock(new LockFile(
            fingerprint: $lock->fingerprint,
            modxVersion: $lock->modxVersion,
            provider: $lock->provider,
            tablePrefix: $lock->tablePrefix,
            installedAt: $lock->installedAt,
            hasSnapshot: true,
            tableCount: $tableCount,
            snapshotFormat: $format !== '' ? $format : $lock->snapshotFormat,
            installRevision: $lock->installRevision,
        ));
    }
}
