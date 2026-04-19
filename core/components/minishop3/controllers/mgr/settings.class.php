<?php

if (!class_exists('msManagerController')) {
    require_once dirname(__FILE__, 2) . '/manager.class.php';
}

class MiniShop3MgrSettingsManagerController extends msManagerController
{
    /**
     * @return string
     */
    public function getPageTitle()
    {
        return $this->modx->lexicon('ms3_settings') . ' | MiniShop3';
    }

    /**
     * @return array
     */
    public function getLanguageTopics()
    {
        return ['minishop3:default', 'minishop3:product', 'minishop3:manager', 'minishop3:vue'];
    }

    /**
     *
     */
    public function loadCustomCssJs()
    {
        $this->addCss($this->ms3->config['cssUrl'] . 'mgr/bootstrap.buttons.css');
        $this->addCss($this->ms3->config['cssUrl'] . 'mgr/main.css');
        $this->addJavascript($this->ms3->config['jsUrl'] . 'mgr/minishop3.js');
        $this->addJavascript($this->ms3->config['jsUrl'] . 'mgr/misc/default.grid.js');
        $this->addJavascript($this->ms3->config['jsUrl'] . 'mgr/misc/default.window.js');
        $this->addJavascript($this->ms3->config['jsUrl'] . 'mgr/misc/strftime-min-1.3.js');
        $this->addJavascript($this->ms3->config['jsUrl'] . 'mgr/misc/ms3.utils.js');
        $this->addJavascript($this->ms3->config['jsUrl'] . 'mgr/misc/ms3.combo.js');

        // Vue shared CSS (variables + PrimeIcons)
        $this->addCss($this->ms3->config['assetsUrl'] . 'css/mgr/vue-dist/primeicons.min.css');
        // Vue shared components CSS
        $this->addCss($this->ms3->config['assetsUrl'] . 'css/mgr/vue-dist/FileBrowser.min.css');
        $this->addCss($this->ms3->config['assetsUrl'] . 'css/mgr/vue-dist/DynamicField.min.css');

        // Vue Grids CSS
        $this->addCss($this->ms3->config['assetsUrl'] . 'css/mgr/vue-dist/deliveries.min.css');
        $this->addCss($this->ms3->config['assetsUrl'] . 'css/mgr/vue-dist/payments.min.css');
        $this->addCss($this->ms3->config['assetsUrl'] . 'css/mgr/vue-dist/vendors.min.css');
        $this->addCss($this->ms3->config['assetsUrl'] . 'css/mgr/vue-dist/statuses.min.css');
        $this->addCss($this->ms3->config['assetsUrl'] . 'css/mgr/vue-dist/links.min.css');
        $this->addCss($this->ms3->config['assetsUrl'] . 'css/mgr/vue-dist/options.min.css');

        // Vue modules with VueTools dependency check
        $this->addVueModule($this->ms3->config['jsUrl'] . 'mgr/vue-dist/deliveries.min.js');
        $this->addVueModule($this->ms3->config['jsUrl'] . 'mgr/vue-dist/payments.min.js');
        $this->addVueModule($this->ms3->config['jsUrl'] . 'mgr/vue-dist/vendors.min.js');
        $this->addVueModule($this->ms3->config['jsUrl'] . 'mgr/vue-dist/statuses.min.js');
        $this->addVueModule($this->ms3->config['jsUrl'] . 'mgr/vue-dist/links.min.js');
        $this->addVueModule($this->ms3->config['jsUrl'] . 'mgr/vue-dist/options.min.js');

        $this->addJavascript($this->ms3->config['jsUrl'] . 'mgr/settings/settings.panel.js');
        $this->addJavascript($this->ms3->config['jsUrl'] . 'mgr/settings/settings.js');
        $this->addJavascript(MODX_MANAGER_URL . 'assets/modext/util/datetime.js');

        $config = $this->ms3->config;
        $config['default_thumb'] = $this->ms3->config['defaultThumb'];

        $this->addHtml('<script>
            ms3.config = ' . json_encode($config) . ';
            MODx.perm.msorder_list = ' . ($this->modx->hasPermission('msorder_list') ? 1 : 0) . ';

            Ext.onReady(function() {
                MODx.add({xtype: "ms3-page-settings"});
            });
        </script>');

        $this->modx->invokeEvent('msOnManagerCustomCssJs', [
            'controller' => $this,
            'page' => 'settings',
        ]);
    }
}
