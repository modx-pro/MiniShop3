<?php

namespace MiniShop3\Controllers\Cart;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderProduct;
use MiniShop3\Model\msOrderAddress;
use MiniShop3\Model\msProduct;
use MODX\Revolution\modX;

/**
 * Контроллер корзины товаров
 *
 * Управляет корзиной покупателя: добавление, изменение, удаление товаров.
 * Корзина хранится в БД как черновик заказа (msOrder со статусом draft).
 *
 * Для переопределения логики:
 * 1. Создайте свой класс, наследующий Cart
 * 2. Переопределите нужные методы (add, remove, change и т.д.)
 * 3. Укажите свой класс в системной настройке: ms3_cart_class = Your\Namespace\MyCart
 *
 * Пример расширения:
 * ```php
 * class MyCart extends \MiniShop3\Controllers\Cart\Cart {
 *     public function add($id, $count = 1, $options = []): array {
 *         // Проверка остатков на складе
 *         $product = $this->validateProduct($id);
 *         if ($product && $product->get('remains') <= 0) {
 *             return $this->error('Товар отсутствует на складе');
 *         }
 *
 *         return parent::add($id, $count, $options);
 *     }
 * }
 * ```
 *
 * @package MiniShop3\Controllers\Cart
 */
class Cart
{
    /** @var modX MODX объект */
    public modX $modx;

    /** @var MiniShop3 MiniShop3 объект */
    public MiniShop3 $ms3;

    /** @var array Конфигурация корзины */
    public array $config = [];

    /** @var string Контекст корзины */
    protected string $ctx = 'web';

    /** @var string Токен покупателя */
    protected string $token = '';

    /** @var msOrder|null Черновик заказа */
    protected ?msOrder $draft = null;

    /** @var array Данные корзины (массив товаров) */
    protected array $cart = [];

    /**
     * Конструктор
     *
     * @param MiniShop3 $ms3 MiniShop3 объект
     * @param array $config Дополнительная конфигурация
     */
    public function __construct(MiniShop3 $ms3, array $config = [])
    {
        $this->ms3 = $ms3;
        $this->modx = $ms3->modx;

        $this->config = array_merge([
            'max_count' => (int)$this->modx->getOption('ms3_cart_max_count', null, 1000, true),
            'allow_deleted' => false,
            'allow_unpublished' => false,
            'cart_product_key_fields' => $this->modx->getOption(
                'ms3_cart_product_key_fields',
                null,
                'id,options',
                true
            ),
        ], $config);

        $this->modx->lexicon->load('minishop3:cart');
    }

    /**
     * Инициализация корзины для контекста
     *
     * @param string $ctx Контекст MODX (web, mgr и т.д.)
     * @param string $token Токен покупателя
     * @return bool Успешность инициализации
     */
    public function initialize(string $ctx = 'web', string $token = ''): bool
    {
        if (empty($token)) {
            return false;
        }

        // Проверяем настройку: использовать ли один контекст для корзины
        $ms3_cart_context = (bool)$this->modx->getOption('ms3_cart_context', null, '0', true);
        $this->ctx = $ms3_cart_context ? 'web' : $ctx;
        $this->token = $token;

        return true;
    }

    /**
     * Получение корзины
     *
     * @return array Response ['success' => bool, 'message' => '', 'data' => ['cart' => [], 'status' => []]]
     */
    public function get(): array
    {
        if (empty($this->token)) {
            return $this->error('ms3_err_token');
        }

        $this->initDraft();
        $this->loadCart();

        // Событие BEFORE
        $response = $this->invokeEvent('msOnBeforeGetCart', [
            'draft' => $this->draft,
        ]);
        if (!$response['success']) {
            return $this->error($response['message']);
        }

        // Событие AFTER (может модифицировать данные корзины)
        $response = $this->invokeEvent('msOnGetCart', [
            'draft' => $this->draft,
            'data' => $this->cart,
        ]);

        if ($response['success'] && isset($response['data']['data'])) {
            $this->cart = $response['data']['data'];
        }

        return $this->success('ms3_cart_get_success', [
            'cart' => $this->cart,
            'status' => $this->getStatus(),
        ]);
    }

