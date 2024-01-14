<?php

namespace MiniShop3\Controllers\Customer;

$autoload = dirname(__FILE__, 4) . '/vendor/autoload.php';

require_once($autoload);

use MiniShop3\MiniShop3;
use MiniShop3\Model\msCustomer;
use MODX\Revolution\modUser;
use MODX\Revolution\modUserProfile;
use MODX\Revolution\modUserSetting;
use MODX\Revolution\modX;

use Rakit\Validation\Validator;

class Customer
{
    /** @var modX $modx */
    public $modx;
    /** @var MiniShop3 $ms3 */
    public $ms3;
    /** @var array $config */
    public $config = [];
    protected $token = '';

    /**
     * Cart constructor.
     *
     * @param MiniShop3 $ms3
     * @param array $config
     */
    public function __construct(MiniShop3 $ms3, array $config = [])
    {
        $this->ms3 = $ms3;
        $this->modx = $ms3->modx;

        $this->config = array_merge([

        ], $config);
        $this->modx->lexicon->load('minishop3:customer');
    }

    public function initialize($token = '')
    {
        if (empty($token)) {
            return false;
        }
        $this->token = $token;
        return true;
    }

    public function generateToken()
    {
        $tokenName = $this->modx->getOption('ms3_token_name', null, 'ms3_token');
        $token = md5(rand() . $tokenName);
        $_SESSION['ms3']['customer_token'] = $token;
        $lifetime = $this->modx->getOption('session_gc_maxlifetime', null, '604800');
        return $this->success('', compact('token', 'lifetime'));
    }

    public function get()
    {
    }

    public function set()
    {
    }

    public function add($key, $value)
    {
        if (empty($this->token)) {
            return $this->error('ms3_err_token');
        }

        if (empty($key)) {
            return $this->error('ms3_customer_key_empty');
        }

        //TODO Реализовать событие ПередДобавлениемПоля

        // $response = $$this->ms3->utils->invokeEvent('msOnBeforeAddToOrder', [
        //            'key' => $key,
        //            'value' => $value,
        //            'order' => $this,
        //        ]);
        //        if (!$response['success']) {
        //            return $this->error($response['message']);
        //        }
        //        $value = $response['data']['value'];

        $response = $this->validate($key, $value);
        if (is_array($response)) {
            return $this->error($response[$key]);
        }

        $validated = $response;

        $msCustomer = $this->modx->getObject(msCustomer::class, [
            'token' => $this->token
        ]);
        if ($msCustomer) {
            $msCustomer->set($key, $validated);
        } else {
            $userId = 0;

            // TODO как правильно определить текущего системного пользователя, если тот авторизован?
            if ($this->modx->user->hasSessionContext($this->ms3->config['ctx'])) {
                $userId = $this->modx->user->get('id');
            }
            $msCustomer = $this->modx->newObject(msCustomer::class, [
                'token' => $this->token,
                $key => $validated,
                'user_id' => $userId
            ]);
        }
        $msCustomer->save();

        //TODO Реализовать событие ПослеДобавлениемПоля

        //$response = $$this->ms3->utils->invokeEvent('msOnAddToCustomer', [
        //                    'key' => $key,
        //                    'value' => $validated,
        //                    'customer' => $this,
//                                'mode' => 'new'
        //                ]);
        //                if (!$response['success']) {
        //                    return $this->error($response['message']);
        //                }
        //                $validated = $response['data']['value'];

        return ($validated === false)
            ? $this->error('', [$key => $value])
            : $this->success('', [$key => $validated]);
    }

    public function validate($key, $value)
    {
        $validator = new Validator();

        $validationRules = [
            'first_name' => 'required|min:2',
            'last_name' => 'required|min:3',
            'email' => 'required|email',
            'phone' => 'required|min:10'
        ];

        $messages = [
            'required' => 'Обязательно для заполнения',
            'email' => 'Не является email',
            'min' => 'Минимум :min символов',
        ];

        $validation = $validator->validate(
            [$key => $value],
            [$key => $validationRules[$key]],
            $messages
        );

        $validation->validate();

        if ($validation->fails()) {
            // handling errors
            $errors = $validation->errors();
            return $errors->firstOfAll();
        } else {
            return $value;
        }

        // TODO валидировать наличие $key в модели msCustomer + разрешение на запись
        //TODO реализовать событие ДоВалидации

        // $eventParams = [
        //            'key' => $key,
        //            'value' => $value,
        //            'customer' => $this,
        //        ];
        //        $response = $this->invokeEvent('msOnBeforeValidateCustomerValue', $eventParams);
        //        $value = $response['data']['value'];

        // TODO валидировать $value

        // TODO реализовать событие ПослеВалидации

        //$eventParams = [
        //            'key' => $key,
        //            'value' => $value,
        //            'customer' => $this,
        //        ];
        //        $response = $this->invokeEvent('msOnValidateCustomerValue', $eventParams);
        //        return $response['data']['value'];

        return $value;
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
                    $customer = $this->modx->newObject(modUser::class, ['username' => $email, 'password' => md5(rand())]
                    );
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

    /**
     * Shorthand for ms3 error method
     *
     * @param string $message
     * @param array $data
     * @param array $placeholders
     *
     * @return array|string
     */
    protected function error($message = '', $data = [], $placeholders = [])
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
    protected function success($message = '', $data = [], $placeholders = [])
    {
        return $this->ms3->utils->success($message, $data, $placeholders);
    }
}
