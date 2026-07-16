<?php

namespace MiniShop3\Services\Order;

use MiniShop3\Model\msOrder;
use MODX\Revolution\modX;
use RuntimeException;

/**
 * Atomic order number generation (prefix + separator + counter).
 *
 * Uses MySQL GET_LOCK around allocate+persist to prevent TOCTOU duplicates (#380).
 * Pair with UNIQUE index on ms3_orders.num (empty drafts store NULL).
 */
class OrderNumberGenerator
{
    private const LOCK_TIMEOUT_SECONDS = 10;
    private const MAX_PERSIST_ATTEMPTS = 3;

    public function __construct(private readonly modX $modx)
    {
    }

    /**
     * Allocate next number and run $persist while the lock is held.
     *
     * Retries allocate+persist a few times if $persist throws ms3_err_order_num_save
     * (UNIQUE collision defense-in-depth).
     *
     * @param callable(string):void $persist Must save the number (and related fields) before returning
     * @throws RuntimeException when the named lock cannot be acquired or counter query fails
     */
    public function runWithNextNumber(callable $persist): string
    {
        [$prefix, $separator] = $this->resolveFormat();
        $lockName = $this->lockName($prefix);
        $locked = $this->acquireLock($lockName);

        if (!$locked) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                '[OrderNumberGenerator] Failed to acquire lock: ' . $lockName
            );
            throw new RuntimeException('ms3_err_order_num_lock');
        }

        try {
            $lastError = null;
            for ($attempt = 1; $attempt <= self::MAX_PERSIST_ATTEMPTS; $attempt++) {
                $num = self::buildNumber(
                    $prefix,
                    $separator,
                    $this->fetchMaxCounter($prefix, $separator) + 1
                );
                try {
                    $persist($num);

                    return $num;
                } catch (RuntimeException $e) {
                    $lastError = $e;
                    if ($e->getMessage() !== 'ms3_err_order_num_save') {
                        throw $e;
                    }
                    $this->modx->log(
                        modX::LOG_LEVEL_WARN,
                        '[OrderNumberGenerator] Persist failed for ' . $num
                        . ' (attempt ' . $attempt . '/' . self::MAX_PERSIST_ATTEMPTS . ')'
                    );
                }
            }

            throw $lastError ?? new RuntimeException('ms3_err_order_num_save');
        } finally {
            $this->releaseLock($lockName);
        }
    }

    /**
     * Peek next order number under a named lock without persisting.
     *
     * @deprecated Prefer runWithNextNumber() so allocate and save share one lock.
     * @throws RuntimeException when the named lock cannot be acquired or counter query fails
     */
    public function generate(): string
    {
        return $this->runWithNextNumber(static function (string $num): void {
        });
    }

    /**
     * @return array{0: string, 1: string} [prefix, separator]
     */
    public function resolveFormat(): array
    {
        $format = htmlspecialchars((string)$this->modx->getOption('ms3_order_format_num', null, 'ym'));
        $separator = trim(
            preg_replace(
                "/[^,\/\-]/",
                '',
                (string)$this->modx->getOption('ms3_order_format_num_separator', null, '/')
            ) ?? ''
        );
        $separator = $separator !== '' ? $separator : '/';
        $prefix = $format !== '' ? date($format) : date('ym');

        return [$prefix, $separator];
    }

    /**
     * Parse counter from an existing order number.
     *
     * @internal used by unit tests
     */
    public static function parseCounter(string $num, string $separator): int
    {
        if ($num === '' || !str_contains($num, $separator)) {
            return 0;
        }

        $parts = explode($separator, $num);

        return (int)($parts[1] ?? 0);
    }

    /**
     * @internal used by unit tests
     */
    public static function buildNumber(string $prefix, string $separator, int $count): string
    {
        return sprintf('%s%s%d', $prefix, $separator, $count);
    }

    /**
     * @throws RuntimeException when the counter query fails
     */
    private function fetchMaxCounter(string $prefix, string $separator): int
    {
        $table = $this->modx->getTableName(msOrder::class);
        $like = $prefix . $separator . '%';

        $sql = "SELECT MAX(CAST(SUBSTRING_INDEX(num, ?, -1) AS UNSIGNED))
                FROM {$table}
                WHERE num IS NOT NULL AND num != '' AND num LIKE ?";

        $stmt = $this->modx->prepare($sql);
        if (!$stmt || !$stmt->execute([$separator, $like])) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                '[OrderNumberGenerator] Counter query failed for prefix ' . $prefix
            );
            throw new RuntimeException('ms3_err_order_num_lock');
        }

        $max = $stmt->fetchColumn();

        return $max === false || $max === null || $max === '' ? 0 : (int)$max;
    }

    private function lockName(string $prefix): string
    {
        return 'ms3_ord_num_' . substr(hash('sha256', $prefix), 0, 24);
    }

    private function acquireLock(string $lockName): bool
    {
        $stmt = $this->modx->prepare('SELECT GET_LOCK(?, ?)');
        if (!$stmt || !$stmt->execute([$lockName, self::LOCK_TIMEOUT_SECONDS])) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                '[OrderNumberGenerator] GET_LOCK prepare/execute failed: ' . $lockName
            );

            return false;
        }

        return (int)$stmt->fetchColumn() === 1;
    }

    private function releaseLock(string $lockName): void
    {
        $stmt = $this->modx->prepare('SELECT RELEASE_LOCK(?)');
        if (!$stmt || !$stmt->execute([$lockName])) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                '[OrderNumberGenerator] RELEASE_LOCK failed: ' . $lockName
            );
        }
    }
}
