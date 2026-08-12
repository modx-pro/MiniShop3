<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Api;

use MiniShop3\Services\Api\WebApiContextResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WebApiContextResolverTest extends TestCase
{
    public function testDefaultWhenRequestedNull(): void
    {
        self::assertSame('web', WebApiContextResolver::resolve(null));
    }

    #[DataProvider('invalidKeys')]
    public function testInvalidKeysFallBackToWeb(?string $requested): void
    {
        self::assertSame('web', WebApiContextResolver::resolve($requested));
    }

    /**
     * @return iterable<string, array{0: ?string}>
     */
    public static function invalidKeys(): iterable
    {
        yield 'empty' => [''];
        yield 'whitespace' => ['  '];
        yield 'mgr' => ['mgr'];
        yield 'mgr prefix' => ['mgrCustom'];
        yield 'path injection' => ['../web'];
        yield 'spaces' => ['en us'];
    }

    public function testValidKeyPassesResolve(): void
    {
        self::assertSame('en', WebApiContextResolver::resolve('en'));
    }

    public function testApplySwitchesContext(): void
    {
        $modx = $this->makeModx('web', true);
        $applied = WebApiContextResolver::apply($modx, 'en');

        self::assertSame('en', $applied);
        self::assertSame('en', $modx->context->key);
        self::assertSame(['en'], $modx->switchedTo);
    }

    public function testApplyNoSwitchWhenSameContext(): void
    {
        $modx = $this->makeModx('web', true);
        $applied = WebApiContextResolver::apply($modx, 'web');

        self::assertSame('web', $applied);
        self::assertSame([], $modx->switchedTo);
    }

    public function testApplyKeepsCurrentWhenSwitchFails(): void
    {
        $modx = $this->makeModx('web', false);
        $applied = WebApiContextResolver::apply($modx, 'en');

        self::assertSame('web', $applied);
        self::assertSame('web', $modx->context->key);
        self::assertSame(['en'], $modx->switchedTo);
    }

    /**
     * Minimal modX-shaped double: switchContext / context.key
     */
    private function makeModx(string $currentKey, bool $switchSucceeds): object
    {
        return new class ($currentKey, $switchSucceeds) {
            /** @var list<string> */
            public array $switchedTo = [];

            public object $context;

            public function __construct(
                string $currentKey,
                private bool $switchSucceeds
            ) {
                $this->context = (object) ['key' => $currentKey];
            }

            public function switchContext($contextKey, $reload = false): bool
            {
                $this->switchedTo[] = (string) $contextKey;
                if (!$this->switchSucceeds) {
                    return false;
                }
                $this->context->key = (string) $contextKey;

                return true;
            }
        };
    }
}
