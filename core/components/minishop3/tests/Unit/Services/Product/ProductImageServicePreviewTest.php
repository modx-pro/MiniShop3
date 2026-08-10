<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Product;

use MiniShop3\Model\msProductData;
use MiniShop3\Model\msProductFile;
use MiniShop3\Services\Product\ProductImageService;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class ProductImageServicePreviewTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 3) . '/stubs/ModxStub.php';
        }
    }

    public function testFindMainImageFileUsesPreviewFileIdWhenValid(): void
    {
        $preview = $this->fakeFile(42);
        $service = new ProductImageService($this->modxReturning($preview));
        $data = $this->fakeProductData(['id' => 7, 'preview_file_id' => 42]);

        $resolved = $this->invokeFind($service, $data);

        self::assertSame($preview, $resolved);
        self::assertSame(42, $data->get('preview_file_id'));
    }

    public function testFindMainImageFileFallsBackWithoutMutatingStalePreview(): void
    {
        $fallback = $this->fakeFile(9);
        $calls = 0;
        $modx = new class ($fallback, $calls) extends modX {
            public function __construct(
                private msProductFile $fallback,
                private int &$calls,
            ) {
                parent::__construct();
            }

            public function getObject($className, $criteria = null, $cacheFlag = true)
            {
                $this->calls++;
                if ($this->calls === 1) {
                    return null;
                }

                return $this->fallback;
            }

            public function newQuery($className, $criteria = null, $cacheFlag = true)
            {
                return new class {
                    public function where($criteria, $conjunction = null)
                    {
                        return $this;
                    }

                    public function sortby($column, $direction = '')
                    {
                        return $this;
                    }
                };
            }
        };

        $service = new ProductImageService($modx);
        $data = $this->fakeProductData(['id' => 7, 'preview_file_id' => 99]);
        $stale = false;
        $resolved = $this->invokeFind($service, $data, $stale);

        self::assertSame($fallback, $resolved);
        self::assertTrue($stale);
        self::assertSame(99, $data->get('preview_file_id'));
    }

    public function testResolvePreviewFileIdIsReadOnlyForStalePointer(): void
    {
        $fallback = $this->fakeFile(9);
        $calls = 0;
        $modx = new class ($fallback, $calls) extends modX {
            public function __construct(
                private msProductFile $fallback,
                private int &$calls,
            ) {
                parent::__construct();
            }

            public function getObject($className, $criteria = null, $cacheFlag = true)
            {
                $this->calls++;
                if ($this->calls === 1) {
                    return null;
                }

                return $this->fallback;
            }

            public function newQuery($className, $criteria = null, $cacheFlag = true)
            {
                return new class {
                    public function where($criteria, $conjunction = null)
                    {
                        return $this;
                    }

                    public function sortby($column, $direction = '')
                    {
                        return $this;
                    }
                };
            }
        };

        $service = new ProductImageService($modx);
        $data = $this->fakeProductData(['id' => 7, 'preview_file_id' => 99]);

        self::assertSame(9, $service->resolvePreviewFileId($data));
        self::assertSame(99, $data->get('preview_file_id'));
    }

    public function testSetProductPreviewRejectsForeignOrMissingFile(): void
    {
        $modx = new class extends modX {
            public function getObject($className, $criteria = null, $cacheFlag = true)
            {
                return null;
            }
        };
        $service = new ProductImageService($modx);
        $data = $this->fakeProductData(['id' => 7, 'preview_file_id' => null]);

        self::assertFalse($service->setProductPreview($data, 123));
    }

    public function testResolvePreviewFileId(): void
    {
        $preview = $this->fakeFile(42);
        $service = new ProductImageService($this->modxReturning($preview));
        $data = $this->fakeProductData(['id' => 7, 'preview_file_id' => 42]);

        self::assertSame(42, $service->resolvePreviewFileId($data));
    }

    private function invokeFind(
        ProductImageService $service,
        msProductData $data,
        bool &$stale = false
    ): ?msProductFile {
        $method = new ReflectionMethod(ProductImageService::class, 'findMainImageFile');
        $method->setAccessible(true);

        return $method->invokeArgs($service, [$data, &$stale]);
    }

    private function modxReturning(msProductFile $file): modX
    {
        return new class ($file) extends modX {
            public function __construct(private msProductFile $file)
            {
                parent::__construct();
            }

            public function getObject($className, $criteria = null, $cacheFlag = true)
            {
                return $this->file;
            }
        };
    }

    private function fakeFile(int $id): msProductFile
    {
        return new class ($id) extends msProductFile {
            public function __construct(private int $fileId)
            {
            }

            public function get($k, $format = null, $formatTemplate = null)
            {
                return $k === 'id' ? $this->fileId : null;
            }
        };
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function fakeProductData(array $fields): msProductData
    {
        return new class ($fields) extends msProductData {
            /** @param array<string, mixed> $fields */
            public function __construct(private array $fields)
            {
            }

            public function get($k, $format = null, $formatTemplate = null)
            {
                return $this->fields[$k] ?? null;
            }

            public function set($k, $v = null, $vType = '')
            {
                $this->fields[$k] = $v;

                return true;
            }
        };
    }
}
