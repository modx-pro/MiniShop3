<?php

namespace MiniShop3\Controllers\Order;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msCustomerAddress;
use MiniShop3\Model\msDelivery;
use MiniShop3\Model\msDeliveryMember;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderAddress;
use MiniShop3\Model\msPayment;
use MODX\Revolution\modUser;
use MODX\Revolution\modUserProfile;
use MODX\Revolution\modUserSetting;
use MODX\Revolution\modX;
use Rakit\Validation\Validator;

/**
 * Order controller (unified)
 * Manages order drafts, validation, submission, and cost calculation
 *
 * Replaces: DBOrder + DBStorage abstraction
 * Following Cart.php pattern - all logic in one class
 */
class Order
{
    protected modX $modx;
    protected MiniShop3 $ms3;
    protected string $ctx;
    protected string $token = '';
    protected ?msOrder $draft = null;

    public array $config = [];
    private array $order = [];

    protected array $validationRules = [];
    protected array $validationMessages = [];
    protected ?array $deliverValidationRules = null;
    protected ?OrderLog $log = null;

    public function __construct(MiniShop3 $ms3, array $config = [])
    {
        $this->ms3 = $ms3;
        $this->modx = $ms3->modx;
        $this->ctx = $ms3->config['ctx'] ?? 'web';
        $this->config = array_merge([], $config);

        $this->modx->lexicon->load('minishop3:cart');
        $this->modx->lexicon->load('minishop3:order');
    }

    /**
     * Initialize order with token
     */
    public function initialize(string $token = '', array $config = []): bool
    {
        if (empty($token)) {
            return false;
        }
        $this->token = $token;
        $this->config = array_merge($this->config, $config);

        if (!empty($_SESSION['ms3']['validation']['rules'])) {
            $this->validationRules = $_SESSION['ms3']['validation']['rules'];
        }
        if (!empty($_SESSION['ms3']['validation']['messages'])) {
            $this->validationMessages = $_SESSION['ms3']['validation']['messages'];
        }

        $this->log = new OrderLog($this->ms3);
        return true;
    }

    /**
     * Initialize draft order (create if not exists)
     */
    public function initDraft(): bool
    {
        if (empty($this->token)) {
            return false;
        }
        $this->draft = $this->getDraft($this->token);
        if (empty($this->draft)) {
            $this->draft = $this->newDraft($this->token);
        }
        if (empty($this->draft->get('customer_id'))) {
            $this->ms3->customer->initialize($this->token);
            $customerResponse = $this->ms3->customer->getFields();
            if ($customerResponse['success'] && !empty($customerResponse['data']['id'])) {
                $customer = $customerResponse['data'];
                $this->draft->set('customer_id', $customer['id']);
                $this->draft->save();
            }
        }
        return true;
    }

    /**
     * Get existing draft order by token
     */
    protected function getDraft(string $token): ?msOrder
    {
        $status_draft = $this->modx->getOption('ms3_status_draft', null, 1);
        $where = [
            'token' => $token,
            'status_id' => $status_draft,
            'context' => $this->ctx
        ];
        return $this->modx->getObject(msOrder::class, $where);
    }

    /**
     * Create new draft order
     */
    protected function newDraft(string $token): msOrder
    {
        $status_draft = $this->modx->getOption('ms3_status_draft', null, 1);

        /** @var msOrder $msOrder */
        $msOrder = $this->modx->newObject(msOrder::class);
        $data = [
            'token' => $token,
            'uuid' => \Ramsey\Uuid\Uuid::uuid4()->toString(),
            'status_id' => $status_draft,
            'context' => $this->ctx,
            'createdon' => time(),
            'user_id' => $this->modx->getLoginUserID($this->ctx),
        ];
        $msOrder->fromArray($data);

        //TODO Событие перед созданием черновика
        //TODO Запись в лог msOrderLog
        $save = $msOrder->save();
        if ($save) {
            $msOrderAddress = $this->modx->newObject(msOrderAddress::class);
            $msOrderAddress->fromArray([
                'createdon' => time(),
                'order_id' => $msOrder->get('id')
            ]);
            $msOrderAddress->save();
            //TODO Событие по факту созданием черновика
        }

        return $msOrder;
    }

    /**
     * Recalculate order costs (cart, delivery, total)
     */
    public function restrictDraft(msOrder $draft): void
    {
        //TODO событие до перерасчета заказа
        $products = $draft->getMany('Products');
        $cart_cost = 0;
        $weight = 0;
        if (!empty($products)) {
            foreach ($products as $product) {
                $weight += $product->get('weight');
                $cart_cost += $product->get('cost');
            }
        }

        $delivery_cost = $draft->get('delivery_cost');
        $cost = $cart_cost + $delivery_cost;

        //TODO событие перерасчета заказа
        $draft->set('updatedon', time());
        $draft->set('cart_cost', $cart_cost);
        $draft->set('cost', $cost);
        $draft->set('weight', $weight);
        $draft->save();
    }

