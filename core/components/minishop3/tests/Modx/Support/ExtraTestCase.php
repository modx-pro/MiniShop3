<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Modx\Support;

use MiniShop3\MiniShop3;
use MiniShop3\ServiceRegistry;
use MiniShop3\Utils\ExtraFields;
use ModxKit\Testbench\Concerns\RefreshesDatabase;
use ModxKit\Testbench\Package\PackageDefinition;
use ModxKit\Testbench\TestCase;
use MODX\Revolution\modEvent;
use MODX\Revolution\modPlugin;
use MODX\Revolution\modPluginEvent;
use MODX\Revolution\modX;
use MODX\Revolution\Processors\ProcessorResponse;
use ReflectionClass;
use ReflectionProperty;
use xPDO\Om\xPDOObject;

/**
 * Live MODX 3 kernel for MiniShop3 (testbench level 2).
 *
 * Isolated from stub PHPUnit via phpunit.modx.xml. RefreshesDatabase is required:
 * Phinx migrations run DDL once per process, then the baseline snapshot is recaptured
 * ({@see PhinxSchemaBootstrap}).
 */
abstract class ExtraTestCase extends TestCase
{
    use RefreshesDatabase;
    use GrantsContextPermissions;

    protected function packageDefinition(): PackageDefinition
    {
        $core = $this->extraCorePath();
        $assets = $this->extraAssetsPath();

        // No ->tables(...): schema comes from Phinx (PhinxSchemaBootstrap).
        return PackageDefinition::make('minishop3')
            ->corePath($core)
            ->assetsPath($assets)
            ->model('MiniShop3\\Model', $core . 'src/', null, 'MiniShop3\\')
            ->settings([
                'ms3_core_path' => $core,
                'ms3_token_name' => 'ms3_token',
            ])
            ->service('ms3', fn (): MiniShop3 => new MiniShop3($this->modx));
    }

    protected function setUp(): void
    {
        // Before parent::setUp() opens snapshot isolation and loadMap() hits ms3_extra_fields.
        PhinxSchemaBootstrap::ensure($this->extraCorePath());

        parent::setUp();
    }

    protected function afterPackageRegistered(): void
    {
        /** @var MiniShop3 $ms3 */
        $ms3 = $this->modx->services->get('ms3');

        // ExtraFields::loadMap() merges into $modx->map and file-caches meta. RefreshesDatabase
        // rolls back msExtraField rows but leaves map/cache dirty across tests
        // (ExtraFieldMapTest → later msVendor asserts). Rebuild a clean map each test.
        (new ExtraFields($this->modx))->clearCache();
        $mapLoaded = new ReflectionProperty(MiniShop3::class, 'mapLoaded');
        $mapLoaded->setAccessible(true);
        $mapLoaded->setValue($ms3, false);

        $inner = new ReflectionProperty($this->modx->map, 'map');
        $inner->setAccessible(true);
        /** @var array<string, mixed> $loaded */
        $loaded = $inner->getValue($this->modx->map);
        foreach (array_keys($loaded) as $class) {
            if (
                is_string($class)
                && str_starts_with($class, 'MiniShop3\\Model\\')
                && !str_contains($class, '\\mysql\\')
            ) {
                unset($this->modx->map[$class]);
            }
        }

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
        // Third arg setPrimaryKeys: fromArray skips PK fields (composite keys) otherwise.
        $object->fromArray($fields, '', true);
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
            $event->set('name', $eventName);
            $event->set('service', 1);
            $event->set('groupname', 'MiniShop3');
            self::assertTrue($event->save(), 'Failed to save modEvent ' . $eventName);
        }

        $pluginEvent = $this->modx->newObject(modPluginEvent::class);
        $pluginEvent->fromArray([
            'pluginid' => $plugin->get('id'),
            'event' => $eventName,
            'priority' => 0,
        ], '', true);
        self::assertTrue($pluginEvent->save(), 'Failed to attach plugin to ' . $eventName);

        $pluginId = (int) $plugin->get('id');
        $this->modx->pluginCache[(string) $pluginId] = $plugin->toArray();
        $this->modx->eventMap[$eventName][$pluginId] = $pluginId;
    }

    /**
     * Force SESSION_STATE_INITIALIZED so processor $permission checks run (#696).
     * Testbench defaults to UNAVAILABLE and checkPolicy() short-circuits to true.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    protected function withProcessorPoliciesEnforced(callable $callback): mixed
    {
        $sessionState = new ReflectionProperty(modX::class, '_sessionState');
        $sessionState->setAccessible(true);
        $previous = $sessionState->getValue($this->modx);
        $sessionState->setValue($this->modx, modX::SESSION_STATE_INITIALIZED);

        try {
            // Processor::run() emits permission_denied_processor before loading getLanguageTopics().
            $this->modx->lexicon->load('default');

            return $callback();
        } finally {
            $sessionState->setValue($this->modx, $previous);
            $this->clearUserAttributeSessionCache();
        }
    }

    /**
     * @param non-empty-string $permission Processor $permission value expected in the lexicon message
     * @param string $action Value for [[+action]] (empty string matches Processor::run when action is unset)
     */
    protected function assertProcessorPermissionDenied(
        ProcessorResponse $response,
        string $permission,
        string $action = ''
    ): void {
        $this->assertProcessorFailure($response);
        self::assertSame(
            $this->modx->lexicon('permission_denied_processor', [
                'permission' => $permission,
                'action' => $action,
            ]),
            $response->getMessage()
        );
    }

    protected function clearUserAttributeSessionCache(): void
    {
        if (!isset($_SESSION) || !is_array($_SESSION)) {
            return;
        }

        $keys = preg_grep('/^modx\.user\..*attributes/', array_keys($_SESSION)) ?: [];
        foreach ($keys as $key) {
            unset($_SESSION[$key]);
        }
    }
}
