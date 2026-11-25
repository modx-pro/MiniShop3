<?php

namespace MiniShop3\Services\Customer;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msCustomer;
use MiniShop3\Services\TokenService;
use MiniShop3\Utils\CookieHelper;
use MiniShop3\Utils\SessionHelper;
use MODX\Revolution\modX;
use ModxPro\PdoTools\Fetch;

/**
 * CustomerPageService - base class for customer account pages
 *
 * Provides common logic for all account pages:
 * - Authentication check
 * - Customer data loading
 * - Rendering via Fenom
 *
 * Used as base class for:
 * - ProfilePageService (customer profile)
 * - AddressesPageService (address management)
 * - OrdersPageService (order history)
 *
 * @package MiniShop3\Services\Customer
 */
abstract class CustomerPageService
{
    /** @var modX */
    protected modX $modx;

    /** @var MiniShop3 */
    protected MiniShop3 $ms3;

    /** @var Fetch */
    protected Fetch $pdoFetch;

    /** @var int|null ID of authenticated customer */
    protected ?int $customerId = null;

    /** @var msCustomer|null Authenticated customer object */
    protected ?msCustomer $customer = null;

    /** @var array Snippet parameters */
    protected array $scriptProperties = [];

    /**
     * @param modX $modx
     * @param MiniShop3 $ms3
     * @param array $scriptProperties Parameters from snippet
     */
    public function __construct(modX $modx, MiniShop3 $ms3, array $scriptProperties = [])
    {
        $this->modx = $modx;
        $this->ms3 = $ms3;
        $this->scriptProperties = $scriptProperties;

        $this->pdoFetch = $this->modx->services->get(Fetch::class);

        $this->modx->lexicon->load('minishop3:customer');
        $this->modx->lexicon->load('minishop3:default');
    }

    /**
     * Check customer authentication
     *
     * @return bool true if customer is authenticated
     */
    public function checkAuth(): bool
    {
        /** @var TokenService|null $tokenService */
        $tokenService = $this->modx->services->has('ms3_token_service')
            ? $this->modx->services->get('ms3_token_service')
            : null;

        if ($tokenService) {
            $tokenService->ensureSessionActive();
            $tokenService->restoreSessionFromCookie();
        } else {
            SessionHelper::ensureActive();
        }

        if (empty($_SESSION['ms3']['customer_id'])) {
            $this->modx->log(
                modX::LOG_LEVEL_DEBUG,
                "[CustomerPageService] Customer not authenticated (no session)"
            );
            return false;
        }

        $this->customerId = (int)$_SESSION['ms3']['customer_id'];

        if (!$tokenService || !$tokenService->sessionTokenBelongsToCustomer($this->customerId)) {
            $this->modx->log(
                modX::LOG_LEVEL_WARN,
                "[CustomerPageService] Session customer #{$this->customerId} does not match API token"
            );

            return $this->rejectAuth();
        }

        $this->customer = $this->modx->getObject(msCustomer::class, $this->customerId);

        if (!$this->customer) {
            $this->modx->log(
                modX::LOG_LEVEL_WARN,
                "[CustomerPageService] Customer #{$this->customerId} not found in database"
            );

            return $this->rejectAuth();
        }

        if (CustomerAccess::isAccessDenied($this->customer)) {
            return $this->rejectAuth();
        }

        return true;
    }

    private function rejectAuth(): bool
    {
        $this->clearAuthSession();
        $this->customerId = null;
        $this->customer = null;

        return false;
    }

    private function clearAuthSession(): void
    {
        if (isset($_SESSION['ms3'])) {
            unset(
                $_SESSION['ms3']['customer_id'],
                $_SESSION['ms3']['customer_token'],
                $_SESSION['ms3']['customer_token_expires']
            );
        }

        CookieHelper::clearTokenCookie($this->modx);
    }

    /**
     * Get authenticated customer ID
     *
     * @return int|null
     */
    public function getCustomerId(): ?int
    {
        return $this->customerId;
    }

    /**
     * Get authenticated customer object
     *
     * @return msCustomer|null
     */
    public function getCustomer(): ?msCustomer
    {
        return $this->customer;
    }

    /**
     * Get safe public customer data filtered by CustomerPublicDto (#840)
     *
     * @return array
     */
    public function getPublicCustomerData(): array
    {
        if (!$this->customer) {
            return [];
        }

        return CustomerPublicDto::fromCustomer($this->customer, $this->modx, $this->ms3);
    }

    /**
     * Render page for unauthenticated user
     *
     * @return string HTML content
     */
    public function renderUnauthorized(): string
    {
        $tpl = $this->modx->getOption(
            'unauthorizedTpl',
            $this->scriptProperties,
            'tpl.msCustomer.unauthorized'
        );

        $chunk = $this->pdoFetch->getChunk($tpl, self::getUnauthorizedUrls($this->modx));

        return is_string($chunk) ? $chunk : '';
    }

    /**
     * URLs of the login and registration pages for the unauthorized chunk (#820).
     *
     * @return array{login_url: string, register_url: string}
     */
    public static function getUnauthorizedUrls(modX $modx): array
    {
        return [
            'login_url' => self::resolvePageUrl($modx, 'ms3_customer_login_page_id'),
            'register_url' => self::resolvePageUrl($modx, 'ms3_customer_register_page_id'),
        ];
    }

    /**
     * Resolve a customer page id into a URL, with a usable fallback for 0 (#820).
     *
     * The shipped settings hold 0, so getOption()'s default is never used: the
     * option is present and "0" is a valid value for it. Passing 0 to makeUrl()
     * returns an empty string and writes "not a valid integer" to the log.
     * A non-positive id therefore means "page not selected" — fall back to the
     * site start page (1 in a default install) and warn the administrator.
     */
    public static function resolvePageUrl(modX $modx, string $settingKey): string
    {
        $pageId = (int)$modx->getOption($settingKey, null, 0);
        if ($pageId > 0) {
            $url = $modx->makeUrl($pageId);
            if (is_string($url) && $url !== '') {
                return $url;
            }

            self::warnOnce($modx, "page {$pageId} from {$settingKey} resolves to an empty URL");
        } else {
            self::warnOnce($modx, "{$settingKey} is not configured ({$pageId})");
        }

        $fallbackId = (int)$modx->getOption('site_start', null, 1);
        if ($fallbackId <= 0) {
            return '';
        }

        $url = $modx->makeUrl($fallbackId);

        return is_string($url) ? $url : '';
    }

    /** @var array<string, true> Warnings already emitted in this request. */
    private static array $warnedAbout = [];

    /**
     * Both page settings ship as 0, so warn once per reason instead of on every
     * render/redirect (#820).
     */
    private static function warnOnce(modX $modx, string $reason): void
    {
        $fallbackId = (int)$modx->getOption('site_start', null, 1);
        $message = "[CustomerPageService] {$reason}; using site start page {$fallbackId}";
        if (isset(self::$warnedAbout[$message])) {
            return;
        }

        self::$warnedAbout[$message] = true;
        $modx->log(modX::LOG_LEVEL_WARN, $message);
    }

    /**
     * Render page (abstract method for overriding in child classes)
     *
     * @return string HTML content
     */
    abstract public function render(): string;

    /**
     * Get raw page data without rendering (for CLI, API, tests)
     *
     * @return array Page data
     */
    abstract public function getData(): array;

    /**
     * Entry point for processing and rendering page
     *
     * @return string HTML content
     */
    public function process(): string
    {
        if (!$this->checkAuth()) {
            return $this->renderUnauthorized();
        }

        return $this->render();
    }
}
