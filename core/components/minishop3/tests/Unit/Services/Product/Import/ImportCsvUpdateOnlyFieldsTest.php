<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Product\Import;

require_once __DIR__ . '/../../../../stubs/ModxStub.php';

use MiniShop3\MiniShop3;
use MiniShop3\Services\Product\Import\ImportCsvContext;
use MiniShop3\Services\Product\Import\ImportCsvGalleryHandler;
use MiniShop3\Services\Product\Import\ImportCsvOptionHandler;
use MiniShop3\Services\Product\Import\ImportCsvProductUpserter;
use MiniShop3\Services\Product\Import\ImportCsvRowProcessor;
use MiniShop3\Services\Product\Import\ImportCsvRunValidator;
use MODX\Revolution\modResource;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

/**
 * #853: updating by the unique key must not require pagetitle/parent in CSV.
 */
final class ImportCsvUpdateOnlyFieldsTest extends TestCase
{
    public function testValidatorAllowsUpdateWithoutTitleAndParent(): void
    {
        $modx = $this->modx(null);
        $validator = new ImportCsvRunValidator($modx, $this->ms3());

        self::assertNull($validator->validateParams($this->context(null, ['update' => 1])));
        self::assertNotNull($validator->validateParams($this->context(null, ['update' => 0])));
    }

    public function testValidatorRequiresMappedIdentifierColumn(): void
    {
        $validator = new ImportCsvRunValidator($this->modx(null), $this->ms3());

        self::assertNotNull($validator->validateParams($this->context(null, [
            'update' => 1,
            'key' => 'article',
            'keys' => ['price'],
        ])));
    }

    public function testBlankCreationCellsDoNotEraseStoredValues(): void
    {
        $existing = $this->resource(42);
        $modx = $this->modx($existing);
        $ctx = $this->context($modx, [
            'update' => 1,
            'keys' => ['article', 'price', 'pagetitle', 'parent'],
        ]);

        self::assertTrue($this->processor($ctx, $modx)->process(['ART-1', '800', '', '']));
        [$action, $data] = $modx->processorCalls[0];
        self::assertStringEndsWith('\\Update', $action);
        self::assertArrayNotHasKey('pagetitle', $data);
        self::assertArrayNotHasKey('parent', $data);
        self::assertSame('web', $data['context_key']);
    }

    public function testProvidedCreationFieldsAreStillWrittenOnUpdate(): void
    {
        $existing = $this->resource(42);
        $modx = $this->modx($existing);
        $ctx = $this->context($modx, [
            'update' => 1,
            'keys' => ['article', 'price', 'pagetitle', 'parent'],
        ]);

        self::assertTrue($this->processor($ctx, $modx)->process(['ART-1', '800', 'New title', '42']));
        [, $data] = $modx->processorCalls[0];
        self::assertSame('New title', $data['pagetitle']);
        self::assertSame(42, $data['parent']);
    }

    public function testInvalidParentOnUpdateIsRejected(): void
    {
        $modx = $this->modx($this->resource(42));
        $ctx = $this->context($modx, [
            'update' => 1,
            'keys' => ['article', 'price', 'pagetitle', 'parent'],
        ]);

        self::assertFalse($this->processor($ctx, $modx)->process(['ART-1', '800', 'Title', '999']));
        self::assertSame(1, $ctx->errors);
        self::assertSame([], $modx->processorCalls);
    }

    public function testExistingProductIsUpdatedWithoutCreationFields(): void
    {
        $existing = $this->resource(42);
        $modx = $this->modx($existing);
        $ctx = $this->context($modx, ['update' => 1]);
        $processor = $this->processor($ctx, $modx);

        self::assertTrue($processor->process(['ART-1', '800']));
        self::assertSame(0, $ctx->errors);
        self::assertSame(1, $ctx->updated);
        self::assertCount(1, $modx->processorCalls);
        [$action, $data] = $modx->processorCalls[0];
        self::assertStringEndsWith('\\Update', $action);
        self::assertSame(42, $data['id']);
        self::assertSame('800', $data['price']);
        self::assertArrayNotHasKey('pagetitle', $data);
        self::assertArrayNotHasKey('parent', $data);
    }

