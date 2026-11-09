<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Product;

use MiniShop3\Services\Product\ProductImageService;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/stubs/ModxStub.php';

/**
 * Regression #998: initializeMediaSource() must not create containers on
 * the read path (ms_gallery snippet renders it on every product card).
 *
 * `createContainer()` logs `directory_create` on every call (MODX core bug
 * with Flysystem `fileExists()` on directories), which flooded
 * `modx_manager_log`. Containers are created only when `$ensureContainer`
 * is true (gallery upload path).
 */
final class ProductImageServiceContainerTest extends TestCase
{
    public function testReadPathDoesNotCreateContainer(): void
    {
        $source = new CountingMediaSource();
        $service = new ProductImageService($this->makeModx($source));

        $result = $service->initializeMediaSource(
            $this->productData(),
            'web'
        );

        self::assertSame($source, $result);
        self::assertSame(0, $source->createCalls);
        self::assertSame([], $source->containers);
    }

    public function testEnsureFlagCreatesContainer(): void
    {
        $source = new CountingMediaSource();
        $service = new ProductImageService($this->makeModx($source));

        $result = $service->initializeMediaSource(
            $this->productData(),
            'web',
            true
        );

        self::assertSame($source, $result);
        self::assertSame(1, $source->createCalls);
        self::assertSame([['42/', '/']], $source->containers);
    }

    private function productData(): \MiniShop3\Model\msProductData
    {
        return new class (['id' => 42, 'source_id' => 1]) extends \MiniShop3\Model\msProductData {
            /** @param array<string, mixed> $fields */
            public function __construct(private array $fields)
            {
            }

            public function get($k, $format = null, $formatTemplate = null)
            {
                return $this->fields[$k] ?? null;
            }
        };
    }

    private function makeModx(CountingMediaSource $source): modX
    {
        return new class ($source) extends modX {
            public function __construct(private readonly CountingMediaSource $source)
            {
                parent::__construct();
            }

            public function getOption(string $key, $options = null, $default = null)
            {
                return $default;
            }

            public function getObject($className, $id = null, $criteria = null)
            {
                return $this->source;
            }
        };
    }
}

final class CountingMediaSource
{
    public int $createCalls = 0;

    /** @var list<array{0: string, 1: string}|string> */
    public array $containers = [];

    public function initialize(string $ctx): bool
    {
        return true;
    }

    public function createContainer(string $path, string $parent): bool
    {
        $this->createCalls++;
        $this->containers[] = [$path, $parent];

        return true;
    }
}
