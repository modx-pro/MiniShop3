<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Cart;

use MiniShop3\Services\Cart\CartDraftContext;
use PHPUnit\Framework\TestCase;

final class CartDraftContextTest extends TestCase
{
    public function testPageContextWhenUnifiedDisabled(): void
    {
        $modx = $this->makeModx('0');
        self::assertSame('en', CartDraftContext::resolve($modx, 'en'));
    }

    public function testWebWhenUnifiedEnabled(): void
    {
        $modx = $this->makeModx('1');
        self::assertSame('web', CartDraftContext::resolve($modx, 'en'));
    }

    public function testEmptyPageFallsBackToWeb(): void
    {
        $modx = $this->makeModx('0');
        self::assertSame('web', CartDraftContext::resolve($modx, ''));
    }

    private function makeModx(string $cartContextOption): object
    {
        return new class ($cartContextOption) {
            public function __construct(private string $cartContextOption)
            {
            }

            public function getOption($key, $options = null, $default = null, $skipEmpty = false)
            {
                if ($key === 'ms3_cart_context') {
                    return $this->cartContextOption;
                }

                return $default;
            }
        };
    }
}
