<?php

namespace MiniShop3\Controllers\Order;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msDelivery;
use MiniShop3\Model\msDeliveryMember;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msPayment;
use MODX\Revolution\modUser;
use MODX\Revolution\modUserProfile;
use MODX\Revolution\modUserSetting;
use MODX\Revolution\modX;
use MiniShop3\Controllers\Storage\DB\DBOrder;

class Order implements OrderInterface
{
    /** @var modX $modx */
    public $modx;
    /** @var MiniShop3 $ms3 */
    public $ms3;
    /** @var array $config */
    public $config = [];
    protected $storage;

    /**
     * Order constructor.
     *
     * @param MiniShop3 $ms3
     * @param array $config
     */
    public function __construct(MiniShop3 $ms3, array $config = [])
    {
        $this->ms3 = $ms3;
        $this->modx = $ms3->modx;

        $this->config = array_merge([], $config);

        $this->modx->lexicon->load('minishop3:cart');

        $this->storage = new DBOrder($this->modx, $this->ms3);
    }

    public function initialize(string $token = '', array $config = []): bool
    {
        return $this->storage->initialize($token, $this->config);
    }

    public function get(): array
    {
        return $this->storage->get();
    }

    /**
     * @return array
     */
    public function getCartCost(): array
    {
        return $this->storage->getCartCost();
    }

    /**
     * @param bool $with_cart
     * @param bool $only_cost
     *
     * @return array
     */
    public function getCost(bool $only_cost = false): array
    {
        return $this->storage->getCost($only_cost);
    }

    /**
     * @param string $key
     * @param string $value
     *
     * @return array
     */
    public function add(string $key, mixed $value = null): array
    {
        return $this->storage->add($key, $value);
    }

    /**
     * @param string $value
     *
     * @return array
     */
    public function setCustomerAddress(string $value = null): array
    {
        return $this->storage->setCustomerAddress($value);
    }

    /**
     * @param string $key
     * @param string $value
     *
     * @return bool|mixed|string
     */
    public function validate(string $key, $value): mixed
    {
        return $this->storage->validate($key, $value);
    }

    /**
     * @param string $key
     *
     * @return array|bool|string
     */
    public function remove($key): bool
    {
        return $this->storage->remove($key);
    }

    /**
     * @param array $order
     *
     * @return array
     */
    public function set(array $order): array
    {
        return $this->storage->set($order);
    }

    /**
     * Checks accordance of payment and delivery
     *
     * @param $delivery
     * @param $payment
     *
     * @return bool
     */
    public function hasPayment($delivery, $payment)
    {
        //TODO перенесен из ms2 - не используется, проверить
        $this->modx->log(1, 'OrderController::hasPayment');
        $q = $this->modx->newQuery(msPayment::class, ['id' => $payment, 'active' => 1]);
        $q->innerJoin(
            msDeliveryMember::class,
            'Member',
            'Member.payment_id = msPayment.id AND Member.delivery_id = ' . $delivery
        );

        return (bool)$this->modx->getCount(msPayment::class, $q);
    }

    /**
     * Returns required fields for delivery
     *
     * @param int $delivery_id
     *
     * @return array
     */
    public function getDeliveryRequiresFields(int $delivery_id = 0): array
    {
        return $this->storage->getDeliveryRequiresFields($delivery_id);
    }

    public function submit(array $data = []): array
    {
        return $this->storage->submit();
    }

    public function clean(): array
    {
        return $this->storage->clean();
    }

    /**
     * Returns number for new order
     * @return string
     */
    public function getNewOrderNum(): string
    {
        return $this->storage->getNewOrderNum();
    }

    /**
     * Returns id for current user. If user is not exists, registers him and returns id.
     *
     * @return integer $id
     */
    public function getUserId(): int
    {
        $modUser = null;

        $response = $this->ms3->utils->invokeEvent('msOnBeforeGetOrderUser', [
            'controller' => $this->ms3->order,
            'user' => $modUser,
        ]);
        if (!$response['success']) {
            return $response['message'];
        }

        if (!empty($response['data']['user']) && $response['data']['user'] instanceof modUser) {
            $modUser = $response['data']['user'];
        }

        if (!$modUser) {
            $orderResponse = $this->ms3->order->get();
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
            'controller' => $this->ms3->order,
            'user' => $modUser,
        ]);
        if (!$response['success']) {
            return $response['message'];
        }

        return $modUser instanceof modUser
            ? $modUser->get('id')
            : 0;
    }

    protected function checkUserExists($data)
    {
        $c = $this->modx->newQuery(modUser::class);
        $c->leftJoin(modUserProfile::class, 'Profile');
        $filter = ['username' => $data['email'], 'OR:Profile.email:=' => $data['email']];
        if (!empty($phone)) {
            $filter['OR:Profile.mobilephone:='] = $data['phone'];
        }
        $c->where($filter);
        $c->select('modUser.id');
        return $this->modx->getObject(modUser::class, $c);
    }

    protected function createUser(array $data): modUser|null
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
     * Shorthand for ms3 error method
     *
     * @param string $message
     * @param array $data
     * @param array $placeholders
     *
     * @return array|string
     */
    protected function error(string $message = '', array $data = [], array $placeholders = []): array|string
    {
        return $this->ms3->utils->error($message, $data, $placeholders);
    }

    /**
     * Shorthand for ms3 success method
     *
     * @param string $message
     * @param array $data
     * @param array $placeholders
     *
     * @return array|string
     */
    protected function success(string $message = '', array $data = [], array $placeholders = []): array|string
    {
        return $this->ms3->utils->success($message, $data, $placeholders);
    }

    /**
     * Shorthand for MS3 invokeEvent method
     *
     * @param string $eventName
     * @param array $params
     *
     * @return array|string
     */
    protected function invokeEvent(string $eventName, array $params = []): array|string
    {
        return $this->ms3->utils->invokeEvent($eventName, $params);
    }
}