    /**
     * Get order data
     */
    public function get(): array
    {
        if (empty($this->token)) {
            return $this->error('ms3_err_token');
        }

        $this->draft = $this->getDraft($this->token);

        //TODO Добавить событие?
        $this->order = $this->getOrder();

        $data = [];
        $data['order'] = $this->order;
        return $this->success('ms3_order_get_success', $data);
    }

    /**
     * Get cart cost from draft order
     */
    public function getCartCost(): array
    {
        if (empty($this->token)) {
            return $this->error('ms3_err_token');
        }
        $this->initDraft();

        if (empty($this->order)) {
            $response = $this->get();
            if ($response['success']) {
                $this->order = $response['data']['order'];
            }
        }

        // TODO проверить доступна ли вообще корзина при прямом независимом вызове метода
        $response = $this->ms3->utils->invokeEvent('msOnBeforeGetCartCost', [
            'controller' => $this,
            'cart' => $this->ms3->cart,
        ]);
        if (!$response['success']) {
            return $this->error($response['message']);
        }
        $this->ms3->cart->initialize($this->ctx, $this->token);
        $response = $this->ms3->cart->status();
        if (!$response['success']) {
            return $this->error($response['message']);
        }

        $status = $response['data'];
        $cost = $status['total_cost'];

        $response = $this->ms3->utils->invokeEvent('msOnGetCartCost', [
            'controller' => $this,
            'cart' => $this->ms3->cart,
            'cost' => $cost,
        ]);
        if (!$response['success']) {
            return $this->error($response['message']);
        }
        $cost = $response['data']['cost'];
        return $this->success('ms3_order_getcost_success', [
            'cost' => $cost,
        ]);
    }

    /**
     * Get delivery cost
     */
    public function getDeliveryCost(): array
    {
        if (empty($this->token)) {
            return $this->error('ms3_err_token');
        }
        $this->initDraft();
        if (empty($this->order)) {
            $response = $this->get();
            if ($response['success']) {
                $this->order = $response['data']['order'];
            }
        }

        // TODO проверить доступна ли вообще корзина при прямом независимом вызове метода
        $response = $this->ms3->utils->invokeEvent('msOnBeforeGetDeliveryCost', [
            'storageController' => $this,
            'cartController' => $this->ms3->cart,
            'orderController' => $this->ms3->order
        ]);
        if (!$response['success']) {
            return $this->error($response['message']);
        }

        $deliveryCost = 0;
        if (empty($this->order['delivery_id'])) {
            return $this->success('ms3_order_getcost_success', [
                'cost' => $deliveryCost,
            ]);
        }

        /** @var msDelivery $msDelivery */
        $msDelivery = $this->modx->getObject(
            msDelivery::class,
            ['id' => $this->order['delivery_id']]
        );
        if (!$msDelivery) {
            return $this->success('ms3_order_getcost_success', [
                'cost' => $deliveryCost,
            ]);
        }

        $cartCostResponse = $this->getCartCost();
        $cartCost = 0;
        if ($cartCostResponse['success']) {
            $cartCost = $cartCostResponse['data']['cost'];
        }
        $deliveryCost = $msDelivery->getCost($this->draft, $cartCost);

        $response = $this->ms3->utils->invokeEvent('msOnGetDeliveryCost', [
            'storageController' => $this,
            'cartController' => $this->ms3->cart,
            'orderController' => $this->ms3->order,
            'cost' => $deliveryCost,
        ]);
        if (!$response['success']) {
            return $this->error($response['message']);
        }
        $deliveryCost = $response['data']['cost'];

        $this->setDeliveryCost($deliveryCost);
        return $this->success('ms3_order_getcost_success', [
            'cost' => $deliveryCost,
        ]);
    }