    /**
     * Добавление товара в корзину
     *
     * @param int $id ID товара
     * @param int $count Количество
     * @param array $options Опции товара ['color' => 'red', 'size' => 'L']
     * @return array Response ['success' => bool, 'message' => '', 'data' => [...]]
     */
    public function add(int $id, int $count = 1, array $options = []): array
    {
        // 1. Валидация токена
        if (empty($this->token)) {
            return $this->error('ms3_err_token');
        }

        // 2. Инициализация черновика заказа
        $this->initDraft();
        $this->loadCart();

        // 3. Валидация входных данных
        if (empty($id) || !is_numeric($id)) {
            return $this->error('ms3_cart_add_err_id');
        }

        $count = (int)$count;
        $options = $this->normalizeOptions($options);

        if ($count > $this->config['max_count'] || $count <= 0) {
            return $this->error('ms3_cart_add_err_count', $this->getStatus(), ['count' => $count]);
        }

        // 4. Получение и валидация товара
        $product = $this->validateProduct($id);
        if (!$product) {
            return $this->error('ms3_cart_add_err_nf', $this->getStatus());
        }

        // 5. Событие BEFORE (разработчик может изменить count/options)
        $response = $this->invokeEvent('msOnBeforeAddToCart', [
            'msProduct' => $product,
            'count' => $count,
            'options' => $options,
        ]);
        if (!$response['success']) {
            return $this->error($response['message']);
        }

        $count = $response['data']['count'];
        $options = $response['data']['options'];

        // 6. Проверка: товар уже в корзине?
        $product_key = $this->getProductKey($product->toArray(), $options);
        if (isset($this->cart[$product_key])) {
            // Увеличиваем количество существующего товара
            return $this->change($product_key, $this->cart[$product_key]['count'] + $count);
        }

        // 7. Создание позиции корзины
        $cartItem = $this->createCartItem($product, $count, $options, $product_key);

        // 8. Сохранение в БД
        $this->draft->addMany($cartItem, 'Products');
        $this->draft->save();

        // 9. Пересчет итогов заказа
        $this->recalculateDraft();

        // 10. Обновление локального кэша корзины
        $this->loadCart();

        // 11. Событие AFTER
        $response = $this->invokeEvent('msOnAddToCart', [
            'msProduct' => $product,
            'count' => $count,
            'options' => $options,
            'product_key' => $product_key,
        ]);
        if (!$response['success']) {
            return $this->error($response['message']);
        }

        // 12. Формирование ответа
        return $this->success('ms3_cart_add_success', [
            'last_key' => $product_key,
            'cart' => $this->cart,
            'status' => $this->getStatus(),
        ], ['count' => $count]);
    }

    /**
     * Изменение количества товара в корзине
     *
     * @param string $product_key Уникальный ключ товара в корзине
     * @param int $count Новое количество
     * @return array Response
     */
    public function change(string $product_key, int $count): array
    {
        if (empty($this->token)) {
            return $this->error('ms3_err_token');
        }

        $this->initDraft();
        $this->loadCart();

        if (!isset($this->cart[$product_key])) {
            return $this->error('ms3_cart_change_error', $this->getStatus());
        }

        $count = (int)$count;

        // Удаление при count <= 0
        if ($count <= 0) {
            return $this->remove($product_key);
        }

        if ($count > $this->config['max_count']) {
            return $this->error('ms3_cart_add_err_count', $this->getStatus(), ['count' => $count]);
        }

        // Событие BEFORE
        $response = $this->invokeEvent('msOnBeforeChangeInCart', [
            'product_key' => $product_key,
            'count' => $count,
        ]);
        if (!$response['success']) {
            return $this->error($response['message']);
        }
        $count = $response['data']['count'];

        // Обновление в БД
        $this->updateCartItemCount($product_key, $count);
        $this->recalculateDraft();
        $this->loadCart();

        // Событие AFTER
        $this->invokeEvent('msOnChangeInCart', [
            'product_key' => $product_key,
            'count' => $count,
        ]);

        return $this->success('ms3_cart_change_success', [
            'last_key' => $product_key,
            'cart' => $this->cart,
            'status' => $this->getStatus(),
        ], ['count' => $count]);
    }

