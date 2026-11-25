<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Controllers;

require_once __DIR__ . '/../../stubs/ModxStub.php';

use MiniShop3\Controllers\Api\Web\CartController;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

/**
 * #831: a snippet in return=data mode returns an array. The controller must
 * not put it into data.render, where CartUI assigns it to innerHTML.
 */
final class CartControllerRenderGuardTest extends TestCase
{
    public function testArrayResultIsNotRendered(): void
    {
        $modx = $this->modx(['cost' => 100]);

        self::assertSame([], $this->render($modx));
        self::assertCount(1, $modx->logs);
        self::assertSame(\MODX\Revolution\modX::LOG_LEVEL_DEBUG, $modx->logs[0][0]);
        self::assertStringContainsString('non-string result', $modx->logs[0][1]);
    }

    public function testHtmlResultIsRendered(): void
    {
        $modx = $this->modx('<div class="cart">ok</div>');

        self::assertSame(['token' => '<div class="cart">ok</div>'], $this->render($modx));
        self::assertSame([], $modx->logs);
    }

    public function testEmptyHtmlIsNotRendered(): void
    {
        $modx = $this->modx('');

        self::assertSame([], $this->render($modx));
    }

    public function testTransformResponseOmitsRenderForArraySnippetResult(): void
    {
        $_GET = ['render' => ['token']];
        $_POST = [];
        try {
            $modx = $this->modx(['total' => 100, 'products' => []]);
            $controller = new class ($modx) extends CartController {
                public function transform(array $result): \MiniShop3\Router\Response
                {
                    return $this->transformResponse($result);
                }
            };

            $data = $controller->transform([
                'success' => true,
                'data' => ['total' => 100],
            ])->getData();
        } finally {
            $_GET = [];
        }

        self::assertIsArray($data);
        self::assertArrayNotHasKey('render', $data);
    }

    private function render(modX $modx): array
    {
        $controller = new class ($modx) extends CartController {
            public function renderTokens(array $tokens, string $customerToken = ''): array
            {
                return $this->renderSnippets($tokens, $customerToken);
            }
        };

        return $controller->renderTokens(['token']);
    }

    /** @param mixed $snippetResult */
    private function modx($snippetResult): modX
    {
        return new class ($snippetResult) extends modX {
            /** @var list<array{0: int, 1: string}> */
            public array $logs = [];

            /** @param mixed $snippetResult */
            public function __construct(private $snippetResult)
            {
                parent::__construct();

                $this->lexicon = new class {
                    public function load(string ...$topics): void
                    {
                    }
                };
                $this->services = new class {
                    public function get(string $key): object
                    {
                        if ($key === 'ms3_cart_response_normalizer') {
                            return new class {
                                /** @return array<string, mixed> */
                                public function normalize(array $data, bool $includeThumbs = false): array
                                {
                                    return $data;
                                }
                            };
                        }

                        return new class {
                            /** @return array<string, string> */
                            public function getSnippetData(string $token): array
                            {
                                return ['_snippetName' => 'msCart', 'tpl' => 'tpl.msCart'];
                            }
                        };
                    }
                };
            }

            public function runSnippet($snippetName, $props = [])
            {
                return $this->snippetResult;
            }

            public function log($level, $message): void
            {
                $this->logs[] = [(int) $level, (string) $message];
            }
        };
    }
}