    /**
     * Get payment cost
     */
    public function getPaymentCost(): array
    {
        if (empty($this->token)) {
            return $this->error('ms3_err_token');
        }
        $this->initDraft();

        if (empty($this->order)) {
            $response = $this->get();
            if ($response['success']) {
                $this->order = $response['data']['order'];
            }
        }

        // TODO проверить доступна ли вообще корзина при прямом независимом вызове метода
        $response = $this->ms3->utils->invokeEvent('msOnBeforeGetPaymentCost', [
            'storageController' => $this,
            'cartController' => $this->ms3->cart,
            'orderController' => $this->ms3->order
        ]);
        if (!$response['success']) {
            return $this->error($response['message']);
        }

        $paymentCost = 0;
        if (empty($this->order['payment_id'])) {
            return $this->success('ms3_order_getcost_success', [
                'cost' => $paymentCost,
            ]);
        }

        /** @var msPayment $msPayment */
        $msPayment = $this->modx->getObject(
            msPayment::class,
            ['id' => $this->order['payment_id']]
        );
        if (!$msPayment) {
            return $this->success('ms3_order_getcost_success', [
                'cost' => $paymentCost,
            ]);
        }

        $cartCostResponse = $this->getCartCost();
        $cartCost = 0;
        if ($cartCostResponse['success']) {
            $cartCost = $cartCostResponse['data']['cost'];
        }
        //TODO пересмотреть модель оплаты  и ее методы
        $costWithPayment = $msPayment->getCost($this->draft, $cartCost);
        $paymentCost = $costWithPayment - $cartCost;

        $response = $this->ms3->utils->invokeEvent('msOnGetPaymentCost', [
            'storageController' => $this,
            'cartController' => $this->ms3->cart,
            'orderController' => $this->ms3->order,
            'cost' => $paymentCost,
        ]);
        if (!$response['success']) {
            return $this->error($response['message']);
        }
        $paymentCost = $response['data']['cost'];

        return $this->success('', [
            'cost' => $paymentCost,
        ]);
    }

    /**
     * Get total cost (cart + delivery + payment)
     */
    public function getCost(bool $only_cost = false): array
    {
        $cartCostResponse = $this->getCartCost();
        $cartCost = 0;
        if ($cartCostResponse['success']) {
            $cartCost = $cartCostResponse['data']['cost'];
        }

        $deliveryCostResponse = $this->getDeliveryCost();
        $deliveryCost = 0;
        if ($deliveryCostResponse['success']) {
            $deliveryCost = $deliveryCostResponse['data']['cost'];
        }

        $cartPaymentResponse = $this->getPaymentCost();
        $paymentCost = 0;
        if ($cartPaymentResponse['success']) {
            $paymentCost = $cartPaymentResponse['data']['cost'];
        }

        $cost = $cartCost + $deliveryCost + $paymentCost;

        if ($only_cost) {
            return $this->success('ms3_order_getcost_success', ['cost' => $cost]);
        }

        $data = [
            'cost' => $cost,
            'cart_cost' => $cartCost,
            'delivery_cost' => $deliveryCost,
            'payment_cost' => $paymentCost,
        ];

        $response = $this->ms3->cart->status();
        if ($response['success']) {
            $status = $response['data'];
            $data = array_merge($data, $status);
        }

        return $this->success('ms3_order_getcost_success', $data);
    }

    /**
     * Add or update order field
     */
    public function add(string $key, mixed $value = null): array
    {
        if (empty($this->order)) {
            $this->initDraft();
            $response = $this->get();
            if ($response['success']) {
                $this->order = $response['data']['order'];
            }
        }
        $response = $this->ms3->utils->invokeEvent('msOnBeforeAddToOrder', [
            'key' => $key,
            'value' => $value,
            'controller' => $this,
        ]);
        if (!$response['success']) {
            return $this->error($response['message']);
        }
        $value = $response['data']['value'];

        if ($key === 'address_hash') {
            return $this->setCustomerAddress($value);
        }

        if (empty($value)) {
            $this->remove($key);
            return $this->success('', [$key => null]);
        }
        $validateResponse = $this->validate($key, $value);
        if ($validateResponse['success']) {
            $validated = $validateResponse['data']['value'];
            $response = $this->ms3->utils->invokeEvent('msOnAddToOrder', [
                'key' => $key,
                'value' => $validated,
                'controller' => $this,
            ]);
            if (!$response['success']) {
                return $this->error($response['message']);
            }
            $validated = $response['data']['value'];
            $this->updateDraft($key, $validated);

            return $this->success('', [$key => $validated]);
        }
        $this->updateDraft($key);
        return $this->error($validateResponse['data']['error'][$key], [$key => null]);
    }

