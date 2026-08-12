<?php

if (!class_exists('msManagerController')) {
    require_once dirname(__FILE__, 2) . '/manager.class.php';
}

class MiniShop3MgrOrdersManagerController extends msManagerController
{
    /**
     * @return string
     */
    public function getPageTitle()
    {
        return $this->modx->lexicon('ms3_orders') . ' | MiniShop3';
    }

    /**
     * @return array
     */
    public function getLanguageTopics()
    {
        return ['minishop3:default', 'minishop3:order', 'minishop3:manager', 'minishop3:vue'];
    }

    /**
     *
     */
    public function loadCustomCssJs()
    {
        $config = $this->ms3->config;
        $config['order_show_drafts'] = (bool) $this->modx->getOption('ms3_order_show_drafts', null, false);

        // Config before Vue modules so mount sees ms3.config (#526).
        $this->addVueConfig($config);

        $this->addCss($this->ms3->config['assetsUrl'] . 'css/mgr/vue-dist/primeicons.min.css');
        $this->addCss($this->ms3->config['assetsUrl'] . 'css/mgr/vue-dist/orders.min.css');
        $this->addVueModule($this->ms3->config['assetsUrl'] . 'js/mgr/vue-dist/orders.min.js');

        $this->modx->invokeEvent('msOnManagerCustomCssJs', [
            'controller' => $this,
            'page' => 'orders',
        ]);
    }

    /**
     * @param array $scriptProperties
     * @return mixed
     */
    public function process(array $scriptProperties = [])
    {
        return [];
    }

    /**
     * @return string
     */
    public function getTemplateFile()
    {
        return dirname(__FILE__, 3) . '/templates/default/orders.tpl';
    }
}
