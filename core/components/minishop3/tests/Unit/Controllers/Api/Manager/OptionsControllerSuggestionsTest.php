<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Controllers\Api\Manager;

use MiniShop3\Controllers\Api\Manager\OptionsController;
use MiniShop3\Model\msProductOption;
use MiniShop3\Router\HttpStatus;
use MiniShop3\Services\Option\OptionService;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

/**
 * #745: getSuggestions must use distinct()+escape(value), not select('DISTINCT value').
 */
final class OptionsControllerSuggestionsTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 4) . '/stubs/ModxStub.php';
        }
    }

    public function testMissingKeyReturnsBadRequest(): void
    {
        $controller = $this->makeController(new SuggestionsQueryProbe(['a']));
        $out = $controller->getSuggestions([]);

        self::assertFalse($out['success']);
        self::assertSame(HttpStatus::BAD_REQUEST, $out['code']);
    }

    public function testReturnsSavedValuesAndBuildsDistinctSelect(): void
    {
        $probe = new SuggestionsQueryProbe(['123', '232']);
        $controller = $this->makeController($probe);

        $out = $controller->getSuggestions([
            'key' => 'test_option_7',
            'limit' => 100,
        ]);

        self::assertTrue($out['success']);
        self::assertSame(['123', '232'], $out['data']['results']);
        self::assertSame(2, $out['data']['total']);
        self::assertTrue($probe->distinctCalled);
        self::assertSame('`value`', $probe->selectColumns);
        self::assertStringNotContainsString('DISTINCT', (string) $probe->selectColumns);
        self::assertSame(msProductOption::class, $probe->queriedClass);
    }

    public function testQueryFilterIsApplied(): void
    {
        $probe = new SuggestionsQueryProbe(['red', 'green']);
        $controller = $this->makeController($probe);

        $out = $controller->getSuggestions([
            'key' => 'color',
            'query' => 're',
            'limit' => 50,
        ]);

        self::assertTrue($out['success']);
        self::assertSame(['red', 'green'], $out['data']['results']);
        self::assertContains(['value:LIKE' => '%re%'], $probe->wheres);
    }

    public function testExecuteFailureIsLoggedAndReturnsEmptyResults(): void
    {
        $probe = new SuggestionsQueryProbe([], executeOk: false);
        $modx = $this->makeModx($probe);
        $controller = new OptionsController($modx);

        $out = $controller->getSuggestions(['key' => 'broken']);

        self::assertTrue($out['success']);
        self::assertSame([], $out['data']['results']);
        self::assertSame(0, $out['data']['total']);
        self::assertNotEmpty($modx->errorLogs);
        self::assertStringContainsString('getSuggestions query failed', $modx->errorLogs[0]);
        self::assertStringContainsString('42S22', $modx->errorLogs[0]);
    }

    private function makeController(SuggestionsQueryProbe $probe): OptionsController
    {
        return new OptionsController($this->makeModx($probe));
    }

    private function makeModx(SuggestionsQueryProbe $probe): modX
    {
        $optionService = $this->createMock(OptionService::class);

        return new class ($probe, $optionService) extends modX {
            /** @var list<string> */
            public array $errorLogs = [];

            public function __construct(
                private SuggestionsQueryProbe $probe,
                OptionService $optionService,
            ) {
                parent::__construct();
                $this->services = new class ($optionService) {
                    public function __construct(private OptionService $optionService)
                    {
                    }

                    public function get(string $key): object
                    {
                        return $this->optionService;
                    }

                    public function has(string $key): bool
                    {
                        return true;
                    }
                };
            }

            public function escape($str, $escape = true): string
            {
                return '`' . str_replace('`', '``', (string) $str) . '`';
            }

            public function newQuery($className, $criteria = null, $cacheFlag = true)
            {
                $this->probe->queriedClass = (string) $className;

                return $this->probe;
            }

            public function log($level, $message): void
            {
                if ((int) $level === self::LOG_LEVEL_ERROR) {
                    $this->errorLogs[] = (string) $message;
                }
            }
        };
    }
}

/**
 * Captures select/distinct/where and fakes PDO stmt for getSuggestions.
 */
final class SuggestionsQueryProbe
{
    public mixed $stmt = null;

    public bool $distinctCalled = false;

    public mixed $selectColumns = null;

    public string $queriedClass = '';

    /** @var list<array<string, mixed>> */
    public array $wheres = [];

    /** @param list<string> $rows */
    public function __construct(
        private array $rows,
        private bool $executeOk = true,
    ) {
    }

    public function where($criteria, $conjunction = null): self
    {
        if (is_array($criteria)) {
            $this->wheres[] = $criteria;
        }

        return $this;
    }

    public function select($columns): self
    {
        $this->selectColumns = $columns;

        return $this;
    }

    public function distinct($on = true): self
    {
        $this->distinctCalled = (bool) $on;

        return $this;
    }

    public function sortby($sort, $dir = 'ASC'): self
    {
        return $this;
    }

    public function limit($limit, $offset = 0): self
    {
        return $this;
    }

    public function prepare(): bool
    {
        $rows = $this->rows;
        $ok = $this->executeOk;
        $this->stmt = new class ($rows, $ok) {
            public function __construct(
                private array $rows,
                private bool $ok,
            ) {
            }

            public function execute(): bool
            {
                return $this->ok;
            }

            public function fetchAll($mode = null): array
            {
                return $this->rows;
            }

            public function errorInfo(): array
            {
                return ['42S22', 1054, "Unknown column 'DISTINCT value' in 'SELECT'"];
            }
        };

        return true;
    }
}