    /**
     * Изменение опций товара в корзине
     *
     * @param string $product_key Уникальный ключ товара
     * @param array $options Новые опции
     * @return array Response
     */
    public function changeOption(string $product_key, array $options): array
    {
        if (empty($this->token)) {
            return $this->error('ms3_err_token');
        }

        $this->initDraft();
        $this->loadCart();

        if (!isset($this->cart[$product_key])) {
            return $this->error('ms3_cart_change_error', $this->getStatus());
        }

        if (empty($options)) {
            return $this->error('ms3_cart_change_options_error', $this->getStatus());
        }

        // Событие BEFORE
        $response = $this->invokeEvent('msOnBeforeChangeOptionsInCart', [
            'product_key' => $product_key,
            'options' => $options,
        ]);
        if (!$response['success']) {
            return $this->error($response['message']);
        }

        // Находим товар в черновике и обновляем опции
        $count = 0;
        $newProductKey = $product_key;

        /** @var msOrderProduct $product */
        foreach ($this->draft->getMany('Products') as $product) {
            if ($product_key === $product->get('product_key')) {
                $orderProductOptions = $product->get('options') ?? [];
                $count = $product->get('count');

                // Обновляем опции
                foreach ($options as $key => $value) {
                    if (!empty($value)) {
                        $orderProductOptions[$key] = $value;
                    } else {
                        unset($orderProductOptions[$key]);
                    }
                }

                // Генерируем новый ключ с учетом измененных опций
                $newProductKey = $this->getProductKey($product->Product->toArray(), $orderProductOptions);

                // Если товар с такими опциями уже есть - объединяем
                if ($newProductKey !== $product_key && isset($this->cart[$newProductKey])) {
                    $product->remove();
                    return $this->change($newProductKey, $this->cart[$newProductKey]['count'] + $count);
                }

                // Обновляем товар
                $product->set('product_key', $newProductKey);
                $product->set('options', $orderProductOptions);
                $product->save();
                break;
            }
        }

        $this->draft->save();
        $this->recalculateDraft();
        $this->loadCart();

        // Событие AFTER
        $this->invokeEvent('msOnChangeOptionInCart', [
            'old_product_key' => $product_key,
            'product_key' => $newProductKey,
            'options' => $options,
        ]);

        return $this->success('ms3_cart_change_success', [
            'last_key' => $newProductKey,
            'cart' => $this->cart,
            'status' => $this->getStatus(),
        ], ['count' => $count]);
    }

    /**
     * Удаление товара из корзины
     *
     * @param string $product_key Уникальный ключ товара
     * @return array Response
     */
    public function remove(string $product_key): array
    {
        if (empty($this->token)) {
            return $this->error('ms3_err_token');
        }

        $this->initDraft();
        $this->loadCart();

        if (!isset($this->cart[$product_key])) {
            return $this->error('ms3_cart_change_error', $this->getStatus());
        }

        // Событие BEFORE
        $response = $this->invokeEvent('msOnBeforeRemoveFromCart', [
            'product_key' => $product_key,
        ]);
        if (!$response['success']) {
            return $this->error($response['message']);
        }

        // Удаление из БД
        $this->removeCartItem($product_key);

        // Если корзина пуста - удаляем черновик
        if ($this->isCartEmpty()) {
            $this->draft->remove();
            $this->draft = null;
            $this->cart = [];
        } else {
            $this->recalculateDraft();
            $this->loadCart();
        }

        // Событие AFTER
        $this->invokeEvent('msOnRemoveFromCart', [
            'product_key' => $product_key,
        ]);

        return $this->success('ms3_cart_remove_success', [
            'last_key' => $product_key,
            'cart' => $this->cart,
            'status' => $this->getStatus(),
        ]);
    }

