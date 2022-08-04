<?php

namespace MiniShop3\Controllers\Order;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msDelivery;
use MiniShop3\Model\msDeliveryMember;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msPayment;
use MODX\Revolution\modX;

class Order
{
    /** @var modX $modx */
    public $modx;
    /** @var MiniShop3 $ms3 */
    public $ms3;
    /** @var array $config */
    public $config = [];
    protected $storage = 'session';
    protected $storageHandler;

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

        $this->storage = $this->modx->getOption('ms_tmp_storage', null, 'session');
        $this->storageInit();

        $this->config = array_merge([], $config);

        $this->modx->lexicon->load('minishop:cart');
    }

    /**
     * @param string $ctx
     *
     * @return bool
     */
    public function initialize($ctx = 'web')
    {
        $this->storageHandler->setContext($ctx);
        return true;
    }

    /**
     * @param string $key
     * @param string $value
     *
     * @return bool|mixed|string
     */
    public function validate($key, $value)
    {
        if ($key != 'comment') {
            $value = preg_replace('/\s+/', ' ', trim($value));
        }

        $eventParams = [
            'key' => $key,
            'value' => $value,
            'order' => $this,
        ];
        $response = $this->invokeEvent('msOnBeforeValidateOrderValue', $eventParams);
        $value = $response['data']['value'];

        $old_value = $this->order[$key] ?? '';
        switch ($key) {
            case 'email':
                $value = preg_match('/^[^@а-яА-Я]+@[^@а-яА-Я]+(?<!\.)\.[^\.а-яА-Я]{2,}$/m', $value)
                    ? $value
                    : false;
                break;
            case 'receiver':
                // Transforms string from "nikolaj -  coster--Waldau jr." to "Nikolaj Coster-Waldau Jr."
                $tmp = preg_replace(
                    ['/[^-a-zа-яёґєіїўäëïöüçàéèîôûäüöÜÖÄÁČĎĚÍŇÓŘŠŤÚŮÝŽ\s\.\'’ʼ`"]/iu', '/\s+/', '/\-+/', '/\.+/', '/[\'’ʼ`"]/iu', '/\'+/'],
                    ['', ' ', '-', '.', '\'', '\''],
                    $value
                );
                $tmp = preg_split('/\s/', $tmp, -1, PREG_SPLIT_NO_EMPTY);
                $tmp = array_map([$this, 'ucfirst'], $tmp);
                $value = preg_replace('/\s+/', ' ', implode(' ', $tmp));
                if (empty($value)) {
                    $value = false;
                }
                break;
            case 'phone':
                $value = substr(preg_replace('/[^-+()0-9]/u', '', $value), 0, 16);
                break;
            case 'delivery':
                /** @var msDelivery $delivery */
                $delivery = $this->modx->getObject(msDelivery::class, ['id' => $value, 'active' => 1]);
                if (!$delivery) {
                    $value = $old_value;
                    break;
                }
                if (!empty($this->order['payment'])) {
                    if (!$this->hasPayment($value, $this->order['payment'])) {
                        $this->order['payment'] = $delivery->getFirstPayment();
                    };
                }
                break;
            case 'payment':
                if (!empty($this->order['delivery'])) {
                    $value = $this->hasPayment($this->order['delivery'], $value)
                        ? $value
                        : $old_value;
                }
                break;
            case 'index':
                $value = substr(preg_replace('/[^-\da-z]/iu', '', $value), 0, 10);
                break;
        }

        $eventParams = [
            'key' => $key,
            'value' => $value,
            'order' => $this,
        ];
        $response = $this->invokeEvent('msOnValidateOrderValue', $eventParams);
        return $response['data']['value'];
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
     * @param $id
     *
     * @return array|string
     */
    public function getDeliveryRequiresFields($id = 0)
    {
        if (empty($id)) {
            $id = $this->order['delivery'];
        }
        /** @var msDelivery $delivery */
        $delivery = $this->modx->getObject(msDelivery::class, ['id' => $id, 'active' => 1]);
        if (!$delivery) {
            return $this->error('ms_order_err_delivery', ['delivery']);
        }
        $requires = $delivery->get('requires');
        $requires = empty($requires)
            ? []
            : array_map('trim', explode(',', $requires));

        return $this->success('', ['requires' => $requires]);
    }

    /**
     * Set controller for Order
     */
    protected function storageInit()
    {
        switch ($this->storage) {
            case 'session':
                require_once dirname(__FILE__) . '/storage/session/ordersessionhandler.class.php';
                $this->storageHandler = new OrderSessionHandler($this->modx, $this->ms3);
                break;
            case 'db':
                require_once dirname(__FILE__) . '/storage/db/orderdbhandler.class.php';
                $this->storageHandler = new OrderDBHandler($this->modx, $this->ms3);
                break;
        }
    }

    /**
     * Return current number of order
     *
     * @return string
     */
    protected function getNum()
    {
        $format = htmlspecialchars($this->modx->getOption('ms_order_format_num', null, '%y%m'));
        $separator = trim(
            preg_replace(
                "/[^,\/\-]/",
                '',
                $this->modx->getOption('ms_order_format_num_separator', null, '/')
            )
        );
        $separator = $separator ?: '/';

        $cur = $format ? strftime($format) : date('ym');

        $count = $num = 0;

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

    /**
     * Ucfirst function with support of cyrillic
     *
     * @param string $str
     *
     * @return string
     */
    protected function ucfirst($str = '')
    {
        if (strpos($str, '-') !== false) {
            $tmp = array_map([$this, __FUNCTION__], explode('-', $str));

            return implode('-', $tmp);
        }

        if (function_exists('mb_substr') && preg_match('/[а-я-яёґєіїўäëïöüçàéèîôû]/iu', $str)) {
            $tmp = mb_strtolower($str, 'utf-8');
            $str = mb_substr(mb_strtoupper($tmp, 'utf-8'), 0, 1, 'utf-8') .
                mb_substr($tmp, 1, mb_strlen($tmp) - 1, 'utf-8');
        } else {
            $str = ucfirst(strtolower($str));
        }

        return $str;
    }

    /**
     * Shorthand for MS3 invokeEvent method
     *
     * @param string $eventName
     * @param array $params
     *
     * @return array|string
     */
    protected function invokeEvent(string $eventName, array $params = [])
    {
        return $this->ms3->utils->invokeEvent($eventName, $params);
    }
}
