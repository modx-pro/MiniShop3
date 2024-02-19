<?php

namespace MiniShop3\Controllers\Storage\DB;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderAddress;
use MODX\Revolution\modX;

class DBOrder extends DBStorage
{
    private $config;
    private $order;
    /**
     * @param string $ctx
     *
     * @return bool
     */
    public function initialize($token, $config = [])
    {
        if (empty($token)) {
            return false;
        }
        $this->token = $token;
        $this->config = $config;
        return true;
    }

    public function get()
    {
        if (empty($this->token)) {
            return $this->error('ms3_err_token');
        }
        $this->initDraft();

        //TODO Добавить событие?
//        $response = $this->invokeEvent('msOnBeforeGetOrder', [
//            'draft' => $this->draft,
//            'cart' => $this,
//        ]);
//        if (!($response['success'])) {
//            return $this->error($response['message']);
//        }
        $this->order = $this->getOrder();

        //TODO Добавить событие?
//        $response = $this->invokeEvent('msOnGetOrder, [
//            'draft' => $this->draft,
//            'data' => $this->order,
//            'cart' => $this
//        ]);
//
//        if (!$response['success']) {
//            return $this->error($response['message']);
//        }
//
//        $this->cart = $response['data']['data'];

        $data = [];

        $data['order'] = $this->order;
        return $this->success(
            'ms3_cart_get_success',
            $data
        );
    }

    private function getOrder()
    {
        $output = $this->draft->toArray();
        //TODO Добавить адрес, может покупателя, все смержить
        return $output;
    }
}
