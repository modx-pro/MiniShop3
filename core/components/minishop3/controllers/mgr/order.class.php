<?php

if (!class_exists('msManagerController')) {
    require_once dirname(__FILE__, 2) . '/manager.class.php';
}

class MiniShop3MgrOrderManagerController extends msManagerController
{
    /**
     * @return string
     */
    public function getPageTitle()
    {
        $id = (int)($_GET['id'] ?? 0);
        return $this->modx->lexicon('ms3_order') . ' #' . $id . ' | MiniShop3';
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
        $this->addCss($this->ms3->config['cssUrl'] . 'mgr/bootstrap.buttons.css');
        $this->addCss($this->ms3->config['cssUrl'] . 'mgr/main.css');
        $this->addJavascript($this->ms3->config['jsUrl'] . 'mgr/minishop3.js');

        $this->addJavascript($this->ms3->config['jsUrl'] . 'mgr/orders/order.wrapper.js');

        $orderId = (int)($_GET['id'] ?? 0);

        $config = $this->ms3->config;
        $config['order_id'] = $orderId;

        $this->addHtml('<script>Object.assign(ms3.config, ' . json_encode($config) . ');</script>');

        $this->addHtml(
            '<link rel="stylesheet" href="' . $this->ms3->config['assetsUrl'] . 'css/mgr/vue-dist/useLexicon.min.css">
        <link rel="stylesheet" href="' . $this->ms3->config['assetsUrl'] . 'css/mgr/vue-dist/order.min.css">
        <script type="module" src="' . $this->ms3->config['assetsUrl'] . 'js/mgr/vue-dist/order.min.js"></script>
        <script>
            Ext.onReady(function() {
                MODx.add({xtype: "ms3-order-vue-wrapper"});
            });
        </script>'
        );

        $this->modx->invokeEvent('msOnManagerCustomCssJs', [
            'controller' => $this,
            'page' => 'order',
        ]);
    }
}
