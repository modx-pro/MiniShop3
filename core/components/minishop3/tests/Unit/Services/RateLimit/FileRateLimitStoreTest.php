<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\RateLimit;

use MiniShop3\Services\RateLimit\FileRateLimitStore;
use PHPUnit\Framework\TestCase;

final class FileRateLimitStoreTest extends TestCase
{
    private string $storagePath;

    protected function setUp(): void
    {
        $this->storagePath = sys_get_temp_dir() . '/ms3_rl_test_' . bin2hex(random_bytes(8));
        mkdir($this->storagePath, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->storagePath . '/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        if (is_dir($this->storagePath)) {
            rmdir($this->storagePath);
        }
    }

    public function testReadReturnsZeroAttemptsForUnknownKey(): void
    {
        $store = new FileRateLimitStore($this->storagePath, 60);
        $state = $store->read('client-a');

        self::assertSame(0, $state['attempts']);
        self::assertGreaterThan(time(), $state['reset_at']);
    }

    public function testIncrementPersistsAttemptsAndResetTime(): void
    {
        $store = new FileRateLimitStore($this->storagePath, 60);

        $first = $store->increment('client-a', 60);
        $second = $store->increment('client-a', 60);

        self::assertSame(1, $first['attempts']);
        self::assertSame(2, $second['attempts']);
        self::assertSame($first['reset_at'], $second['reset_at']);

        $read = $store->read('client-a');
        self::assertSame(2, $read['attempts']);
        self::assertSame($first['reset_at'], $read['reset_at']);
    }

    public function testResetClearsCounter(): void
    {
        $store = new FileRateLimitStore($this->storagePath, 60);
        $store->increment('client-a', 60);
        $store->reset('client-a');

        $state = $store->read('client-a');
        self::assertSame(0, $state['attempts']);
    }
}
