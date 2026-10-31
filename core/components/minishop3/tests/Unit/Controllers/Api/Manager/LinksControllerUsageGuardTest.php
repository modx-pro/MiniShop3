<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Controllers\Api\Manager;

use MiniShop3\Controllers\Api\Manager\LinksController;
use MiniShop3\Model\msProductLink;
use MiniShop3\Router\HttpStatus;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

/**
 * Regression #948: защита от удаления используемого типа связи считала по
 * несуществующей колонке `link_id`. xPDO молча отбрасывает неизвестный ключ,
 * поэтому getCount() всегда возвращал 0, а композит msLink удалял все связи
 * товаров этого типа.
 */
final class LinksControllerUsageGuardTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 4) . '/stubs/ModxStub.php';
        }
    }

    public function testDeleteRefusesLinkUsedByProducts(): void
    {
        $link = $this->fakeLink();
        $modx = $this->modxWithLink($link, 2);

        $data = (new LinksController($modx))->delete(['id' => 7]);

        self::assertSame([[msProductLink::class, ['link' => 7]]], $modx->countCriteria);
        self::assertFalse($data['success'] ?? true);
        self::assertSame(HttpStatus::BAD_REQUEST, $data['code'] ?? null);
        self::assertStringContainsString('2 product connections', (string) ($data['message'] ?? ''));
        self::assertFalse($link->removed, 'Used link type must not be deleted');
    }

    public function testDeleteAllowsUnusedLink(): void
    {
        $link = $this->fakeLink();
        $modx = $this->modxWithLink($link, 0);

        $data = (new LinksController($modx))->delete(['id' => 7]);

        self::assertSame([[msProductLink::class, ['link' => 7]]], $modx->countCriteria);
        self::assertTrue($data['success'] ?? false);
        self::assertTrue($link->removed);
    }

    public function testBulkDeleteSkipsLinkUsedByProducts(): void
    {
        $link = $this->fakeLink();
        $modx = $this->modxWithLink($link, 3);

        $data = (new LinksController($modx))->bulkDelete(['ids' => [7]]);

        self::assertSame([[msProductLink::class, ['link' => 7]]], $modx->countCriteria);
        self::assertFalse($data['success'] ?? true);
        self::assertSame(HttpStatus::BAD_REQUEST, $data['code'] ?? null);
        self::assertStringContainsString('1 links are in use', (string) ($data['message'] ?? ''));
        self::assertFalse($link->removed, 'Used link type must not be deleted by bulk');
    }

    public function testBulkDeleteDeletesUnusedLink(): void
    {
        $link = $this->fakeLink();
        $modx = $this->modxWithLink($link, 0);

        $data = (new LinksController($modx))->bulkDelete(['ids' => [7]]);

        self::assertSame([[msProductLink::class, ['link' => 7]]], $modx->countCriteria);
        self::assertTrue($data['success'] ?? false);
        self::assertSame(1, $data['data']['deleted'] ?? null);
        self::assertSame(0, $data['data']['in_use'] ?? null);
        self::assertTrue($link->removed);
    }

    /**
     * modX-заглушка: фиксирует критерий getCount() и отдаёт фейковый тип связи.
     */
    private function modxWithLink(object $link, int $usageCount): modX
    {
        return new class ($link, $usageCount) extends modX {
            /** @var list<array{0: string, 1: mixed}> */
            public array $countCriteria = [];

            private object $link;
            private int $usageCount;

            public function __construct(object $link, int $usageCount)
            {
                parent::__construct();
                $this->link = $link;
                $this->usageCount = $usageCount;
            }

            public function getObject($className, $criteria = null, $cacheFlag = true)
            {
                return $this->link;
            }

            public function getCount($className, $criteria = null)
            {
                $this->countCriteria[] = [(string) $className, $criteria];

                return $this->usageCount;
            }
        };
    }

    private function fakeLink(): object
    {
        return new class {
            public bool $removed = false;

            public function remove(): bool
            {
                $this->removed = true;

                return true;
            }
        };
    }
}
