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
        $modx = $this->makeModx('web', ['en' => true]);
        $applied = WebApiContextResolver::apply($modx, 'en');

        self::assertSame('en', $applied);
        self::assertSame('en', $modx->context->key);
        self::assertSame(['en'], $modx->switchedTo);
    }

    public function testApplyNoSwitchWhenSameContext(): void
    {
        $modx = $this->makeModx('web', ['en' => true]);
        $applied = WebApiContextResolver::apply($modx, 'web');

        self::assertSame('web', $applied);
        self::assertSame([], $modx->switchedTo);
    }

    public function testApplySkipsUnknownContextWithoutSwitch(): void
    {
        $modx = $this->makeModx('web', []);
        $applied = WebApiContextResolver::apply($modx, 'zzz_nonexistent_ctx');

        self::assertSame('web', $applied);
        self::assertNotNull($modx->context);
        self::assertSame('web', $modx->context->key);
        self::assertSame([], $modx->switchedTo);
    }

    /**
     * MODX nulls $modx->context when switchContext fails after prepare() —
     * ensure we restore a live context (policy denial path).
     */
    public function testApplyRestoresContextWhenSwitchFailsAndNulls(): void
    {
        $modx = $this->makeModx('web', ['en' => true], nullOnFail: true);
        $modx->forceSwitchFail = true;

        $applied = WebApiContextResolver::apply($modx, 'en');

        self::assertSame('web', $applied);
        self::assertNotNull($modx->context);
        self::assertSame('web', $modx->context->key);
        self::assertContains('en', $modx->switchedTo);
        self::assertContains('web', $modx->switchedTo);
    }

    public function testLiveContextKeyFallsBackWhenContextNull(): void
    {
        $modx = (object) ['context' => null];
        self::assertSame('web', WebApiContextResolver::liveContextKey($modx));
    }

    /**
     * Minimal modX-shaped double matching real MODX failure modes:
     * - getContext() null for unknown keys (no side effects)
     * - failed switchContext can null $context (like _initContext)
     *
     * @param array<string, bool> $knownContexts key => exists
     */
    private function makeModx(string $currentKey, array $knownContexts, bool $nullOnFail = false): object
    {
        return new class ($currentKey, $knownContexts, $nullOnFail) {
            /** @var list<string> */
            public array $switchedTo = [];

            public ?object $context;

            public bool $forceSwitchFail = false;

            /**
             * @param array<string, bool> $knownContexts
             */
            public function __construct(
                string $currentKey,
                private array $knownContexts,
                private bool $nullOnFail
            ) {
                $this->context = (object) ['key' => $currentKey];
            }

            public function getContext($contextKey): ?object
            {
                $key = (string) $contextKey;
                if ($key === 'web' || !empty($this->knownContexts[$key])) {
                    return (object) ['key' => $key];
                }

                return null;
            }

            public function switchContext($contextKey, $reload = false): bool
            {
                $key = (string) $contextKey;
                $this->switchedTo[] = $key;

                if ($this->forceSwitchFail && $key !== 'web') {
                    if ($this->nullOnFail) {
                        $this->context = null;
                    }

                    return false;
                }

                if ($key !== 'web' && empty($this->knownContexts[$key])) {
                    if ($this->nullOnFail) {
                        $this->context = null;
                    }

                    return false;
                }

                $this->context = (object) ['key' => $key];

                return true;
            }
        };
    }
}
