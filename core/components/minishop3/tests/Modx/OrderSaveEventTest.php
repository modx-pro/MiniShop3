<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Modx;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msOrder;
use MiniShop3\Tests\Modx\Support\ExtraTestCase;
use MiniShop3\Utils\EventGate;

final class OrderSaveEventTest extends ExtraTestCase
{
    public function testMsOnSaveOrderPluginRunsAndEventGateSeesCancel(): void
    {
        $this->registerPlugin(
            'msOnSaveOrder',
            '$modx->setPlaceholder("ms3_testbench_save", "1"); return "cancel";',
        );

        $order = $this->modx->newObject(msOrder::class);
        $order->fromArray([
            'num' => 'TB-EVENT-1',
            'cost' => 10.0,
            'context' => 'web',
        ]);
        self::assertTrue($order->save());

        self::assertSame('1', $this->modx->getPlaceholder('ms3_testbench_save'));

        $result = $this->triggerEvent('msOnSaveOrder', [
            'mode' => 'upd',
            'object' => $order,
            'msOrder' => $order,
        ]);
        self::assertIsArray($result);
        self::assertTrue(EventGate::isCancelled($result));

        /** @var MiniShop3 $ms3 */
        $ms3 = $this->modx->services->get('ms3');
        $gated = $ms3->utils->invokeEvent('msOnSaveOrder', [
            'object' => $order,
            'msOrder' => $order,
        ]);
        self::assertFalse($gated['success']);
    }
}
