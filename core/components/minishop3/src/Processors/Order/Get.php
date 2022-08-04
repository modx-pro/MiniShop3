<?php

namespace MiniShop3\Processors\Order;

use MiniShop3\Model\msOrder;
use MODX\Revolution\Processors\Model\GetProcessor;

class Get extends GetProcessor
{
    public $classKey = msOrder::class;
    public $languageTopics = ['minishop:default'];
    public $permission = 'msorder_view';


    /**
     * @return bool|null|string
     */
    public function initialize()
    {
        if (!$this->modx->hasPermission($this->permission)) {
            return $this->modx->lexicon('access_denied');
        }

        return parent::initialize();
    }


    /**
     * @return array|string
     */
    public function cleanup()
    {
        $ms3 = $this->modx->services->get('ms3');
        $array = $this->object->toArray();
        $address = $this->object->getOne('Address');
        if ($address) {
            $array = array_merge($array, $address->toArray('addr_'));
        }
        if ($profile = $this->object->getOne('UserProfile')) {
            $array['fullname'] = $profile->get('fullname');
        } else {
            $array['fullname'] = $this->modx->lexicon('no');
        }

        $array['createdon'] = $ms3->format->date($array['createdon']);
        $array['updatedon'] = $ms3->format->date($array['updatedon']);

        return $this->success('', $array);
    }
}