    /**
     * Очистка корзины
     *
     * @return array Response
     */
    public function clean(): array
    {
        if (empty($this->token)) {
            return $this->error('ms3_err_token');
        }

        $this->initDraft();
        $this->loadCart();

        // Событие BEFORE
        $response = $this->invokeEvent('msOnBeforeEmptyCart');
        if (!$response['success']) {
            return $this->error($response['message']);
        }

        // Удаление черновика
        if ($this->draft) {
            $this->draft->remove();
            $this->draft = null;
        }

        $this->cart = [];

        // Событие AFTER
        $this->invokeEvent('msOnEmptyCart');

        return $this->success('ms3_cart_clean_success', [
            'cart' => $this->cart,
            'status' => $this->getStatus(),
        ]);
    }

    /**
     * Получение статуса корзины
     *
     * @param array $data Дополнительные данные для объединения со статусом
     * @return array Response
     */
    public function status(array $data = []): array
    {
        if (empty($this->token)) {
            return $this->error('ms3_err_token');
        }

        if (empty($this->cart)) {
            $this->initDraft();
            $this->loadCart();
        }

        $status = array_merge($data, $this->getStatus());

        return $this->success('ms3_cart_status_success', $status);
    }

    /**
     * Установка всех товаров корзины одним массивом
     *
     * @param array $cart Массив товаров
     * @return void
     */
    public function set(array $cart = []): void
    {
        // TODO: Реализовать при необходимости
        // Этот метод может быть полезен для восстановления корзины из внешнего источника
    }

    /**
     * Генерация уникального ключа товара в корзине
     *
     * Ключ генерируется на основе полей, указанных в ms3_cart_product_key_fields
     * По умолчанию: id + options (товар с разными опциями = разные позиции в корзине)
     *
     * @param array $product Массив данных товара
     * @param array $options Опции товара
     * @return string Уникальный ключ (например: "ms3d41d8cd98f00b204e9800998ecf8427e")
     */
    public function getProductKey(array $product, array $options = []): string
    {
        $key_fields = array_map('trim', explode(',', $this->config['cart_product_key_fields']));
        $product['options'] = $options;
        $key = '';

        foreach ($key_fields as $key_field) {
            if (isset($product[$key_field])) {
                $key .= is_array($product[$key_field])
                    ? json_encode($product[$key_field])
                    : $product[$key_field];
            }
        }

        return 'ms' . md5($key);
    }

    // ========== PROTECTED ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ ==========

    /**
     * Инициализация черновика заказа (создание если не существует)
     *
     * @return void
     */
    protected function initDraft(): void
    {
        if ($this->draft !== null) {
            return; // Уже инициализирован
        }

        $this->draft = $this->getDraft();
        if (!$this->draft) {
            $this->draft = $this->createDraft();
        }

        // Привязка покупателя к черновику
        if (empty($this->draft->get('customer_id'))) {
            $this->attachCustomer();
        }
    }

    /**
     * Получение существующего черновика заказа
     *
     * @return msOrder|null
     */
    protected function getDraft(): ?msOrder
    {
        $status_draft = $this->modx->getOption('ms3_status_draft', null, 1);
        return $this->modx->getObject(msOrder::class, [
            'token' => $this->token,
            'status_id' => $status_draft,
            'context' => $this->ctx,
        ]);
    }

    /**
     * Создание нового черновика заказа
     *
     * @return msOrder
     */
    protected function createDraft(): msOrder
    {
        $status_draft = $this->modx->getOption('ms3_status_draft', null, 1);

        /** @var msOrder $draft */
        $draft = $this->modx->newObject(msOrder::class);
        $draft->fromArray([
            'token' => $this->token,
            'status_id' => $status_draft,
            'createdon' => time(),
            'context' => $this->ctx,
            'user_id' => $this->modx->getLoginUserID($this->ctx),
        ]);

        $draft->save();

        // Создание адреса доставки
        $this->createOrderAddress($draft);

        return $draft;
    }

