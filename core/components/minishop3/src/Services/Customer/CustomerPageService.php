<?php

namespace MiniShop3\Services\Customer;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msCustomer;
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

        /** @var Fetch $pdoFetch */
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
        if (empty($_SESSION['ms3']['customer_id'])) {
            $this->modx->log(
                modX::LOG_LEVEL_DEBUG,
                "[CustomerPageService] Customer not authenticated (no session)"
            );
            return false;
        }

        $this->customerId = (int)$_SESSION['ms3']['customer_id'];

        $this->customer = $this->modx->getObject(msCustomer::class, $this->customerId);

        if (!$this->customer) {
            $this->modx->log(
                modX::LOG_LEVEL_WARN,
                "[CustomerPageService] Customer #{$this->customerId} not found in database"
            );
            return false;
        }

        return true;
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

        $chunk = $this->pdoFetch->getChunk($tpl, [
            'login_url' => $this->modx->makeUrl(
                $this->modx->getOption('ms3_customer_login_page_id', null, 1)
            ),
            'register_url' => $this->modx->makeUrl(
                $this->modx->getOption('ms3_customer_register_page_id', null, 1)
            ),
        ]);

        return is_string($chunk) ? $chunk : '';
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
