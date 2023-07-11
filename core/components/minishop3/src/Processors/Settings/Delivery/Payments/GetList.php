<?php

namespace MiniShop3\Processors\Settings\Delivery\Payments;

use MiniShop3\Model\msDeliveryMember;
use MiniShop3\Model\msPayment;
use MODX\Revolution\Processors\Model\GetListProcessor;
use xPDO\Om\xPDOObject;
use xPDO\Om\xPDOQuery;

class GetList extends GetListProcessor
{
    public $classKey = msPayment::class;
    public $defaultSortField = 'position';
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
            'Deliveries',
            "Deliveries.payment_id = msPayment.id AND Deliveries.delivery_id = {$this->getProperty('delivery')}"
        );
        $c->select($this->modx->getSelectColumns($this->classKey, 'msPayment'));
        $c->select('(Deliveries.payment_id is not null) as active');
        $c->groupby($this->classKey . '.id');

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
                'cls' => '',
                'icon' => 'icon icon-power-off action-green',
                'title' => $this->modx->lexicon('ms_menu_enable'),
                'multiple' => $this->modx->lexicon('ms_menu_enable'),
                'action' => 'enablePayment',
                'button' => true,
                'menu' => true,
            ];
        } else {
            $data['actions'][] = [
                'cls' => '',
                'icon' => 'icon icon-power-off action-gray',
                'title' => $this->modx->lexicon('ms_menu_disable'),
                'multiple' => $this->modx->lexicon('ms_menu_disable'),
                'action' => 'disablePayment',
                'button' => true,
                'menu' => true,
            ];
        }

        return $data;
    }
}
