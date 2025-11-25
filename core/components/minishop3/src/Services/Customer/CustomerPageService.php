<?php

namespace MiniShop3\Services\Customer;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msCustomer;
use MODX\Revolution\modX;
use ModxPro\PdoTools\Fetch;

/**
 * CustomerPageService - базовый класс для страниц личного кабинета клиента
 *
 * Обеспечивает общую логику для всех страниц ЛК:
 * - Проверка авторизации
 * - Загрузка данных клиента
 * - Рендеринг через Fenom
 *
 * Используется как базовый класс для:
 * - ProfilePageService (профиль клиента)
 * - AddressesPageService (управление адресами)
 * - OrdersPageService (история заказов)
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

    /** @var int|null ID авторизованного клиента */
    protected ?int $customerId = null;

    /** @var msCustomer|null Объект авторизованного клиента */
    protected ?msCustomer $customer = null;

    /** @var array Параметры сниппета */
    protected array $scriptProperties = [];

    /**
     * @param modX $modx
     * @param MiniShop3 $ms3
     * @param array $scriptProperties Параметры из сниппета
     */
    public function __construct(modX $modx, MiniShop3 $ms3, array $scriptProperties = [])
    {
        $this->modx = $modx;
        $this->ms3 = $ms3;
        $this->scriptProperties = $scriptProperties;

        /** @var Fetch $pdoFetch */
        $this->pdoFetch = $this->modx->services->get(Fetch::class);

        // Загрузить лексиконы
        $this->modx->lexicon->load('minishop3:customer');
        $this->modx->lexicon->load('minishop3:default');
    }

    /**
     * Проверить авторизацию клиента
     *
     * @return bool true если клиент авторизован
     */
    public function checkAuth(): bool
    {
        // Проверка сессии
        if (empty($_SESSION['ms3']['customer_id'])) {
            $this->modx->log(
                modX::LOG_LEVEL_DEBUG,
                "[CustomerPageService] Customer not authenticated (no session)"
            );
            return false;
        }

        $this->customerId = (int)$_SESSION['ms3']['customer_id'];

        // Загрузка объекта клиента
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
     * Получить ID авторизованного клиента
     *
     * @return int|null
     */
    public function getCustomerId(): ?int
    {
        return $this->customerId;
    }

    /**
     * Получить объект авторизованного клиента
     *
     * @return msCustomer|null
     */
    public function getCustomer(): ?msCustomer
    {
        return $this->customer;
    }

    /**
     * Рендерить страницу для неавторизованного пользователя
     *
     * @return string HTML содержимое
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
     * Рендерить страницу (абстрактный метод для переопределения в дочерних классах)
     *
     * @return string HTML содержимое
     */
    abstract public function render(): string;

    /**
     * Получить сырые данные страницы без рендеринга (для CLI, API, тестов)
     *
     * @return array Данные страницы
     */
    abstract public function getData(): array;

    /**
     * Точка входа для обработки и рендеринга страницы
     *
     * @return string HTML содержимое
     */
    public function process(): string
    {
        // Проверка авторизации
        if (!$this->checkAuth()) {
            return $this->renderUnauthorized();
        }

        // Рендеринг страницы
        return $this->render();
    }
}
