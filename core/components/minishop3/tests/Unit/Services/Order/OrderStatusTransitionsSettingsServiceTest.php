<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Order;

use MiniShop3\Model\msOrderStatus;
use MiniShop3\Services\Order\OrderStatusTransitionPolicy;
use MiniShop3\Services\Order\OrderStatusTransitionsSettingsService;
use MODX\Revolution\modSystemSetting;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

final class OrderStatusTransitionsSettingsServiceTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 3) . '/stubs/ModxStub.php';
        }
    }

    public function testToCsvRoundTripWithResolve(): void
    {
        $csv = OrderStatusTransitionPolicy::toCsv([
            3 => [4 => true],
            2 => [3 => true, 5 => true],
        ]);
        self::assertSame('2:3,2:5,3:4', $csv);

        $resolved = OrderStatusTransitionPolicy::resolve($csv);
        self::assertSame(OrderStatusTransitionPolicy::MODE_ON, $resolved['mode']);
        self::assertTrue(isset($resolved['edges'][2][3]));
        self::assertTrue(isset($resolved['edges'][2][5]));
        self::assertTrue(isset($resolved['edges'][3][4]));
    }

    public function testEmptyEdgesSerializeToEmptyString(): void
    {
        self::assertSame('', OrderStatusTransitionPolicy::toCsv([]));
        self::assertSame(
            OrderStatusTransitionPolicy::MODE_OFF,
            OrderStatusTransitionPolicy::resolve('')['mode']
        );
    }

    public function testStructurallyForbiddenFinalAndFixed(): void
    {
        $service = new OrderStatusTransitionsSettingsService($this->createStub(modX::class));

        $final = ['id' => 5, 'final' => true, 'fixed' => false, 'position' => 40];
        $fixed = ['id' => 3, 'final' => false, 'fixed' => true, 'position' => 20];
        $earlier = ['id' => 2, 'final' => false, 'fixed' => false, 'position' => 10];
        $later = ['id' => 4, 'final' => false, 'fixed' => false, 'position' => 30];

        self::assertTrue($service->isStructurallyForbidden($final, $later));
        self::assertTrue($service->isStructurallyForbidden($fixed, $earlier));
        self::assertTrue($service->isStructurallyForbidden($fixed, $fixed));
        self::assertFalse($service->isStructurallyForbidden($fixed, $later));
        self::assertFalse($service->isStructurallyForbidden($earlier, $later));
    }

    public function testSaveEdgesWritesCsvAndDropsForbiddenPairs(): void
    {
        $setting = new class {
            public string $value = 'broken';

            public function set(string $key, mixed $value): void
            {
                if ($key === 'value') {
                    $this->value = (string) $value;
                }
            }

            public function save(): bool
            {
                return true;
            }

            public function fromArray(array $data, string $prefix = '', bool $setRaw = false, bool $silent = false): void
            {
            }
        };

        $statuses = [
            $this->statusRow(2, 'new', 10, false, false),
            $this->statusRow(3, 'paid', 20, false, true),
            $this->statusRow(4, 'sent', 30, false, false),
            $this->statusRow(5, 'cancelled', 40, true, false),
        ];

        $modx = new class ($statuses, $setting) extends modX {
            /** @var list<object> */
            private array $statuses;
            private object $setting;
            private string $option = '';
            public $cacheManager;

            public function __construct(array $statuses, object $setting)
            {
                $this->statuses = $statuses;
                $this->setting = $setting;
                $this->cacheManager = new class {
                    public function refresh(array $options = []): bool
                    {
                        return true;
                    }
                };
            }

            public function getOption(string $key, $options = null, $default = null)
            {
                return $key === OrderStatusTransitionsSettingsService::SETTING_KEY
                    ? $this->option
                    : $default;
            }

            public function setOption(string $key, mixed $value): void
            {
                if ($key === OrderStatusTransitionsSettingsService::SETTING_KEY) {
                    $this->option = (string) $value;
                }
            }

            public function getObject($className, $criteria = null, $cacheFlag = true)
            {
                if ($className === modSystemSetting::class || $className === 'modSystemSetting') {
                    return $this->setting;
                }

                return null;
            }

            public function newObject($className, $fields = [])
            {
                return $this->setting;
            }

            public function newQuery($className, $criteria = null, $cacheFlag = true)
            {
                return new class {
                    public function sortby(string $column, string $dir = 'ASC'): self
                    {
                        return $this;
                    }
                };
            }

            public function getIterator($className, $criteria = null, $cacheFlag = true)
            {
                if ($className === msOrderStatus::class) {
                    return new \ArrayIterator($this->statuses);
                }

                return new \ArrayIterator([]);
            }
        };

        $service = new OrderStatusTransitionsSettingsService($modx);
        $matrix = $service->saveEdges([
            [2, 3],
            [2, 2], // self
            [5, 2], // final from
            [3, 2], // fixed backward
            [3, 4], // allowed
            [99, 2], // unknown
            'bad',
        ]);

        self::assertSame('2:3,3:4', $setting->value);
        self::assertSame(OrderStatusTransitionPolicy::MODE_ON, $matrix['mode']);
        self::assertSame([[2, 3], [3, 4]], $matrix['edges']);
        self::assertContains(5, $matrix['unreachable']);
    }

    public function testSaveEmptyEdgesClearsAllowList(): void
    {
        $setting = new class {
            public string $value = '2:3';

            public function set(string $key, mixed $value): void
            {
                if ($key === 'value') {
                    $this->value = (string) $value;
                }
            }

            public function save(): bool
            {
                return true;
            }
        };

        $statuses = [
            $this->statusRow(2, 'new', 10, false, false),
            $this->statusRow(3, 'paid', 20, false, false),
        ];

        $modx = new class ($statuses, $setting) extends modX {
            private array $statuses;
            private object $setting;
            private string $option = '2:3';
            public $cacheManager = null;

            public function __construct(array $statuses, object $setting)
            {
                $this->statuses = $statuses;
                $this->setting = $setting;
            }

            public function getOption(string $key, $options = null, $default = null)
            {
                return $key === OrderStatusTransitionsSettingsService::SETTING_KEY
                    ? $this->option
                    : $default;
            }

            public function setOption(string $key, mixed $value): void
            {
                if ($key === OrderStatusTransitionsSettingsService::SETTING_KEY) {
                    $this->option = (string) $value;
                }
            }

            public function getObject($className, $criteria = null, $cacheFlag = true)
            {
                return $this->setting;
            }

            public function newQuery($className, $criteria = null, $cacheFlag = true)
            {
                return new class {
                    public function sortby(string $column, string $dir = 'ASC'): self
                    {
                        return $this;
                    }
                };
            }

            public function getIterator($className, $criteria = null, $cacheFlag = true)
            {
                return new \ArrayIterator($this->statuses);
            }
        };

        $service = new OrderStatusTransitionsSettingsService($modx);
        $matrix = $service->saveEdges([]);

        self::assertSame('', $setting->value);
        self::assertSame(OrderStatusTransitionPolicy::MODE_OFF, $matrix['mode']);
        self::assertSame([], $matrix['edges']);
        self::assertSame([], $matrix['unreachable']);
    }

    private function statusRow(int $id, string $name, int $position, bool $final, bool $fixed): object
    {
        return new class ($id, $name, $position, $final, $fixed) {
            public function __construct(
                private int $id,
                private string $name,
                private int $position,
                private bool $final,
                private bool $fixed
            ) {
            }

            public function get(string $key): mixed
            {
                return match ($key) {
                    'id' => $this->id,
                    'name' => $this->name,
                    'color' => '000000',
                    'active' => true,
                    'final' => $this->final,
                    'fixed' => $this->fixed,
                    'position' => $this->position,
                    default => null,
                };
            }
        };
    }
}
