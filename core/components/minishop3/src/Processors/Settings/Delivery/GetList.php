<?php

namespace MiniShop3\Processors\Settings\Delivery;

use MiniShop3\Model\msDelivery;
use MiniShop3\Model\msDeliveryMember;
use MiniShop3\Services\Settings\SettingsComboListService;
use MODX\Revolution\Processors\Model\GetListProcessor;
use xPDO\Om\xPDOObject;
use xPDO\Om\xPDOQuery;

class GetList extends GetListProcessor
{
    public $classKey = msDelivery::class;
    public $defaultSortField = 'position';
    public $defaultSortDirection = 'asc';
    public $permission = 'mssetting_list';

    /**
     * Combo mode delegates to SettingsComboListService (same source as REST dropdowns).
     *
     * @return array|string
     */
    public function process()
    {
        if ($this->getProperty('combo')) {
            /** @var SettingsComboListService $comboList */
            $comboList = $this->modx->services->get('ms3_settings_combo_list');
            $rows = $comboList->listActiveDeliveriesForCombo(
                (int) $this->getProperty('id'),
                trim((string) $this->getProperty('query', ''))
            );

            return $this->outputArray($rows, count($rows));
        }

        return parent::process();
    }

    /**
     * @param xPDOQuery $c
     *
     * @return xPDOQuery
     */
    public function prepareQueryBeforeCount(xPDOQuery $c)
    {
        $c->leftJoin(msDeliveryMember::class, 'Payments');
        $c->groupby('msDelivery' . '.id');
        $c->select($this->modx->getSelectColumns($this->classKey, 'msDelivery'));
        $c->select('COUNT(Payments.payment_id) as payments');
        if ($query = trim($this->getProperty('query'))) {
            $c->where([
                'name:LIKE' => "%{$query}%",
                'OR:description:LIKE' => "%{$query}%",
                'OR:class:LIKE' => "%{$query}%",
            ]);
        }

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

        $data['actions'][] = [
            'cls' => '',
            'icon' => 'icon icon-edit',
            'title' => $this->modx->lexicon('ms3_menu_update'),
            'action' => 'updateDelivery',
            'button' => true,
            'menu' => true,
        ];
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
        $data['actions'][] = [
            'cls' => [
                'menu' => 'red',
                'button' => 'red',
            ],
            'icon' => 'icon icon-trash-o',
            'title' => $this->modx->lexicon('ms3_menu_remove'),
            'multiple' => $this->modx->lexicon('ms3_menu_remove_multiple'),
            'action' => 'removeDelivery',
            'button' => true,
            'menu' => true,
        ];

        return $data;
    }
}