    public function testUpdateDisabledSkipsExistingProduct(): void
    {
        $modx = $this->modx($this->resource(42));
        $ctx = $this->context($modx, ['update' => 0]);
        $processor = $this->processor($ctx, $modx);

        self::assertTrue($processor->process(['ART-1', '800']));
        self::assertSame([], $modx->processorCalls);
        self::assertSame(1, $ctx->skipped);
    }

    public function testNewProductStillRequiresTitleAndParent(): void
    {
        $modx = $this->modx(null);
        $ctx = $this->context($modx, ['update' => 1]);
        $processor = $this->processor($ctx, $modx);

        self::assertFalse($processor->process(['ART-1', '800']));
        self::assertSame(1, $ctx->errors);
        self::assertSame([], $modx->processorCalls);
    }

    /** @param array<string, mixed> $overrides */
    private function context(?modX $modx = null, array $overrides = []): ImportCsvContext
    {
        return new ImportCsvContext(
            $modx ?? $this->modx(null),
            $this->ms3(),
            array_merge([
                'keys' => ['article', 'price'],
                'key' => 'article',
                'update' => 1,
                'tv_enabled' => false,
            ], $overrides),
            'test'
        );
    }

    private function ms3(): MiniShop3
    {
        return new class extends MiniShop3 {
            public $utils;

            public function __construct()
            {
                $this->utils = new class {
                    public function error(string $message): array
                    {
                        return ['success' => false, 'message' => $message];
                    }
                };
            }
        };
    }

    private function resource(int $id): modResource
    {
        $resource = new class extends modResource {
            public int $id = 0;
            public string $contextKey = 'web';

            public function get($k, $format = null, $formatTemplate = null)
            {
                return $k === 'context_key' ? $this->contextKey : null;
            }
        };
        $resource->id = $id;

        return $resource;
    }

    private function processor(ImportCsvContext $ctx, modX $modx): ImportCsvRowProcessor
    {
        $upserter = new ImportCsvProductUpserter(
            $ctx,
            new ImportCsvOptionHandler($modx),
            new ImportCsvGalleryHandler($modx)
        );

        return new ImportCsvRowProcessor($ctx, $upserter);
    }

    private function modx(?modResource $existing): modX
    {
        return new class ($existing) extends modX {
            public array $processorCalls = [];
            public $error;

            public function __construct(private ?modResource $existing)
            {
                parent::__construct();
                $this->error = new class {
                    public function reset(): void
                    {
                    }
                };
            }

            public function lexicon(string $key, array $params = []): string
            {
                return $key;
            }

            public function log($level, $message): void
            {
            }

            public function invokeEvent($eventName, array $params = [])
            {
                return [];
            }

            public function getFields($className, $criteria = null)
            {
                return ['article' => []];
            }

            public function newQuery($class, $criteria = null)
            {
                return new class {
                    public function setClassAlias($alias): void
                    {
                    }

                    public function where($conditions): void
                    {
                    }

                    public function select($fields): void
                    {
                    }

                    public function innerJoin($class, $alias, $on): void
                    {
                    }

                    public function prepare()
                    {
                        return true;
                    }

                    public function toSql()
                    {
                        return 'SELECT 1';
                    }
                };
            }

            public function getObject($className, $criteria = null, $cacheFlag = true)
            {
                if (is_array($criteria) && isset($criteria['id'])) {
                    return $this->existing && $this->existing->id === (int) $criteria['id']
                        ? $this->existing
                        : null;
                }

                return $this->existing;
            }

            public function runProcessor($action, $params = [])
            {
                $this->processorCalls[] = [$action, $params];

                return new class {
                    public function isError()
                    {
                        return false;
                    }

                    public function getAllErrors()
                    {
                        return [];
                    }

                    public function getObject()
                    {
                        return ['id' => 42];
                    }
                };
            }
        };
    }
}
