<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Modx;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msOrder;
use MiniShop3\Tests\Modx\Support\ExtraTestCase;
use MiniShop3\Utils\EventGate;
use MODX\Revolution\modEvent;
use MODX\Revolution\modPlugin;
use MODX\Revolution\modPluginEvent;

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

    private function registerPlugin(string $eventName, string $phpCode): void
    {
        $plugin = $this->modx->newObject(modPlugin::class);
        $plugin->fromArray([
            'name' => 'testbench-' . $eventName . '-' . bin2hex(random_bytes(3)),
            'plugincode' => $phpCode,
            'disabled' => false,
        ]);
        self::assertTrue($plugin->save(), 'Failed to save test plugin');

        if ($this->modx->getCount(modEvent::class, ['name' => $eventName]) === 0) {
            $event = $this->modx->newObject(modEvent::class);
            $event->fromArray([
                'name' => $eventName,
                'service' => 1,
                'groupname' => 'MiniShop3',
            ]);
            self::assertTrue($event->save(), 'Failed to save modEvent ' . $eventName);
        }

        $pluginEvent = $this->modx->newObject(modPluginEvent::class);
        $pluginEvent->fromArray([
            'pluginid' => $plugin->get('id'),
            'event' => $eventName,
            'priority' => 0,
        ]);
        self::assertTrue($pluginEvent->save(), 'Failed to attach plugin to ' . $eventName);

        $pluginId = (int) $plugin->get('id');
        $this->modx->pluginCache[(string) $pluginId] = $plugin->toArray();
        $this->modx->eventMap[$eventName][$pluginId] = $pluginId;
    }
}
