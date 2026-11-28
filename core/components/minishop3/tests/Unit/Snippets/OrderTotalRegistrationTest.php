<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Snippets;

require_once __DIR__ . '/../../stubs/ModxStub.php';

use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

/**
 * #831 behavioral regression: msOrderTotal registers itself for cart
 * re-rendering only when it actually produces HTML, not for return=data.
 */
final class OrderTotalRegistrationTest extends TestCase
{
    public function testDataReturnDoesNotRegisterWidget(): void
    {
        [$result, $ms3] = $this->runSnippet('data');

        self::assertIsArray($result);
        self::assertSame(0, $ms3->registrations);
    }

    public function testTplReturnRegistersWidget(): void
    {
        [$result, $ms3] = $this->runSnippet('tpl');

        self::assertSame('<html>', $result);
        self::assertSame(1, $ms3->registrations);
        self::assertSame('msOrderTotal', $ms3->registeredName);
    }

    public function testClearedReturnStillRegistersBecauseSnippetRendersHtml(): void
    {
        [$result, $ms3] = $this->runSnippet('');

        self::assertSame('<html>', $result);
        self::assertSame(1, $ms3->registrations);
    }

    /** @return array{0: mixed, 1: object} */
    private function runSnippet(string $returnValue): array
    {
        $ms3 = $this->fakeMs3();
        $modx = $this->modx($ms3, $returnValue);
        $path = dirname(__DIR__, 3) . '/elements/snippets/ms3_order_total.php';

        $result = (static function (string $path, modX $modx, object $ms3) {
            $scriptProperties = ['return' => $modx->getOption('return')];

            return include $path;
        })($path, $modx, $ms3);

        return [$result, $ms3];
    }

    private function fakeMs3(): object
    {
        return new class {
            public int $registrations = 0;
            public ?string $registeredName = null;

            public object $order;
            public object $format;

            public function __construct()
            {
                $this->order = new class {
                    public function initialize(string $token): void
                    {
                    }

                    /** @return array{success: bool} */
                    public function getCost(): array
                    {
                        return ['success' => false];
                    }
                };
                $this->format = new class {
                    public function price($value, bool $format = false): string
                    {
                        return (string) $value;
                    }

                    public function weightWithUnit($value): string
                    {
                        return (string) $value;
                    }
                };
            }

            public function initialize(string $context): void
            {
            }

            public function registerSnippet(array $props, string $name = 'msCart'): void
            {
                $this->registrations++;
                $this->registeredName = $name;
            }
        };
    }

    private function modx(object $ms3, string $returnValue): modX
    {
        $tokenService = new class {
            public function resolveOrCreateToken(): string
            {
                return 'token';
            }
        };
        $pdoFetch = new class {
            public function addTime(string $label): void
            {
            }

            public function getChunk(string $tpl, array $data): string
            {
                return '<html>';
            }
        };
        $services = new class ($ms3, $tokenService, $pdoFetch) {
            public function __construct(
                private object $ms3,
                private object $tokenService,
                private object $pdoFetch,
            ) {
            }

            public function has(string $key): bool
            {
                return $key === 'ms3';
            }

            public function get(string $key): object
            {
                return match ($key) {
                    'ms3' => $this->ms3,
                    'ms3_token_service' => $this->tokenService,
                    default => $this->pdoFetch,
                };
            }
        };

        $modx = new class ($services, $returnValue) extends modX {
            public function __construct(object $services, string $returnValue)
            {
                parent::__construct();
                $this->services = $services;
                $this->context = (object) ['key' => 'web'];
                $this->optionOverrides = ['return' => $returnValue];
            }

            public function getOption(string $key, $options = null, $default = null, $skipEmpty = false)
            {
                $value = parent::getOption($key, $options, $default);

                return $skipEmpty && ($value === '' || $value === null) ? $default : $value;
            }
        };

        return $modx;
    }
}
