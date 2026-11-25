<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Customer;

use MiniShop3\Services\Customer\CustomerPageService;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

/**
 * Regression #820: the shipped page id 0 must not disable the fallback and
 * must never reach makeUrl(0).
 */
final class CustomerPageServiceFallbackTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 3) . '/stubs/ModxStub.php';
        }
    }

    public function testZeroLoginPageFallsBackToSiteStartAndWarns(): void
    {
        $logs = $this->logCollector();
        $modx = $this->modx([
            'ms3_customer_login_page_id' => 0,
            'site_start' => 1,
        ], $logs);

        self::assertSame(
            '/1',
            CustomerPageService::resolvePageUrl($modx, 'ms3_customer_login_page_id')
        );
        self::assertCount(1, $logs->items);
        self::assertSame(modX::LOG_LEVEL_WARN, $logs->items[0]['level']);
        self::assertStringContainsString('ms3_customer_login_page_id', $logs->items[0]['message']);
    }

    public function testConfiguredPageIsUsedWithoutWarning(): void
    {
        $logs = $this->logCollector();
        $modx = $this->modx([
            'ms3_customer_login_page_id' => 5,
            'site_start' => 1,
        ], $logs);

        self::assertSame('/5', CustomerPageService::resolvePageUrl($modx, 'ms3_customer_login_page_id'));
        self::assertSame([], $logs->items);
    }

    public function testConfiguredPageWithEmptyUrlFallsBackAndWarns(): void
    {
        $logs = $this->logCollector();
        $modx = $this->modx([
            'ms3_customer_login_page_id' => 5,
            'site_start' => 1,
        ], $logs, [5]);

        self::assertSame('/1', CustomerPageService::resolvePageUrl($modx, 'ms3_customer_login_page_id'));
        self::assertCount(1, $logs->items);
        self::assertSame(modX::LOG_LEVEL_WARN, $logs->items[0]['level']);
    }

    public function testWarningIsEmittedOncePerReason(): void
    {
        $logs = $this->logCollector();
        $modx = $this->modx([
            'ms3_customer_login_page_id' => 0,
            'site_start' => 42,
        ], $logs);

        CustomerPageService::resolvePageUrl($modx, 'ms3_customer_login_page_id');
        CustomerPageService::resolvePageUrl($modx, 'ms3_customer_login_page_id');

        self::assertCount(1, $logs->items);
    }

    public function testMissingSettingUsesFallback(): void
    {
        $logs = $this->logCollector();
        $modx = $this->modx(['site_start' => 9], $logs);

        self::assertSame('/9', CustomerPageService::resolvePageUrl($modx, 'ms3_customer_login_page_id'));
        self::assertCount(1, $logs->items);
    }

    public function testNegativePageIdUsesFallback(): void
    {
        $logs = $this->logCollector();
        $modx = $this->modx([
            'ms3_customer_login_page_id' => '-3',
            'site_start' => 2,
        ], $logs);

        self::assertSame('/2', CustomerPageService::resolvePageUrl($modx, 'ms3_customer_login_page_id'));
        self::assertCount(1, $logs->items);
    }

    public function testUnauthorizedUrlsUseTheSameResolver(): void
    {
        $logs = $this->logCollector();
        $modx = $this->modx([
            'ms3_customer_login_page_id' => 5,
            'ms3_customer_register_page_id' => 0,
            'site_start' => 1,
        ], $logs);

        self::assertSame(
            ['login_url' => '/5', 'register_url' => '/1'],
            CustomerPageService::getUnauthorizedUrls($modx)
        );
    }

    public function testEmptySiteStartDoesNotCallMakeUrlWithZero(): void
    {
        $logs = $this->logCollector();
        $modx = $this->modx([
            'ms3_customer_login_page_id' => 0,
            'site_start' => 0,
        ], $logs);

        self::assertSame('', CustomerPageService::resolvePageUrl($modx, 'ms3_customer_login_page_id'));
        self::assertCount(1, $logs->items);
    }

    public function testSnippetDelegatesPageUrlsToTheService(): void
    {
        $snippet = file_get_contents(dirname(__DIR__, 4) . '/elements/snippets/ms3_customer.php');
        self::assertIsString($snippet);

        self::assertStringContainsString('CustomerPageService::resolvePageUrl', $snippet);
        self::assertStringContainsString('CustomerPageService::getUnauthorizedUrls', $snippet);
        self::assertStringNotContainsString(
            "getOption('ms3_customer_login_page_id', null, 1)",
            $snippet
        );
    }

    /**
     * @return object{items: list<array{level: int, message: string}>}
     */
    private function logCollector(): object
    {
        return new class {
            /** @var list<array{level: int, message: string}> */
            public array $items = [];
        };
    }

    /**
     * @param array<string, mixed> $options
     * @param object{items: list<array{level: int, message: string}>} $logs
     * @param list<int> $emptyIds Page ids for which makeUrl() returns an empty string.
     */
    private function modx(array $options, object $logs, array $emptyIds = []): modX
    {
        return new class ($options, $logs, $emptyIds) extends modX {
            /**
             * @param array<string, mixed> $options
             * @param object{items: list<array{level: int, message: string}>} $logs
             * @param list<int> $emptyIds
             */
            public function __construct(
                private array $options,
                private object $logs,
                private array $emptyIds,
            ) {
                parent::__construct();
                $this->optionOverrides = $options;
            }

            public function makeUrl($id, $context = null, $args = [], $scheme = -1, array $options = [])
            {
                if ((int)$id <= 0) {
                    throw new \LogicException('makeUrl() must not be called with a non-positive id');
                }

                return in_array((int)$id, $this->emptyIds, true) ? '' : '/' . (int)$id;
            }

            public function log($level, $message): void
            {
                $this->logs->items[] = ['level' => (int)$level, 'message' => (string)$message];
            }
        };
    }
}
