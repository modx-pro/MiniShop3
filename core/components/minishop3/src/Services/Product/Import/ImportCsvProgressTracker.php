<?php

declare(strict_types=1);

namespace MiniShop3\Services\Product\Import;

use MODX\Revolution\modX;
use xPDO\xPDO;

final class ImportCsvProgressTracker
{
    public static function save(ImportCsvContext $ctx, int $current, int $total, bool $completed = false): void
    {
        $progress = [
            'import_id' => $ctx->importId,
            'current' => $current,
            'total' => $total,
            'created' => $ctx->created,
            'updated' => $ctx->updated,
            'errors' => $ctx->errors,
            'skipped' => $ctx->skipped,
            'completed' => $completed,
            'percent' => $total > 0 ? round(($current / $total) * 100) : 0,
            'timestamp' => time(),
        ];

        $cacheKey = 'import_progress_' . $ctx->importId;
        $ctx->modx->cacheManager->set($cacheKey, $progress, 3600, [
            xPDO::OPT_CACHE_KEY => 'minishop3',
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function getProgress(modX $modx, string $importId): ?array
    {
        $cacheKey = 'import_progress_' . $importId;

        return $modx->cacheManager->get($cacheKey, [
            xPDO::OPT_CACHE_KEY => 'minishop3',
        ]);
    }
}