    /**
     * Validate order field value
     */
    public function validate(string $key, mixed $value): mixed
    {
        if (empty($this->order)) {
            $this->initDraft();
            $response = $this->get();
            if ($response['success']) {
                $this->order = $response['data']['order'];
            }
        }
        //TODO реализовать use custom validation rule для проверки существования payment, delivery,
        // для показа уникального message
        $this->validationRules = [
            'delivery_id' => 'required|numeric',
            'payment_id' => 'required|numeric',
        ];

        $this->validationMessages = [
            'required' => 'Обязательно для заполнения',
            'numeric' => 'Требуется число',
            'min' => 'Минимум :min символов',
            'email' => 'Email заполнен некорректно'
        ];

        if (!empty($this->order['delivery_id']) && empty($this->deliverValidationRules)) {
            $response = $this->getDeliveryValidationRules($this->order['delivery_id']);
            if (!empty($response['success'])) {
                $this->deliverValidationRules = $response['data']['validation_rules'];
                $this->validationRules = array_unique(
                    array_merge($this->validationRules, $this->deliverValidationRules)
                );
            }
        }

        $eventParams = [
            'key' => $key,
            'value' => $value,
            'controller' => $this,
        ];
        $response = $this->invokeEvent('msOnBeforeValidateOrderValue', $eventParams);
        $value = $response['data']['value'];

        if (!isset($this->validationRules[$key])) {
            return $this->success('', [
                'value' => $response['data']['value']
            ]);
        }

        $validator = new Validator();

        $validation = $validator->validate(
            [$key => $value],
            [$key => $this->validationRules[$key]],
            $this->validationMessages
        );

        $validation->validate();

        if ($validation->fails()) {
            $errors = $validation->errors();
            $eventParams = [
                'key' => $key,
                'value' => $value,
                'error' => $errors->firstOfAll(),
                'controller' => $this,
            ];
            $response = $this->invokeEvent('msOnErrorValidateOrderValue', $eventParams);
            if (!empty($response['data']['error'])) {
                return $this->error('', [
                    'error' => $response['data']['error']
                ]);
            }
        } else {
            $eventParams = [
                'key' => $key,
                'value' => $value,
                'controller' => $this,
            ];
            $response = $this->invokeEvent('msOnValidateOrderValue', $eventParams);
        }
        return $this->success('', [
            'value' => $response['data']['value']
        ]);
    }

    /**
     * Remove order field
     */
    public function remove(string $key): bool
    {
        if (empty($this->order)) {
            $this->initDraft();
            $response = $this->get();
            if ($response['success']) {
                $this->order = $response['data']['order'];
            }
        }

        $exists = array_key_exists($key, $this->order)
            || array_key_exists('address_' . $key, $this->order);
        if ($exists) {
            $response = $this->ms3->utils->invokeEvent('msOnBeforeRemoveFromOrder', [
                'key' => $key,
                'controller' => $this,
            ]);
            if (!$response['success']) {
                return $this->error($response['message']);
            }
            $this->updateDraft($key);
            $response = $this->ms3->utils->invokeEvent('msOnRemoveFromOrder', [
                'key' => $key,
                'controller' => $this,
            ]);
            if (!$response['success']) {
                return $this->error($response['message']);
            }
        }

        return $exists;
    }

    /**
     * Set multiple order fields at once
     */
    public function set(array $order): array
    {
        if (empty($this->order)) {
            $this->initDraft();
            $response = $this->get();
            if ($response['success']) {
                $this->order = $response['data']['order'];
            }
        }
        //TODO  Event before set
        //TODO Сообрать массив возможных ошибок валидации
        foreach ($order as $key => $value) {
            $this->add($key, $value);
        }
        // TODO event on set

        $data = [];
        $this->order = $this->getOrder();
        $data['order'] = $this->order;
        return $this->success('ms3_order_set_success', $data);
    }

