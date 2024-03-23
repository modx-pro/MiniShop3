<?php

namespace MiniShop3\Processors\Utilities\ExtraField;

use MiniShop3\Model\msOrder;
use MiniShop3\Model\msVendor;
use MiniShop3\Model\msProductData;
use MODX\Revolution\Processors\Processor;

class GetClassNodes extends Processor
{
    public $languageTopics = ['minishop3:default', 'minishop3:prooduct'];
    public $permission = 'mssetting_view';

    /**
     * {@inheritDoc}
     * @return boolean
     */
    public function checkPermissions()
    {
        return $this->modx->hasPermission($this->permission);
    }

    /**
     * {@inheritDoc}
     * @return array
     */
    public function getLanguageTopics()
    {
        return $this->languageTopics;
    }

    /**
     * {@inheritDoc}
     * @return mixed
     */
    public function initialize()
    {
        $this->setDefaultProperties([
            'id' => 0,
            'sort' => 'name',
            'dir' => 'ASC',
            'showAnonymous' => true,
        ]);

        return true;
    }

    /**
     * {@inheritDoc}
     * @return mixed
     */
    public function process()
    {
        $classes = [];
        if ($this->getProperty('id') == 'root') {
            $classes = $this->getClasses();
        }

        $list = [];
        /** @var modUserGroup $group */
        foreach ($classes as $class => $props) {
            $list[] = $this->prepareClass($class, $props);
        }

        return $this->toJSON($list);
    }

    /**
     * Get the User Groups within the filter
     * @return array
     */
    public function getClasses()
    {
        return [
            msProductData::class => [
                //'text' => $this->modx->lexicon('ms3_product') . ' (msProductData)',
                'iconCls' => $this->modx->getOption('mgr_tree_icon_msproduct', null, 'icon icon-tag')
            ],
            msVendor::class => [
                'iconCls' => 'icon icon-industry'
            ],
            //msOrder::class => [
            //],
        ];
    }

    /**
     * Prepare a User Group for listing
     * @param modUserGroup $group
     * @return array
     */
    public function prepareClass(string $classKey, array $props)
    {
        $cls = '';

        $path = explode('\\', $classKey);
        $shortClass = array_pop($path);

        $itemArray = array_merge([
            'text' => htmlentities($shortClass),
            'id' => 'n_' . str_replace('\\', '__', $classKey),
            'hasChildren' => false,
            'expanded' => false,
            'childCount' => 0,
            'type' => $classKey,
            'qtip' => $classKey,
            'cls' => $cls,
            'allowDrop' => false,
            'iconCls' => 'icon icon-dice-d6',
        ], $props);

        return $itemArray;
    }
}
