<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Modx\Support;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msExtraField;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderProduct;
use MiniShop3\Model\msOrderStatus;
use ModxKit\Testbench\Concerns\RefreshesDatabase;
use ModxKit\Testbench\Package\PackageDefinition;
use ModxKit\Testbench\TestCase;

/**
 * Live MODX 3 kernel for MiniShop3 (testbench level 2).
 *
 * Isolated from stub PHPUnit via phpunit.modx.xml. RefreshesDatabase is required:
 * PackageDefinition::tables() runs DDL, which commits the test transaction.
 */
abstract class ExtraTestCase extends TestCase
{
    use RefreshesDatabase;

    protected function packageDefinition(): PackageDefinition
    {
        $core = $this->extraCorePath();
        $assets = $this->extraAssetsPath();

        return PackageDefinition::make('minishop3')
            ->corePath($core)
            ->assetsPath($assets)
            ->model('MiniShop3\\Model', $core . 'src/', null, 'MiniShop3\\')
            ->tables(
                msOrder::class,
                msOrderProduct::class,
                msOrderStatus::class,
                msExtraField::class,
            )
            ->settings([
                'ms3_core_path' => $core,
                'ms3_token_name' => 'ms3_token',
            ])
            ->service('ms3', fn (): MiniShop3 => new MiniShop3($this->modx));
    }

    protected function afterPackageRegistered(): void
    {
        /** @var MiniShop3 $ms3 */
        $ms3 = $this->modx->services->get('ms3');
        $ms3->loadMap();
    }

    protected function extraCorePath(): string
    {
        return dirname(__DIR__, 3) . '/';
    }

    protected function extraAssetsPath(): string
    {
        return dirname($this->extraCorePath(), 3) . '/assets/components/minishop3/';
    }
}
