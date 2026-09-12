<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Modx;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msExtraField;
use MiniShop3\Model\msVendor;
use MiniShop3\Tests\Modx\Support\ExtraTestCase;
use MiniShop3\Utils\ExtraFields;
use ReflectionProperty;

final class ExtraFieldMapTest extends ExtraTestCase
{
    public function testActiveExtraFieldIsMergedIntoXpdoMap(): void
    {
        $key = 'tb_note_' . bin2hex(random_bytes(3));
        $this->persistObject(msVendor::class, ['name' => 'TB Extra Host']);
        $this->persistObject(msExtraField::class, [
            'class' => msVendor::class,
            'key' => $key,
            'label' => 'TB Note',
            'dbtype' => 'varchar',
            'phptype' => 'string',
            'precision' => '191',
            'null' => 1,
            'active' => 1,
        ]);

        $extra = new ExtraFields($this->modx);
        $extra->clearCache();
        $extra->loadMap();

        self::assertArrayHasKey($key, $this->modx->map[msVendor::class]['fields'] ?? []);
        self::assertSame('varchar', $this->modx->map[msVendor::class]['fieldMeta'][$key]['dbtype'] ?? null);
        self::assertSame('string', $this->modx->map[msVendor::class]['fieldMeta'][$key]['phptype'] ?? null);
    }

    protected function tearDown(): void
    {
        // RefreshesDatabase rolls back msExtraField rows; clear in-memory merge + file cache
        // so later tests do not SELECT phantom columns on msVendor.
        (new ExtraFields($this->modx))->clearCache();
        unset($this->modx->map[msVendor::class]);

        if ($this->modx->services->has('ms3')) {
            /** @var MiniShop3 $ms3 */
            $ms3 = $this->modx->services->get('ms3');
            $mapLoaded = new ReflectionProperty(MiniShop3::class, 'mapLoaded');
            $mapLoaded->setAccessible(true);
            $mapLoaded->setValue($ms3, false);
        }

        parent::tearDown();
    }
}
