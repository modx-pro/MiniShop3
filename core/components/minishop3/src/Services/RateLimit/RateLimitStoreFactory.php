<?php

namespace MiniShop3\Services\RateLimit;

use MODX\Revolution\modX;

/**
 * Builds rate limit storage from MODX settings / environment.
 */
final class RateLimitStoreFactory
{
    public static function fromModx(modX $modx): RateLimitStoreInterface
    {
        $windowSeconds = (int) $modx->getOption('ms3_rate_limit_decay_seconds', null, 60);
        $driver = self::resolveDriver($modx);

        return match ($driver) {
            'redis' => self::createRedis($modx, $windowSeconds),
            'memcached' => self::createMemcached($modx, $windowSeconds),
            default => self::createFile($modx, $windowSeconds),
        };
    }

    private static function resolveDriver(modX $modx): string
    {
        $env = getenv('MS3_RATE_LIMIT_STORE');
        $driver = is_string($env) && $env !== ''
            ? $env
            : (string) $modx->getOption('ms3_rate_limit_store', null, 'file');

        return strtolower(trim($driver));
    }

    private static function createFile(modX $modx, int $windowSeconds): FileRateLimitStore
    {
        $path = (string) $modx->getOption('ms3_rate_limit_storage_path', null, '');
        if ($path === '') {
            $path = sys_get_temp_dir();
        }

        return new FileRateLimitStore($path, $windowSeconds);
    }

    private static function createRedis(modX $modx, int $windowSeconds): RateLimitStoreInterface
    {
        if (!class_exists(\Redis::class)) {
            $modx->log(
                modX::LOG_LEVEL_WARN,
                '[RateLimitStoreFactory] ext-redis is not available; falling back to file storage.'
            );

            return self::fallbackToFile($modx, $windowSeconds);
        }

        $dsn = self::resolveRedisDsn($modx);
        $redis = new \Redis();

        if (!self::connectRedis($redis, $dsn, $modx)) {
            return self::fallbackToFile($modx, $windowSeconds);
        }

        return new RedisRateLimitStore($redis, $windowSeconds);
    }

    private static function createMemcached(modX $modx, int $windowSeconds): RateLimitStoreInterface
    {
        if (!class_exists(\Memcached::class)) {
            $modx->log(
                modX::LOG_LEVEL_WARN,
                '[RateLimitStoreFactory] ext-memcached is not available; falling back to file storage.'
            );

            return self::fallbackToFile($modx, $windowSeconds);
        }

        $memcached = new \Memcached();
        $servers = self::resolveMemcachedServers($modx);
        if ($servers === []) {
            $modx->log(
                modX::LOG_LEVEL_WARN,
                '[RateLimitStoreFactory] ms3_rate_limit_memcached_servers is empty; falling back to file storage.'
            );

            return self::fallbackToFile($modx, $windowSeconds);
        }

        if ($memcached->addServers($servers) === false) {
            $modx->log(
                modX::LOG_LEVEL_WARN,
                '[RateLimitStoreFactory] Memcached addServers failed; falling back to file storage.'
            );

            return self::fallbackToFile($modx, $windowSeconds);
        }

        return new MemcachedRateLimitStore($memcached, $windowSeconds);
    }

    /**
     * Build the file-backed store, used as the default and as the fallback when
     * a shared-store driver is unavailable or misconfigured.
     */
    private static function fallbackToFile(modX $modx, int $windowSeconds): FileRateLimitStore
    {
        return self::createFile($modx, $windowSeconds);
    }

    /**
     * @return array<int, array{host: string, port: int, weight: int}>
     */
    private static function resolveMemcachedServers(modX $modx): array
    {
        $env = getenv('MS3_RATE_LIMIT_MEMCACHED_SERVERS');
        $raw = is_string($env) && $env !== ''
            ? $env
            : (string) $modx->getOption('ms3_rate_limit_memcached_servers', null, '127.0.0.1:11211');

        $servers = [];
        foreach (array_filter(array_map('trim', explode(',', $raw))) as $chunk) {
            [$host, $port] = array_pad(explode(':', $chunk, 2), 2, '11211');
            if ($host === '') {
                continue;
            }
            $servers[] = [
                'host' => $host,
                'port' => (int) $port,
                'weight' => 0,
            ];
        }

        return $servers;
    }

    private static function resolveRedisDsn(modX $modx): string
    {
        $env = getenv('MS3_RATE_LIMIT_REDIS_DSN');
        if (is_string($env) && $env !== '') {
            return $env;
        }

        $dsn = trim((string) $modx->getOption('ms3_rate_limit_redis_dsn', null, ''));
        if ($dsn !== '') {
            return $dsn;
        }

        $host = (string) $modx->getOption('ms3_rate_limit_redis_host', null, '127.0.0.1');
        $port = (int) $modx->getOption('ms3_rate_limit_redis_port', null, 6379);
        $password = (string) $modx->getOption('ms3_rate_limit_redis_password', null, '');
        $database = (int) $modx->getOption('ms3_rate_limit_redis_database', null, 0);

        if ($password !== '') {
            return sprintf('redis://:%s@%s:%d/%d', rawurlencode($password), $host, $port, $database);
        }

        return sprintf('redis://%s:%d/%d', $host, $port, $database);
    }

    private static function connectRedis(\Redis $redis, string $dsn, modX $modx): bool
    {
        $parts = parse_url($dsn);
        if ($parts === false || !isset($parts['host'])) {
            $modx->log(
                modX::LOG_LEVEL_WARN,
                '[RateLimitStoreFactory] Invalid Redis DSN; falling back to file storage.'
            );

            return false;
        }

        $host = $parts['host'];
        $port = isset($parts['port']) ? (int) $parts['port'] : 6379;
        $timeout = 1.0;

        try {
            if (!$redis->connect($host, $port, $timeout)) {
                throw new \RuntimeException('connect failed');
            }

            if (isset($parts['pass']) && $parts['pass'] !== '') {
                $redis->auth(rawurldecode((string) $parts['pass']));
            }

            if (isset($parts['path']) && $parts['path'] !== '' && $parts['path'] !== '/') {
                $db = (int) ltrim($parts['path'], '/');
                $redis->select($db);
            }
        } catch (\Throwable $e) {
            $modx->log(
                modX::LOG_LEVEL_WARN,
                '[RateLimitStoreFactory] Redis connection failed: ' . $e->getMessage()
            );

            return false;
        }

        return true;
    }
}
