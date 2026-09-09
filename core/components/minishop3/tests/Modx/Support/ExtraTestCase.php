<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Modx\Support;

use MiniShop3\MiniShop3;
use MiniShop3\ServiceRegistry;
use ModxKit\Testbench\Concerns\RefreshesDatabase;
use ModxKit\Testbench\Package\PackageDefinition;
use ModxKit\Testbench\TestCase;
use MODX\Revolution\modEvent;
use MODX\Revolution\modPlugin;
use MODX\Revolution\modPluginEvent;
use MODX\Revolution\Processors\ProcessorResponse;
use ReflectionClass;
use xPDO\Om\xPDOObject;

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
            ->tables(...PackageModels::tables())
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

    protected function processorsPath(): string
    {
        return $this->extraCorePath() . 'src/Processors/';
    }

    /**
     * @param class-string $class
     * @param array<string, mixed> $properties
     */
    protected function runExtraProcessor(string $class, array $properties = []): ProcessorResponse
    {
        return $this->runProcessor($class, $properties, [
            'processors_path' => $this->processorsPath(),
        ]);
    }

    /**
     * @return list<string>
     */
    protected function defaultServiceKeys(): array
    {
        $reflection = new ReflectionClass(ServiceRegistry::class);
        $registry = $reflection->newInstanceWithoutConstructor();
        $map = $reflection->getProperty('defaultServices')->getValue($registry);
        self::assertIsArray($map);

        return array_keys($map);
    }

    protected function actingAsSudo(): void
    {
        $this->actingAs($this->createUser([
            'username' => 'sudo-' . bin2hex(random_bytes(3)),
            'sudo' => true,
        ]));
    }

    protected function actingAsPlain(): void
    {
        $this->actingAs($this->createUser([
            'username' => 'plain-' . bin2hex(random_bytes(3)),
        ]));
    }

    /**
     * @param class-string<xPDOObject> $class
     * @param array<string, mixed> $fields
     */
    protected function persistObject(string $class, array $fields): xPDOObject
    {
        $object = $this->modx->newObject($class);
        self::assertNotNull($object, $class . ' is not in the xPDO map');
        $object->fromArray($fields);
        self::assertTrue($object->save(), 'Failed to save ' . $class);

        return $object;
    }

    /**
     * @param class-string $class
     */
    protected function assertTableExists(string $class): void
    {
        $quoted = $this->modx->getTableName($class);
        self::assertNotFalse($quoted, 'No table name for ' . $class);
        $table = str_replace('`', '', (string) $quoted);
        $statement = $this->modx->prepare('SHOW TABLES LIKE ?');
        $statement->execute([$table]);
        self::assertNotFalse($statement->fetch(), 'Missing table ' . $table . ' for ' . $class);
    }

    protected function registerPlugin(string $eventName, string $phpCode): void
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
