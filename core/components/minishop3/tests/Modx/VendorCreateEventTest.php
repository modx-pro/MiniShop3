<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Modx;

use MiniShop3\Model\msVendor;
use MiniShop3\Processors\Settings\Vendor\Create as VendorCreate;
use MiniShop3\Tests\Modx\Support\ExtraTestCase;

final class VendorCreateEventTest extends ExtraTestCase
{
    public function testMsOnVendorCreatePluginRunsFromProcessor(): void
    {
        $this->registerPlugin(
            'msOnVendorCreate',
            '$modx->setPlaceholder("ms3_testbench_vendor", $object->get("name"));',
        );

        $this->actingAsSudo();
        $name = 'TB Event Vendor ' . bin2hex(random_bytes(3));
        $response = $this->runExtraProcessor(VendorCreate::class, ['name' => $name]);

        $this->assertProcessorSuccess($response);
        $this->assertObjectExists(msVendor::class, ['name' => $name]);
        self::assertSame($name, $this->modx->getPlaceholder('ms3_testbench_vendor'));
    }
}
