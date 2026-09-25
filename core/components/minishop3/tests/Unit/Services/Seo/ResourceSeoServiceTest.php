<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Seo;

use MiniShop3\Services\Seo\ResourceSeoService;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

/**
 * Unit coverage for {@see ResourceSeoService} sanitization and persistence shape.
 *
 * The service talks to xPDO via modX::getObject / newObject / removeCollection / getIterator.
 * We stub a minimal modX that records calls and returns array-backed fake rows so the
 * sanitization rules (robots allowlist, canonical/og_image URL allowlist, empty = unset)
 * can be exercised without a real MODX install.
 */
final class ResourceSeoServiceTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 3) . '/stubs/ModxStub.php';
        }
    }

    public function testGetReturnsEmptyShapeWhenRowMissing(): void
    {
        $service = new ResourceSeoService($this->modx(null));

        self::assertSame($this->emptyShape(), $service->get(42));
    }

    public function testGetReturnsEmptyShapeForZeroId(): void
    {
        $service = new ResourceSeoService($this->modx(null));

        self::assertSame($this->emptyShape(), $service->get(0));
    }

    public function testGetNormalizesStoredRow(): void
    {
        $row = $this->fakeRow([
            'resource_id' => 42,
            'title' => '  Spaced  ',
            'description' => 'Desc',
            'canonical' => 'https://shop.example/kettle',
            'robots' => 'noindex,follow',
            'og_title' => '',
            'og_description' => null,
            'og_image' => '/assets/og.jpg',
        ]);

        $service = new ResourceSeoService($this->modx($row));

        $shape = $service->get(42);

        self::assertSame('Spaced', $shape['title']);
        self::assertSame('Desc', $shape['description']);
        self::assertSame('https://shop.example/kettle', $shape['canonical']);
        self::assertSame('noindex,follow', $shape['robots']);
        self::assertNull($shape['og_title']);
        self::assertNull($shape['og_description']);
        self::assertSame('/assets/og.jpg', $shape['og_image']);
    }

    public function testSaveUpsertsSanitizedValues(): void
    {
        $capture = $this->captureNewObject();
        $service = new ResourceSeoService($this->modx(null, $capture));

        $result = $service->save(42, [
            'title' => '  Override  ',
            'description' => 'Desc',
            'canonical' => '/custom/path/',
            'robots' => 'NOINDEX, FOLLOW',
            'og_title' => 'Social headline',
            'og_description' => 'Social blurb',
            'og_image' => 'https://cdn.example/og.jpg',
            // Unknown keys must be ignored.
            'jsonld' => '{"leak":true}',
        ]);

        self::assertTrue($result['ok']);
        self::assertSame('Override', $capture['set']['title']);
        self::assertSame('Desc', $capture['set']['description']);
        self::assertSame('/custom/path/', $capture['set']['canonical']);
        self::assertSame('noindex,follow', $capture['set']['robots']);
        self::assertSame('Social headline', $capture['set']['og_title']);
        self::assertSame('Social blurb', $capture['set']['og_description']);
        self::assertSame('https://cdn.example/og.jpg', $capture['set']['og_image']);
        self::assertArrayNotHasKey('jsonld', $capture['set']);
        self::assertSame(42, $capture['set']['resource_id']);
    }

    public function testSaveDropsRowWhenAllFieldsEmpty(): void
    {
        $existing = $this->fakeRow(['resource_id' => 42, 'title' => 'Old']);
        $modx = $this->modx($existing);
        $service = new ResourceSeoService($modx);

        $result = $service->save(42, [
            'title' => '',
            'description' => '',
            'canonical' => '',
            'robots' => '',
            'og_title' => null,
            'og_description' => null,
            'og_image' => null,
        ]);

        self::assertTrue($result['ok']);
        self::assertSame($this->emptyShape(), $result['data']);
        self::assertTrue($existing->removed);
    }

    public function testSaveRejectsUnknownRobotsValue(): void
    {
        $capture = $this->captureNewObject();
        $service = new ResourceSeoService($this->modx(null, $capture));

        // Unknown robots is dropped to null; remaining fields empty → row is not created.
        $result = $service->save(42, ['robots' => 'all,index']);

        self::assertTrue($result['ok']);
        self::assertSame($this->emptyShape(), $result['data']);
        self::assertNull($capture['instance']);
    }

    public function testSaveRejectsNonHttpSchemesForCanonical(): void
    {
        $capture = $this->captureNewObject();
        $service = new ResourceSeoService($this->modx(null, $capture));

        // javascript: is dropped; remaining fields empty → row is not created.
        $result = $service->save(42, ['canonical' => 'javascript:alert(1)']);

        self::assertTrue($result['ok']);
        self::assertNull($capture['instance']);
    }

    public function testSaveKeepsAbsoluteHttpsAndRootRelativeUrls(): void
    {
        $capture = $this->captureNewObject();
        $service = new ResourceSeoService($this->modx(null, $capture));

        $service->save(42, [
            'canonical' => 'https://shop.example/kettle',
            'og_image' => '/assets/og.jpg',
        ]);

        self::assertSame('https://shop.example/kettle', $capture['set']['canonical']);
        self::assertSame('/assets/og.jpg', $capture['set']['og_image']);
    }

    public function testSaveRequiresResourceId(): void
    {
        $service = new ResourceSeoService($this->modx(null));

        $result = $service->save(0, ['title' => 'X']);

        self::assertFalse($result['ok']);
        self::assertArrayHasKey('resource_id', $result['errors']);
    }

    public function testDeleteRemovesRow(): void
    {
        $modx = $this->modx(null);
        $service = new ResourceSeoService($modx);

        self::assertTrue($service->delete(42));
        self::assertSame(
            [\MiniShop3\Model\msResourceSeo::class => ['resource_id' => 42]],
            $modx->removed,
        );
    }

    public function testDeleteIgnoresZeroId(): void
    {
        $service = new ResourceSeoService($this->modx(null));

        self::assertFalse($service->delete(0));
    }

    public function testBatchByIdsReturnsShapesForKnownIds(): void
    {
        $rows = [
            $this->fakeRow(['resource_id' => 1, 'title' => 'One']),
            $this->fakeRow(['resource_id' => 3, 'title' => 'Three']),
        ];
        $modx = $this->modxWithIterator($rows);
        $service = new ResourceSeoService($modx);

        $batch = $service->batchByIds([1, 2, 3]);

        self::assertSame('One', $batch[1]['title']);
        self::assertSame($this->emptyShape(), $batch[2]);
        self::assertSame('Three', $batch[3]['title']);
    }

    public function testBatchByIdsDropsNonPositiveIds(): void
    {
        $service = new ResourceSeoService($this->modxWithIterator([]));

        self::assertSame([], $service->batchByIds([0, -1, 'not-int']));
    }

    /**
     * @return array<string, string|null>
     */
    private function emptyShape(): array
    {
        return [
            'title' => null,
            'description' => null,
            'canonical' => null,
            'robots' => null,
            'og_title' => null,
            'og_description' => null,
            'og_image' => null,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function fakeRow(array $data): object
    {
        return new class ($data) {
            public bool $removed = false;

            public function __construct(public array $data)
            {
            }

            public function get(string $key)
            {
                return $this->data[$key] ?? null;
            }

            public function set(string $key, $value): void
            {
                $this->data[$key] = $value;
            }

            public function toArray(): array
            {
                return $this->data;
            }

            public function save(): bool
            {
                return true;
            }

            public function remove(): bool
            {
                $this->removed = true;

                return true;
            }
        };
    }

    /**
     * @param object|null $existing
     * @param array{instance: object|null, set: array<string, mixed>} $capture
     */
    private function modx(?object $existing, ?array &$capture = null): modX
    {
        $capture = $capture ?? ['instance' => null, 'set' => []];

        return new class ($existing, $capture) extends modX {
            public array $removed = [];

            private ?object $existing;

            /** @var array{instance: object|null, set: array<string, mixed>} */
            private array $capture;

            public function __construct(?object $existing, array &$capture)
            {
                parent::__construct();
                $this->existing = $existing;
                $this->capture = &$capture;
            }

            public function getObject($className, $criteria = null, $cacheFlag = true)
            {
                return $this->existing;
            }

            public function newObject($className)
            {
                $row = new class ($this->capture) {
                    /** @var array{instance: object|null, set: array<string, mixed>} */
                    private array $capture;

                    public function __construct(array &$capture)
                    {
                        $this->capture = &$capture;
                    }

                    public function set(string $key, $value): void
                    {
                        $this->capture['set'][$key] = $value;
                    }

                    public function get(string $key)
                    {
                        return $this->capture['set'][$key] ?? null;
                    }

                    public function save(): bool
                    {
                        $this->capture['instance'] = $this;

                        return true;
                    }

                    /**
                     * @return array<string, mixed>
                     */
                    public function toArray(): array
                    {
                        return $this->capture['set'];
                    }
                };

                return $row;
            }

            public function removeCollection($className, $criteria)
            {
                $this->removed[$className] = $criteria;

                return 1;
            }
        };
    }

    /**
     * @param list<object> $rows
     */
    private function modxWithIterator(array $rows): modX
    {
        return new class ($rows) extends modX {
            public function __construct(private array $rows)
            {
                parent::__construct();
            }

            public function getIterator($className, $criteria = null, $cacheFlag = true)
            {
                foreach ($this->rows as $row) {
                    yield $row;
                }
            }

            public function newQuery($className, $criteria = null, $cacheFlag = true)
            {
                return new class {
                    public function where($x): void
                    {
                    }
                };
            }
        };
    }

    /**
     * @return array{instance: object|null, set: array<string, mixed>}
     */
    private function captureNewObject(): array
    {
        return ['instance' => null, 'set' => []];
    }
}