    /**
     * Submit order (convert draft to new order)
     */
    public function submit(array $data = []): array
    {
        if (empty($this->token)) {
            return $this->error('ms3_err_token');
        }
        $this->initDraft();

        if (empty($this->order)) {
            $response = $this->get();
            if ($response['success']) {
                $this->order = $response['data']['order'];
            }
        }

        $response = $this->ms3->utils->invokeEvent('msOnSubmitOrder', [
            'data' => $data,
            'controller' => $this,
        ]);
        if (!$response['success']) {
            $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[Order::submit] Event msOnSubmitOrder failed: ' . $response['message']);
            return $this->error($response['message']);
        }
        if (!empty($response['data']['data'])) {
            $this->set($response['data']['data']);
        }

        $this->ms3->cart->initialize($this->ctx, $this->token);
        $response = $this->ms3->cart->status();
        if (!$response['success']) {
            $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[Order::submit] Cart status failed: ' . $response['message']);
            return $this->error($response['message']);
        }
        $cart_status = $response['data'];
        if (empty($cart_status['total_count'])) {
            $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[Order::submit] Cart is empty');
            return $this->error('ms3_order_err_empty');
        }

        $customer_id = $this->draft->customer_id;
        if (empty($this->draft->customer_id)) {
            $this->ms3->customer->initialize($this->token);
            $customer_id = $this->ms3->customer->getId();
            if (empty($customer_id)) {
                return $this->error('ms3_err_customer_nf');
            }
        }

        $addressData = [
            'updatedon' => time(),
        ];

        if (empty($this->draft->Customer)) {
            $this->draft->set('customer_id', $customer_id);
            $this->draft->save();
        }

        //TODO  тут возможно понадобится получить клиента через $this->ms3->customer->getFields
        if (empty($this->order['address_first_name']) && !empty($this->draft->Customer->get('last_name'))) {
            $this->add('first_name', $this->draft->Customer->get('first_name'));
        }
        if (empty($this->order['address_last_name']) && !empty($this->draft->Customer->get('last_name'))) {
            $this->add('last_name', $this->draft->Customer->get('last_name'));
        }
        if (empty($this->order['address_email']) && !empty($this->draft->Customer->get('email'))) {
            $this->add('email', $this->draft->Customer->get('email'));
        }
        if (empty($this->order['address_phone']) && !empty($this->draft->Customer->get('phone'))) {
            $this->add('phone', $this->draft->Customer->get('phone'));
        }

        // reload order after additional data
        $response = $this->get();
        if ($response['success']) {
            $this->order = $response['data']['order'];
        }

        // Check if delivery method is selected
        if (empty($this->order['delivery_id'])) {
            return $this->error('ms3_order_err_delivery', ['delivery_id']);
        }

        $response = $this->getDeliveryRequiresFields();

        if (!$response['success']) {
            return $this->error($response['message']);
        }

        $requires = $response['data']['requires'];
        $errors = [];
        foreach ($requires as $k => $v) {
            if (empty($this->order[$k]) && empty($this->order['address_' . $k])) {
                $errors[] = $k;
            }
        }

        if (!empty($errors)) {
            return $this->error('ms3_order_err_requires', $errors);
        }

        $registerUser = $this->modx->getOption('ms3_order_register_user_on_submit', null, false);
        $user_id = 0;
        if ($registerUser) {
            $user_id = $this->getUserId();
            if (empty($user_id)) {
                return $this->error('ms3_err_user_nf');
            }
        }

        $response = $this->getCost();
        if (!$response['success']) {
            return $this->error($response['message']);
        }
        $deliveryCost = $response['data']['delivery_cost'];
        $cost = $response['data']['cart_cost'];
        $num = $this->getNewOrderNum();
        $this->draft->fromArray([
            'customer_id' => $customer_id,
            'user_id' => $user_id,
            'updatedon' => time(),
            'num' => $num,
            'delivery_cost' => $deliveryCost,
            'cost' => $cost,
        ]);

        $this->draft->Address->fromArray($addressData);
        $this->draft->save();

        $properties = $this->draft->get('properties');
        if (!empty($properties['save_address']) && !empty($customer_id)) {
            $customerAddressData = [
                'customer_id' => $customer_id,
                'country' => $this->order['address_country'],
                'index' => $this->order['address_index'],
                'region' => $this->order['address_region'],
                'city' => $this->order['address_city'],
                'metro' => $this->order['address_metro'],
                'street' => $this->order['address_street'],
                'building' => $this->order['address_building'],
                'entrance' => $this->order['address_entrance'],
                'floor' => $this->order['address_floor'],
                'room' => $this->order['address_room'],
                'comment' => $this->order['address_comment'],
            ];
            $this->ms3->customer->addAddress($customerAddressData);
        }

        // TODO  а нужно здесь это событие?
        $response = $this->ms3->utils->invokeEvent('msOnBeforeCreateOrder', [
            'msOrder' => $this->draft,
            'controller' => $this,
        ]);
        if (!$response['success']) {
            return $this->error($response['message']);
        }

        $response = $this->ms3->utils->invokeEvent('msOnCreateOrder', [
            'msOrder' => $this->draft,
            'controller' => $this,
        ]);

        if (!$response['success']) {
            return $this->error($response['message']);
        }
        if (empty($_SESSION['ms3']['orders'])) {
            $_SESSION['ms3']['orders'] = [];
        }
        $_SESSION['ms3']['orders'][] = $this->draft->get('id');

        // Trying to set status "new"
        $status_new = $this->modx->getOption('ms3_status_new', null, 1);
        $orderStatus = new OrderStatus($this->ms3);
        $response = $orderStatus->change($this->draft->get('id'), $status_new);

        if ($response !== true) {
            return $this->error($response, ['msorder' => $this->draft->get('uuid')]);
        }
        // Reload order object after changes in OrderStatus::change method

        /** @var msOrder $msOrder */
        $msOrder = $this->modx->getObject(msOrder::class, ['id' => $this->draft->get('id')]);
        $msPayment = $this->modx->getObject(
            msPayment::class,
            ['id' => $msOrder->get('payment_id'), 'active' => 1]
        );
        if (!$msPayment) {
            return $this->success('', ['msorder' => $msOrder->get('uuid')]);
        }

        $response = $msPayment->send($msOrder);
        if (!$response['success']) {
            return $this->error($response['message']);
        }
        if (!empty($response['data']['redirect'])) {
            return $response;
        }
        $thanks_id = $this->modx->getOption('ms3_order_redirect_thanks_id', null, 1);
        $redirect = $this->modx->makeUrl($thanks_id, $this->ctx, ['msorder' => $msOrder->get('uuid')]);
        $response['data']['redirect'] = $redirect;
        return $response;
    }

