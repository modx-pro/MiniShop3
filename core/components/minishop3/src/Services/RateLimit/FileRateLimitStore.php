<?php

namespace MiniShop3\Services\RateLimit;

/**
 * File-based rate limit store for single-node deployments (default).
 */
class FileRateLimitStore implements RateLimitStoreInterface
{
    public function __construct(
        private string $storagePath,
        private int $defaultWindowSeconds = 60,
    ) {
        if ($this->storagePath === '') {
            $this->storagePath = sys_get_temp_dir();
        }
    }

    public function read(string $key): array
    {
        return [
            'attempts' => $this->readAttempts($key),
            'reset_at' => $this->readResetTime($key),
        ];
    }

    public function increment(string $key, int $windowSeconds): array
    {
        $attempts = $this->readAttempts($key) + 1;
        $resetAt = $this->readResetTime($key);

        if ($attempts === 1) {
            $resetAt = time() + $windowSeconds;
        }

        $this->writeAttempts($key, $attempts);
        $this->writeResetTime($key, $resetAt);

        return [
            'attempts' => $attempts,
            'reset_at' => $resetAt,
        ];
    }

    public function reset(string $key): void
    {
        @unlink($this->filePath($key, 'attempts'));
        @unlink($this->filePath($key, 'reset'));
    }

    private function readAttempts(string $key): int
    {
        $file = $this->filePath($key, 'attempts');
        if (!is_file($file)) {
            return 0;
        }

        return (int) file_get_contents($file);
    }

    private function readResetTime(string $key): int
    {
        $file = $this->filePath($key, 'reset');
        if (!is_file($file)) {
            return time() + $this->defaultWindowSeconds;
        }

        return (int) file_get_contents($file);
    }

    private function writeAttempts(string $key, int $attempts): void
    {
        file_put_contents($this->filePath($key, 'attempts'), (string) $attempts);
    }

    private function writeResetTime(string $key, int $resetAt): void
    {
        file_put_contents($this->filePath($key, 'reset'), (string) $resetAt);
    }

    private function filePath(string $key, string $type): string
    {
        return rtrim($this->storagePath, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . $key
            . '_'
            . $type
            . '.tmp';
    }
}
