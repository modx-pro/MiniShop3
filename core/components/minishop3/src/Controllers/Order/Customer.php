<?php

namespace MiniShop3\Controllers\Order;

use MiniShop3\MiniShop3;
use MODX\Revolution\modUser;
use MODX\Revolution\modUserProfile;
use MODX\Revolution\modUserSetting;
use MODX\Revolution\modX;

class Customer
{
    /** @var modX $modx */
    public $modx;
    /** @var MiniShop3 $ms3 */
    public $ms3;

    public function __construct(MiniShop3 $ms3)
    {
        $this->ms3 = $ms3;
        $this->modx = $ms3->modx;

        $this->modx->lexicon->load('minishop3:default');
    }

    /**
     * Returns id for current customer. If customer is not exists, registers him and returns id.
     *
     * @return integer $id
     */
    public function getId()
    {
        $customer = null;

        $response = $this->ms3->utils->invokeEvent('msOnBeforeGetOrderCustomer', [
            'order' => $this->ms3->order,
            'customer' => $customer,
        ]);
        if (!$response['success']) {
            return $response['message'];
        }

        if (!$customer) {
            $data = $this->ms3->order->get();
            $email = $data['email'] ?? '';
            $receiver = $data['receiver'] ?? '';
            $phone = $data['phone'] ?? '';
            if (empty($receiver)) {
                $receiver = $email
                    ? substr($email, 0, strpos($email, '@'))
                    : ($phone
                        ? preg_replace('#\D#', '', $phone)
                        : uniqid('user_', false));
            }
            if (empty($email)) {
                $email = $receiver . '@' . $this->modx->getOption('http_host');
            }

            if ($this->modx->user->isAuthenticated()) {
                $profile = $this->modx->user->Profile;
                if (!$profile->get('email')) {
                    $profile->set('email', $email);
                    $profile->save();
                }
                $customer = $this->modx->user;
            } else {
                $c = $this->modx->newQuery(modUser::class);
                $c->leftJoin(modUserProfile::class, 'Profile');
                $filter = ['username' => $email, 'OR:Profile.email:=' => $email];
                if (!empty($phone)) {
                    $filter['OR:Profile.mobilephone:='] = $phone;
                }
                $c->where($filter);
                $c->select('modUser.id');
                if (!$customer = $this->modx->getObject(modUser::class, $c)) {
                    $customer = $this->modx->newObject(modUser::class, ['username' => $email, 'password' => md5(rand())]);
                    $profile = $this->modx->newObject(modUserProfile::class, [
                        'email' => $email,
                        'fullname' => $receiver,
                        'mobilephone' => $phone
                    ]);
                    $customer->addOne($profile);
                    /** @var modUserSetting $setting */
                    $setting = $this->modx->newObject(modUserSetting::class);
                    $setting->fromArray([
                        'key' => 'cultureKey',
                        'area' => 'language',
                        'value' => $this->modx->getOption('cultureKey', null, 'en', true),
                    ], '', true);
                    $customer->addMany($setting);
                    if (!$customer->save()) {
                        $customer = null;
                    } elseif ($groups = $this->modx->getOption('ms3_order_user_groups', null, false)) {
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
                            $customer->joinGroup($groupRole[0], $roleId);
                        }
                    }
                }
            }
        }

        $response = $this->ms3->utils->invokeEvent('msOnGetOrderCustomer', [
            'order' => $this->ms3->order,
            'customer' => $customer,
        ]);
        if (!$response['success']) {
            return $response['message'];
        }

        return $customer instanceof modUser
            ? $customer->get('id')
            : 0;
    }
}