    /**
     * Создание адреса доставки для заказа
     *
     * @param msOrder $draft Черновик заказа
     * @return void
     */
    protected function createOrderAddress(msOrder $draft): void
    {
        /** @var msOrderAddress $address */
        $address = $this->modx->newObject(msOrderAddress::class);
        $address->fromArray([
            'createdon' => time(),
            'user_id' => $this->modx->getLoginUserID($this->ctx),
            'order_id' => $draft->get('id'),
        ]);
        $address->save();
    }

    /**
     * Привязка покупателя к черновику заказа
     *
     * @return void
     */
    protected function attachCustomer(): void
    {
        $this->ms3->customer->initialize($this->token);
        $customerResponse = $this->ms3->customer->getFields();

        if ($customerResponse['success'] && !empty($customerResponse['data']['id'])) {
            $customer = $customerResponse['data'];
            $this->draft->set('customer_id', $customer['id']);
            $this->draft->save();
        }
    }

    /**
     * Загрузка корзины из черновика в массив
     *
     * @return void
     */
    protected function loadCart(): void
    {
        $this->cart = [];

        if (!$this->draft) {
            return;
        }

        /** @var msOrderProduct $item */
        foreach ($this->draft->getMany('Products') as $item) {
            $key = $item->get('product_key');
            $this->cart[$key] = $item->toArray();
        }
    }

    /**
     * Валидация товара (существование, публикация, удаление)
     *
     * @param int $id ID товара
     * @return msProduct|null
     */
    protected function validateProduct(int $id): ?msProduct
    {
        $filter = ['id' => $id, 'class_key' => msProduct::class];

        if (!$this->config['allow_deleted']) {
            $filter['deleted'] = 0;
        }
        if (!$this->config['allow_unpublished']) {
            $filter['published'] = 1;
        }

        return $this->modx->getObject(msProduct::class, $filter);
    }

    /**
     * Создание позиции корзины (msOrderProduct)
     *
     * @param msProduct $product Товар
     * @param int $count Количество
     * @param array $options Опции
     * @param string $product_key Ключ товара
     * @return msOrderProduct
     */
    protected function createCartItem(msProduct $product, int $count, array $options, string $product_key): msOrderProduct
    {
        $price = $product->getPrice();
        $old_price = $product->get('old_price');
        $weight = $product->getWeight();

        $discount_price = $old_price > 0 ? $old_price - $price : 0;

        /** @var msOrderProduct $item */
        $item = $this->modx->newObject(msOrderProduct::class);
        $item->fromArray([
            'product_id' => $product->get('id'),
            'product_key' => $product_key,
            'name' => $product->get('pagetitle'),
            'count' => $count,
            'price' => $price,
            'weight' => $weight,
            'cost' => $price * $count,
            'options' => $options,
            'properties' => [
                'old_price' => $old_price,
                'discount_price' => $discount_price,
                'discount_cost' => $discount_price * $count,
            ],
        ]);

        return $item;
    }

    /**
     * Обновление количества товара в корзине
     *
     * @param string $product_key Ключ товара
     * @param int $count Новое количество
     * @return void
     */
    protected function updateCartItemCount(string $product_key, int $count): void
    {
        /** @var msOrderProduct $product */
        foreach ($this->draft->getMany('Products') as $product) {
            if ($product_key === $product->get('product_key')) {
                $price = $product->get('price');
                $product->set('count', $count);
                $product->set('cost', $price * $count);

                // Обновляем discount_cost в properties
                $properties = $product->get('properties') ?? [];
                if (isset($properties['discount_price'])) {
                    $properties['discount_cost'] = $properties['discount_price'] * $count;
                    $product->set('properties', $properties);
                }

                $product->save();
                break;
            }
        }

        $this->draft->save();
    }

