<?php

namespace MiniShop3\Processors\Settings\Payment\Deliveries;

use MiniShop3\Model\msDelivery;
use MiniShop3\Model\msDeliveryMember;
use MODX\Revolution\Processors\Model\GetListProcessor;
use xPDO\Om\xPDOObject;
use xPDO\Om\xPDOQuery;

class GetList extends GetListProcessor
{
    public $classKey = msDelivery::class;
    public $defaultSortField = 'position';
    public $sortAlias = 'msDelivery';
    public $defaultSortDirection = 'asc';
    public $permission = 'mssetting_list';


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
     * @param xPDOQuery $c
     *
     * @return xPDOQuery
     */
    public function prepareQueryBeforeCount(xPDOQuery $c)
    {
        $c->leftJoin(
            msDeliveryMember::class,
            'Payments',
            "Payments.delivery_id = msDelivery.id AND Payments.payment_id = {$this->getProperty('payment')}"
        );
        $c->select($this->modx->getSelectColumns($this->classKey, 'msDelivery'));
        $c->select('(Payments.delivery_id is not null) as active');
        $c->groupby($this->sortAlias . '.id');

        return $c;
    }


    /**
     * @param xPDOObject $object
     *
     * @return array
     */
    public function prepareRow(xPDOObject $object)
    {
        $data = $object->toArray();
        $data['actions'] = [];

        if (empty($data['active'])) {
            $data['actions'][] = [
                'cls' => 'fw-900',
                'icon' => 'icon icon-power-off action-green',
                'title' => $this->modx->lexicon('ms3_menu_enable'),
                'multiple' => $this->modx->lexicon('ms3_menu_enable'),
                'action' => 'enableDelivery',
                'button' => true,
                'menu' => true,
            ];
        } else {
            $data['actions'][] = [
                'cls' => 'fw-900',
                'icon' => 'icon icon-power-off action-gray',
                'title' => $this->modx->lexicon('ms3_menu_disable'),
                'multiple' => $this->modx->lexicon('ms3_menu_disable'),
                'action' => 'disableDelivery',
                'button' => true,
                'menu' => true,
            ];
        }

        return $data;
    }
}