    /**
     * Clean order data (reset all fields to null)
     */
    public function clean(): array
    {
        if (empty($this->draft)) {
            $this->initDraft();
        }
        //TODO  Event before clean
        foreach ($this->draft->Address->_fields as $key => $value) {
            switch ($key) {
                case 'id':
                case 'order_id':
                case 'user_id':
                case 'createdon':
                    break;
                default:
                    $this->draft->Address->set($key, null);
            }
        }
        $this->draft->Address->save();
        $this->draft->set('updatedon', time());

        foreach ($this->draft->_fields as $key => $value) {
            switch ($key) {
                case 'id':
                case 'user_id':
                case 'token':
                case 'createdon':
                    break;
                default:
                    $this->draft->set($key, null);
            }
        }
        $this->draft->set('updatedon', time());
        $this->draft->save();

        // TODO event on clean

        return $this->success('ms3_order_clean_success');
    }

    /**
     * Set customer address from saved addresses
     */
    public function setCustomerAddress(?string $addressHash = null): array
    {
        if (empty($this->token)) {
            return $this->error('');
        }
        if (empty($addressHash)) {
            return $this->cleanCustomerAddress();
        }

        if (empty($this->order)) {
            $response = $this->get();
            if ($response['success']) {
                $this->order = $response['data']['order'];
            }
        }

        if (empty($this->order['customer_id'])) {
            return $this->error('');
        }

        $msCustomerAddress = $this->modx->getObject(msCustomerAddress::class, [
            'customer_id' => $this->order['customer_id'],
            'hash' => $addressHash,
        ]);
        if (!$msCustomerAddress) {
            return $this->error('');
        }

        $fields = $this->getCustomerAddressFields([
            'id', 'customer_id', 'hash', 'name', 'comment',
            'createdon', 'updatedon', 'active'
        ]);

        $returnData = [];

        foreach ($fields as $key => $value) {
            if (in_array('address_' . $key, array_keys($this->order))) {
                $this->add($key, $msCustomerAddress->get($key));
                $returnData[$key] = $msCustomerAddress->get($key);
            }
        }

        $properties = $this->order['properties'];
        unset($properties['save_address']);
        $properties['address_hash'] = $addressHash;
        $this->add('properties', $properties);
        return $this->success('', $returnData);
    }

    /**
     * Clean customer address fields
     */
    public function cleanCustomerAddress(): array
    {
        if (empty($this->token)) {
            return $this->error('');
        }
        if (empty($this->order)) {
            $response = $this->get();
            if ($response['success']) {
                $this->order = $response['data']['order'];
            }
        }

        $fields = $this->getCustomerAddressFields([
            'id', 'customer_id', 'hash', 'name', 'comment',
            'createdon', 'updatedon', 'active'
        ]);
        foreach ($fields as $key => $value) {
            if (in_array('address_' . $key, array_keys($this->order)) && !empty($this->order['address_' . $key])) {
                $this->add($key);
            }
        }

        $properties = $this->order['properties'];
        unset($properties['address_hash']);
        $this->add('properties', $properties);

        return $this->success('');
    }

    /**
     * Get validation rules for delivery
     */
    public function getDeliveryValidationRules(int $delivery_id = 0): array
    {
        if (empty($delivery_id)) {
            if (empty($this->order)) {
                $response = $this->get();
                if ($response['success']) {
                    $this->order = $response['data']['order'];
                }
            }
            $delivery_id = $this->order['delivery_id'];
        }
        if (empty($delivery_id)) {
            return $this->error('ms3_order_delivery_id_nf');
        }
        $q = $this->modx->newQuery(msDelivery::class);
        $q->where([
            'id' => $delivery_id,
            'active' => 1
        ]);
        $q->select('validation_rules');
        $q->prepare();
        $q->stmt->execute();
        $rules = $q->stmt->fetch(\PDO::FETCH_COLUMN);

        if (empty($rules)) {
            return $this->success('', ['validation_rules' => []]);
        }

        $rules = json_decode($rules, true);

        if (!is_array($rules)) {
            return $this->success('', ['validation_rules' => []]);
        }
        return $this->success('', ['validation_rules' => $rules]);
    }

