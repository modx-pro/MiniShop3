<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Grid;

use MiniShop3\Services\Grid\GridRelationFieldExtractor;
use PHPUnit\Framework\TestCase;

final class GridRelationFieldExtractorTest extends TestCase
{
    public function testGroupsByTableAndForeignKey(): void
    {
        $extractor = new GridRelationFieldExtractor();
        $groups = $extractor->extract([
            [
                'name' => 'status_name',
                'type' => 'relation',
                'relation' => [
                    'table' => 'msOrderStatus',
                    'foreignKey' => 'status_id',
                    'displayField' => 'name',
                ],
            ],
            [
                'name' => 'status_color',
                'type' => 'relation',
                'relation' => [
                    'table' => 'msOrderStatus',
                    'foreignKey' => 'status_id',
                    'displayField' => 'color',
                ],
            ],
            [
                'name' => 'pagetitle',
                'type' => 'model',
            ],
        ]);

        self::assertCount(1, $groups);
        $group = $groups['msOrderStatus_status_id'];
        self::assertSame('MiniShop3\\Model\\msOrderStatus', $group['modelClass']);
        self::assertCount(2, $group['fields']);
        self::assertSame('rel_msOrderStatus_status_id', $group['alias']);
    }

    public function testSkipsIncompleteRelation(): void
    {
        $extractor = new GridRelationFieldExtractor();
        $groups = $extractor->extract([
            [
                'name' => 'broken',
                'type' => 'relation',
                'relation' => ['table' => 'msVendor'],
            ],
        ]);

        self::assertSame([], $groups);
    }
}
