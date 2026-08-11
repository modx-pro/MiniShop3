<?php

namespace MiniShop3\Processors\Settings\Vendor;

use MiniShop3\Model\msVendor;
use MiniShop3\Services\Settings\SettingsComboListService;
use MODX\Revolution\modResource;
use MODX\Revolution\Processors\Model\GetListProcessor;
use xPDO\Om\xPDOObject;
use xPDO\Om\xPDOQuery;

class GetList extends GetListProcessor
{
    public $classKey = msVendor::class;
    public $objectType = 'msVendor';
    public $defaultSortField = 'id';
    public $defaultSortDirection = 'asc';
    public $permission = 'mssetting_list';

    /**
     * Combo mode delegates to SettingsComboListService (same source as REST /references/vendors).
     *
     * @return array|string
     */
    public function process()
    {
        if ($this->getProperty('combo')) {
            $includeId = SettingsComboListService::resolvePinnedIncludeId(
                (int) $this->getProperty('id'),
                $this->getProperty('limit')
            );
            /** @var SettingsComboListService $comboList */
            $comboList = $this->modx->services->get('ms3_settings_combo_list');
            $rows = $comboList->listVendorsForCombo(
                $includeId,
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
        $c->leftJoin(modResource::class, 'Resource');
        $c->select($this->modx->getSelectColumns($this->classKey, 'msVendor'));
        $c->select('Resource.pagetitle');

        $query = trim($this->getProperty('query'));
        if (!empty($query)) {
            $c->where([
                'name:LIKE' => "%{$query}%",
                'OR:description:LIKE' => "%{$query}%",
                'OR:country:LIKE' => "%{$query}%",
                'OR:email:LIKE' => "%{$query}%",
                'OR:address:LIKE' => "%{$query}%",
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
        if (!$data['resource_id']) {
            $data['resource_id'] = null;
        }
        $data['actions'] = [];

        $data['actions'][] = [
            'cls' => '',
            'icon' => 'icon icon-edit',
            'title' => $this->modx->lexicon('ms3_menu_update'),
            'action' => 'updateVendor',
            'button' => true,
            'menu' => true,
        ];

        $data['actions'][] = [
            'cls' => [
                'menu' => 'red',
                'button' => 'red',
            ],
            'icon' => 'icon icon-trash-o',
            'title' => $this->modx->lexicon('ms3_menu_remove'),
            'multiple' => $this->modx->lexicon('ms3_menu_remove_multiple'),
            'action' => 'removeVendor',
            'button' => true,
            'menu' => true,
        ];

        return $data;
    }
}