    /**
     * Get required fields for delivery
     */
    public function getDeliveryRequiresFields(int $delivery_id = 0): array
    {
        if (empty($delivery_id)) {
            if (empty($this->order)) {
                $response = $this->get();
                if ($response['success']) {
                    $this->order = $response['data']['order'];
                }
            }

            $delivery_id = $this->order['delivery_id'];
        }
        $response = $this->getDeliveryValidationRules($delivery_id);
        if (!$response['success']) {
            if (isset($response['message'])) {
                return $this->error($response['message'], ['delivery']);
            } else {
                return $this->error('ms3_order_err_delivery', ['delivery']);
            }
        }
        $requires = array_filter($response['data']['validation_rules'], function ($rules) {
            return in_array('required', array_map('trim', explode("|", $rules)));
        }, ARRAY_FILTER_USE_BOTH);

        return $this->success('', ['requires' => $requires]);
    }

    /**
     * Get new order number
     */
    public function getNewOrderNum(): string
    {
        $format = htmlspecialchars($this->modx->getOption('ms3_order_format_num', null, 'ym'));
        $separator = trim(
            preg_replace(
                "/[^,\/\-]/",
                '',
                $this->modx->getOption('ms3_order_format_num_separator', null, '/')
            )
        );
        $separator = $separator ?: '/';

        $cur = $format ? date($format) : date('ym');

        $count = 0;

        $c = $this->modx->newQuery(msOrder::class);
        $c->where(['num:LIKE' => "{$cur}%"]);
        $c->select('num');
        $c->sortby('id', 'DESC');
        $c->limit(1);
        if ($c->prepare() && $c->stmt->execute()) {
            $num = $c->stmt->fetchColumn();
            [, $count] = explode($separator, $num);
        }
        $count = intval($count) + 1;

        return sprintf('%s%s%d', $cur, $separator, $count);
    }

    /**
     * Checks accordance of payment and delivery
     */
    public function hasPayment(int $delivery, int $payment): bool
    {
        //TODO перенесен из ms2 - не используется, проверить
        $q = $this->modx->newQuery(msPayment::class, ['id' => $payment, 'active' => 1]);
        $q->innerJoin(
            msDeliveryMember::class,
            'Member',
            'Member.payment_id = msPayment.id AND Member.delivery_id = ' . $delivery
        );

        return (bool)$this->modx->getCount(msPayment::class, $q);
    }

    /**
     * Returns id for current user. If user does not exist, registers them and returns id.
     */
    public function getUserId(): int
    {
        $modUser = null;

        $response = $this->ms3->utils->invokeEvent('msOnBeforeGetOrderUser', [
            'controller' => $this,
            'user' => $modUser,
        ]);
        if (!$response['success']) {
            return 0;
        }

        if (!empty($response['data']['user']) && $response['data']['user'] instanceof modUser) {
            $modUser = $response['data']['user'];
        }

        if (!$modUser) {
            $orderResponse = $this->get();
            if (!$orderResponse['success']) {
                return 0;
            }
            $order = $orderResponse['data']['order'];
            $email = $order['address_email'] ?? '';
            $firstName = $order['address_first_name'] ?? '';
            $lastName = $order['address_last_name'] ?? '';
            $fullName = implode(' ', [$firstName, $lastName]);
            $phone = $order['address_phone'] ?? '';
            // TODO подумать как сделать формирование данных более гибким, настраиваемым. Хардкор - плохо
            if (empty($fullName)) {
                $fullName = $email
                    ? substr($email, 0, strpos($email, '@'))
                    : ($phone
                        ? preg_replace('#\D#', '', $phone)
                        : uniqid('user_', false));
            }
            //TODO username должен быть уникальным, имя не годится
            $modResource = $this->modx->newObject(\modResource::class);
            $userName = $modResource->cleanAlias($fullName);
            if (empty($email)) {
                $email = $userName . '@' . $this->modx->getOption('http_host');
            }

            if ($this->modx->user->isAuthenticated()) {
                $profile = $this->modx->user->Profile;
                if (!$profile->get('email')) {
                    $profile->set('email', $email);
                }
                if (!$profile->get('mobilephone')) {
                    $profile->set('mobilephone', $phone);
                }
                $profile->save();
                $modUser = $this->modx->user;
            } else {
                $data = [
                    'email' => $email,
                    'full_name' => $fullName,
                    'user_name' => $userName,
                    'phone' => $phone,
                ];
                $modUser = $this->checkUserExists($data);
                if (!$modUser) {
                    $modUser = $this->createUser($data);
                }
            }
        }

        $response = $this->ms3->utils->invokeEvent('msOnGetOrderUser', [
            'controller' => $this,
            'user' => $modUser,
        ]);
        if (!$response['success']) {
            return 0;
        }

        return $modUser instanceof modUser
            ? $modUser->get('id')
            : 0;
    }

    /**
     * Check if user already exists
     */
    protected function checkUserExists(array $data): ?modUser
    {
        $c = $this->modx->newQuery(modUser::class);
        $c->leftJoin(modUserProfile::class, 'Profile');
        $filter = ['username' => $data['email'], 'OR:Profile.email:=' => $data['email']];
        if (!empty($data['phone'])) {
            $filter['OR:Profile.mobilephone:='] = $data['phone'];
        }
        $c->where($filter);
        $c->select('modUser.id');
        return $this->modx->getObject(modUser::class, $c);
    }

