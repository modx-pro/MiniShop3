<?php

use MODX\Revolution\modSystemSetting;

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
        return array('minishop3:default', 'minishop3:order', 'minishop3:manager', 'minishop3:vue');
    }


    /**
     *
     */
    public function loadCustomCssJs()
    {
        $this->addCss($this->ms3->config['cssUrl'] . 'mgr/bootstrap.buttons.css');
        $this->addCss($this->ms3->config['cssUrl'] . 'mgr/main.css');
        $this->addJavascript($this->ms3->config['jsUrl'] . 'mgr/minishop3.js');

        $this->addJavascript($this->ms3->config['jsUrl'] . 'mgr/orders/orders.wrapper.js');

        $config = $this->ms3->config;
        $this->addHtml('<script>Object.assign(ms3.config, ' . json_encode($config) . ');</script>');

        $this->addCss($this->ms3->config['assetsUrl'] . 'css/mgr/vue-dist/orders.min.css');
        // Vue module with VueTools dependency check
        $this->addVueModule($this->ms3->config['assetsUrl'] . 'js/mgr/vue-dist/orders.min.js');

        $this->addHtml('
        <script>
            Ext.onReady(function() {
                MODx.add({xtype: "ms3-orders-vue-wrapper"});
            });
        </script>');

        $this->modx->invokeEvent('msOnManagerCustomCssJs', array(
            'controller' => $this,
            'page' => 'orders',
        ));
    }
}
