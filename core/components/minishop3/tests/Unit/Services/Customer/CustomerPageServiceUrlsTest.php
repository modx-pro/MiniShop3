<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Customer;

use MiniShop3\Services\Customer\CustomerPageService;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

/**
 * Regression #887 (and the login half of #820): page id 0 means "not configured".
 *
 * The shipped value of ms3_customer_login_page_id / ms3_customer_register_page_id
 * is 0, so getOption()'s fallback never fires and makeUrl(0) returned '' plus a
 * "not a valid integer" error in the log.
 */
final class CustomerPageServiceUrlsTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 3) . '/stubs/ModxStub.php';
        }
    }

    public function testUnconfiguredPagesYieldEmptyUrls(): void
    {
        $modx = $this->modx([
            'ms3_customer_login_page_id' => 0,
            'ms3_customer_register_page_id' => 0,
        ]);

        self::assertSame(
            ['login_url' => '', 'register_url' => ''],
            $this->pageService($modx)->getUnauthorizedUrls(),
            'page id 0 must not reach makeUrl() and must not fall back to page 1'
        );
    }

    public function testMissingSettingsYieldEmptyUrls(): void
    {
        $modx = $this->modx([]);

        self::assertSame(
            ['login_url' => '', 'register_url' => ''],
            $this->pageService($modx)->getUnauthorizedUrls()
        );
    }

    public function testConfiguredPagesBuildUrls(): void
    {
        $modx = $this->modx([
            'ms3_customer_login_page_id' => 5,
            'ms3_customer_register_page_id' => 7,
        ]);

        self::assertSame(
            ['login_url' => '/5', 'register_url' => '/7'],
            $this->pageService($modx)->getUnauthorizedUrls()
        );
    }

    public function testNonPositivePageIdsAreIgnored(): void
    {
        $modx = $this->modx([
            'ms3_customer_login_page_id' => '-3',
            'ms3_customer_register_page_id' => '0',
        ]);

        self::assertSame(
            ['login_url' => '', 'register_url' => ''],
            $this->pageService($modx)->getUnauthorizedUrls()
        );
    }

    public function testLogoutRedirectsToConfiguredLoginPage(): void
    {
        $modx = $this->modx([
            'ms3_customer_login_page_id' => 5,
            'site_start' => 1,
        ]);

        self::assertSame('/5', CustomerPageService::resolveLogoutUrl($modx));
    }

    public function testLogoutFallsBackToSiteStartWithoutLoginPage(): void
    {
        $modx = $this->modx([
            'ms3_customer_login_page_id' => 0,
            'site_start' => 1,
        ]);

        self::assertSame('/1', CustomerPageService::resolveLogoutUrl($modx));
    }

    public function testLogoutUrlIsEmptyWhenNothingIsConfigured(): void
    {
        $modx = $this->modx(['ms3_customer_login_page_id' => 0]);

        self::assertSame('', CustomerPageService::resolveLogoutUrl($modx));
    }

    public function testSnippetUsesResolvedUrlsInsteadOfRawMakeUrl(): void
    {
        $snippet = file_get_contents(dirname(__DIR__, 4) . '/elements/snippets/ms3_customer.php');
        self::assertIsString($snippet);

        self::assertStringContainsString(
            '$pageService->getUnauthorizedUrls()',
            $snippet,
            'return=data branch must reuse the service URLs (#887)'
        );
        self::assertStringContainsString(
            'CustomerPageService::resolveLogoutUrl($modx)',
            $snippet,
            'logout redirect must not call makeUrl(0) (#820)'
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    private function modx(array $options): modX
    {
        return new class ($options) extends modX {
            /** @param array<string, mixed> $options */
            public function __construct(private array $options)
            {
                parent::__construct();
                $this->optionOverrides = $options;
            }

            public function makeUrl($id, $context = '', $args = '', $scheme = -1, array $options = [])
            {
                if ((int) $id <= 0) {
                    throw new \RuntimeException('makeUrl() must not be called with id ' . var_export($id, true));
                }

                return '/' . (int) $id;
            }
        };
    }

    private function pageService(modX $modx): CustomerPageService
    {
        return new class ($modx) extends CustomerPageService {
            public function __construct(modX $modx)
            {
                $this->modx = $modx;
            }

            public function render(): string
            {
                return '';
            }

            public function getData(): array
            {
                return [];
            }
        };
    }
}