    /**
     * Create new MODX user from order data
     */
    protected function createUser(array $data): ?modUser
    {
        $modUser = $this->modx->newObject(
            modUser::class,
            ['username' => $data['user_name'], 'password' => md5(rand())]
        );
        $profile = $this->modx->newObject(modUserProfile::class, [
            'email' => $data['email'],
            'fullname' => $data['full_name'],
            'mobilephone' => $data['phone'],
        ]);
        $modUser->addOne($profile);
        /** @var modUserSetting $setting */
        $setting = $this->modx->newObject(modUserSetting::class);
        $setting->fromArray([
            'key' => 'cultureKey',
            'area' => 'language',
            'value' => $this->modx->getOption('cultureKey', null, 'en', true),
        ], '', true);
        $modUser->addMany($setting);
        if (!$modUser->save()) {
            return null;
        }

        $groups = $this->modx->getOption('ms3_order_user_groups', null, false);
        if (!$groups) {
            return $modUser;
        }

        $groupRoles = array_map('trim', explode(',', $groups));
        foreach ($groupRoles as $groupRole) {
            $groupRole = explode(':', $groupRole);
            if (count($groupRole) > 1 && !empty($groupRole[1])) {
                if (is_numeric($groupRole[1])) {
                    $roleId = (int)$groupRole[1];
                } else {
                    $roleId = $groupRole[1];
                }
            } else {
                $roleId = null;
            }
            $modUser->joinGroup($groupRole[0], $roleId);
        }

        return $modUser;
    }

    /**
     * Get order array (merged order + address fields)
     */
    protected function getOrder(): array
    {
        if (empty($this->draft)) {
            $output = $this->modx->getFields(msOrder::class);
            $address = $this->modx->getFields(msOrderAddress::class);
            $addressFields = [];
            foreach ($address as $key => $value) {
                $addressFields['address_' . $key] = $value;
            }
            return array_merge($output, $addressFields);
        }
        $address = $this->draft->getOne('Address');
        $output = $this->draft->toArray();
        if (!empty($address)) {
            $addressFields = [];
            foreach ($address->toArray() as $key => $value) {
                $addressFields['address_' . $key] = $value;
            }
            $output = array_merge($output, $addressFields);
        }
        return $output;
    }

    /**
     * Set delivery cost and recalculate total
     */
    protected function setDeliveryCost(float $delivery_cost): void
    {
        $cart_cost = $this->draft->get('cart_cost');
        $cost = $cart_cost + $delivery_cost;

        $this->draft->set('delivery_cost', $delivery_cost);
        $this->draft->set('cost', $cost);
        $this->draft->save();
    }

    /**
     * Update draft order field
     */
    protected function updateDraft(string $key, mixed $value = null): bool
    {
        if (in_array($key, array_keys($this->draft->_fields))) {
            $this->draft->set($key, $value);
            $this->draft->set('updatedon', time());
            $this->draft->save();
        }
        if (in_array($key, array_keys($this->draft->Address->_fields))) {
            $this->draft->Address->set($key, $value);
            $this->draft->Address->save();
            $this->draft->set('updatedon', time());
            $this->draft->save();
        }

        if ($key === 'save_address' && !empty($value)) {
            $properties = $this->draft->get('properties');
            $properties['save_address'] = 1;
            $this->draft->set('properties', $properties);
            $this->draft->set('updatedon', time());
            $this->draft->save();
        }

        if (!empty($this->draft->get('customer_id'))) {
            $customer = $this->draft->getOne('Customer');

            //TODO  получить текущего customer, если есть сохранить ему поля
        }

        return false;
    }

    /**
     * Get customer address fields (exclude specific fields)
     */
    private function getCustomerAddressFields(array $exclude): array
    {
        $fields = $this->modx->getFields(msCustomerAddress::class);
        if (!empty($exclude)) {
            foreach ($exclude as $key) {
                unset($fields[$key]);
            }
        }

        return $fields;
    }

    /**
     * Shorthand for MS3 success method
     */
    protected function success(string $message = '', array $data = [], array $placeholders = []): array
    {
        return $this->ms3->utils->success($message, $data, $placeholders);
    }

    /**
     * Shorthand for MS3 error method
     */
    protected function error(string $message = '', array $data = [], array $placeholders = []): array
    {
        return $this->ms3->utils->error($message, $data, $placeholders);
    }

    /**
     * Shorthand for MS3 invokeEvent method
     */
    protected function invokeEvent(string $eventName, array $params = []): array
    {
        return $this->ms3->utils->invokeEvent($eventName, $params);
    }
}
