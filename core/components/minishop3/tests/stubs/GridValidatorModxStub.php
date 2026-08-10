<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Stubs;

use MODX\Revolution\modX;

/**
 * Minimal modX for GridColumnTypeValidator unit tests (log + table_prefix).
 */
final class GridValidatorModxStub extends modX
{
    /** @var array<string, mixed> */
    public $config = ['table_prefix' => 'modx_'];

    public function __construct()
    {
    }

    public function log($level, $msg = '', $target = '', $def = '', $file = '', $line = ''): void
    {
    }

    public function getTableName($className, $escape = true): string
    {
        return '';
    }
}
