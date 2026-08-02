<?php

declare(strict_types=1);

namespace MiniShop3\Services\Product\Import;

use MiniShop3\MiniShop3;
use MODX\Revolution\modX;

/**
 * Mutable import run state shared between CSV import collaborators.
 */
final class ImportCsvContext
{
    /** @var array<string, int> */
    public array $tvCache = [];

    public function __construct(
        public modX $modx,
        public MiniShop3 $ms3,
        public array $params,
        public string $importId,
        public int $rows = 0,
        public int $created = 0,
        public int $updated = 0,
        public int $errors = 0,
        public int $skipped = 0,
        public ?string $detectedEncoding = null,
    ) {
    }
}
