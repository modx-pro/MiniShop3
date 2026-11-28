<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Customer;

use MiniShop3\Services\Customer\RateLimiter;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

/**
 * #926: the first atomically-added hit must still respect maxAttempts=1, and
 * repeated hits must not slide the fixed window.
 */
final class RateLimiterMaxAttemptsTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 3) . '/stubs/ModxStub.php';
        }
    }

    public function testFirstHitRespectsSingleAttemptLimit(): void
    {
        $cache = new InMemoryCache();
        $limiter = new RateLimiter($this->modx($cache));

        self::assertFalse($limiter->check('login', '127.0.0.1', 1, 60));
    }

    public function testSecondHitAllowedWithTwoAttemptLimit(): void
    {
        $cache = new InMemoryCache();
        $limiter = new RateLimiter($this->modx($cache));

        self::assertTrue($limiter->check('login', '127.0.0.1', 2, 60));
        self::assertTrue($limiter->check('login', '127.0.0.1', 2, 60));
        self::assertFalse($limiter->check('login', '127.0.0.1', 2, 60));
    }

    public function testResetTimestampDoesNotSlide(): void
    {
        $cache = new InMemoryCache();
        $limiter = new RateLimiter($this->modx($cache));

        $limiter->check('login', '127.0.0.1', 10, 60);
        $key = (new \ReflectionMethod(RateLimiter::class, 'getKey'))->invoke($limiter, 'login', '127.0.0.1');
        $firstReset = $cache->values[$key . '_reset'];

        $limiter->check('login', '127.0.0.1', 10, 60);
        self::assertSame($firstReset, $cache->values[$key . '_reset']);
    }

    private function modx(InMemoryCache $cache): modX
    {
        return new class ($cache) extends modX {
            public InMemoryCache $cacheManager;

            public function __construct(InMemoryCache $cache)
            {
                parent::__construct();
                $this->cacheManager = $cache;
            }
        };
    }
}

final class InMemoryCache
{
    /** @var array<string, mixed> */
    public array $values = [];

    public function add($key, &$var, $lifetime = 0, $options = []): bool
    {
        if (array_key_exists($key, $this->values)) {
            return false;
        }
        $this->values[$key] = $var;

        return true;
    }

    public function get($key, $options = [])
    {
        return $this->values[$key] ?? null;
    }

    public function set($key, &$var, $lifetime = 0, $options = []): bool
    {
        $this->values[$key] = $var;

        return true;
    }
}