    /**
     * Удаление товара из корзины
     *
     * @param string $product_key Ключ товара
     * @return void
     */
    protected function removeCartItem(string $product_key): void
    {
        /** @var msOrderProduct $product */
        foreach ($this->draft->getMany('Products') as $product) {
            if ($product_key === $product->get('product_key')) {
                $product->remove();
                break;
            }
        }
    }

    /**
     * Проверка: пуста ли корзина
     *
     * @return bool
     */
    protected function isCartEmpty(): bool
    {
        if (!$this->draft) {
            return true;
        }

        $count = $this->modx->getCount(msOrderProduct::class, [
            'order_id' => $this->draft->get('id')
        ]);

        return $count === 0;
    }

    /**
     * Пересчет итогов черновика (cart_cost, cost, weight)
     *
     * @return void
     */
    protected function recalculateDraft(): void
    {
        if (!$this->draft) {
            return;
        }

        $cart_cost = 0;
        $weight = 0;

        /** @var msOrderProduct $product */
        foreach ($this->draft->getMany('Products') as $product) {
            $cart_cost += $product->get('cost');
            $weight += $product->get('weight') * $product->get('count');
        }

        $delivery_cost = $this->draft->get('delivery_cost') ?? 0;

        $this->draft->fromArray([
            'updatedon' => time(),
            'cart_cost' => $cart_cost,
            'cost' => $cart_cost + $delivery_cost,
            'weight' => $weight,
        ]);

        $this->draft->save();
    }

    /**
     * Получение статуса корзины (итоги)
     *
     * @return array Массив с итогами (total_count, total_cost, total_weight и т.д.)
     */
    protected function getStatus(): array
    {
        $status = [
            'total_count' => 0,
            'total_cost' => 0,
            'total_weight' => 0,
            'total_discount' => 0,
            'total_positions' => count($this->cart),
        ];

        foreach ($this->cart as $item) {
            $status['total_count'] += $item['count'];
            $status['total_cost'] += $item['cost'];
            $status['total_weight'] += $item['weight'] * $item['count'];
            $status['total_discount'] += ($item['properties']['discount_price'] ?? 0) * $item['count'];
        }

        // Событие для модификации статуса
        $response = $this->invokeEvent('msOnGetStatusCart', [
            'status' => $status,
        ]);

        if ($response['success'] && isset($response['data']['status'])) {
            $status = $response['data']['status'];
        }

        return $status;
    }

    /**
     * Нормализация опций (преобразование строки JSON в массив)
     *
     * @param mixed $options Опции (массив или JSON строка)
     * @return array
     */
    protected function normalizeOptions($options): array
    {
        if (is_string($options)) {
            $decoded = json_decode($options, true);
            return is_array($decoded) ? $decoded : [];
        }

        return is_array($options) ? $options : [];
    }

    /**
     * Shorthand для успешного ответа
     *
     * @param string $message Ключ лексикона
     * @param array $data Данные ответа
     * @param array $placeholders Плейсхолдеры для сообщения
     * @return array
     */
    protected function success(string $message = '', array $data = [], array $placeholders = []): array
    {
        return $this->ms3->utils->success($message, $data, $placeholders);
    }

    /**
     * Shorthand для ответа с ошибкой
     *
     * @param string $message Ключ лексикона
     * @param array $data Данные ответа
     * @param array $placeholders Плейсхолдеры для сообщения
     * @return array
     */
    protected function error(string $message = '', array $data = [], array $placeholders = []): array
    {
        return $this->ms3->utils->error($message, $data, $placeholders);
    }

    /**
     * Shorthand для вызова события
     *
     * @param string $eventName Имя события
     * @param array $params Параметры события
     * @return array
     */
    protected function invokeEvent(string $eventName, array $params = []): array
    {
        $params['controller'] = $this;
        return $this->ms3->utils->invokeEvent($eventName, $params);
    }
}
