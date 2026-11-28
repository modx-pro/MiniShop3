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
        $attemptsFile = $this->filePath($key, 'attempts');
        $resetFile = $this->filePath($key, 'reset');

        // Ensure directory exists
        $dir = dirname($attemptsFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $fp = fopen($attemptsFile, 'c+');
        if ($fp === false) {
            // Fallback: non-atomic increment
            return ['attempts' => 1, 'reset_at' => time() + $windowSeconds];
        }

        try {
            // Exclusive lock for atomic read-modify-write
            flock($fp, LOCK_EX);

            // Read current attempts
            $contents = fread($fp, 32);
            $attempts = $contents === '' ? 0 : (int) $contents;
            $attempts++;

            // Reset position and write new value
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, (string) $attempts);
            fflush($fp);

            // Fixed window: set reset time only on the first hit. Re-writing it
            // on every increment makes the window slide and never reset (#926).
            if ($attempts === 1) {
                $resetAt = time() + $windowSeconds;
                file_put_contents($resetFile, (string) $resetAt);
            } else {
                $resetAt = $this->readResetTime($key);
                if ($resetAt <= 0) {
                    $resetAt = time() + $windowSeconds;
                    file_put_contents($resetFile, (string) $resetAt);
                }
            }

            return [
                'attempts' => $attempts,
                'reset_at' => $resetAt,
            ];
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
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
