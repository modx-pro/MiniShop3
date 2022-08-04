<?php

use MiniShop3\Model\msOptionType;

if (!class_exists('msManagerController')) {
    require_once dirname(__FILE__, 2) . '/manager.class.php';
}

class MiniShopMgrSettingsManagerController extends msManagerController
{
    /**
     * @return string
     */
    public function getPageTitle()
    {
        return $this->modx->lexicon('ms_settings') . ' | MiniShop3';
    }

    /**
     * @return array
     */
    public function getLanguageTopics()
    {
        return ['minishop:default', 'minishop:product', 'minishop:manager'];
    }

    /**
     *
     */
    public function loadCustomCssJs()
    {
        $this->addCss($this->ms3->config['cssUrl'] . 'mgr/bootstrap.buttons.css');
        $this->addCss($this->ms3->config['cssUrl'] . 'mgr/main.css');
        $this->addJavascript($this->ms3->config['jsUrl'] . 'mgr/minishop.js');
        $this->addJavascript($this->ms3->config['jsUrl'] . 'mgr/misc/default.grid.js');
        $this->addJavascript($this->ms3->config['jsUrl'] . 'mgr/misc/default.window.js');
        $this->addJavascript($this->ms3->config['jsUrl'] . 'mgr/misc/strftime-min-1.3.js');
        $this->addJavascript($this->ms3->config['jsUrl'] . 'mgr/misc/ms.utils.js');
        $this->addJavascript($this->ms3->config['jsUrl'] . 'mgr/misc/ms.combo.js');

        $this->addJavascript($this->ms3->config['jsUrl'] . 'mgr/settings/delivery/grid.js');
        $this->addJavascript($this->ms3->config['jsUrl'] . 'mgr/settings/delivery/window.js');
        $this->addJavascript($this->ms3->config['jsUrl'] . 'mgr/settings/delivery/members.js');
        $this->addJavascript($this->ms3->config['jsUrl'] . 'mgr/settings/payment/grid.js');
        $this->addJavascript($this->ms3->config['jsUrl'] . 'mgr/settings/payment/window.js');
        $this->addJavascript($this->ms3->config['jsUrl'] . 'mgr/settings/payment/members.js');
        $this->addJavascript($this->ms3->config['jsUrl'] . 'mgr/settings/status/grid.js');
        $this->addJavascript($this->ms3->config['jsUrl'] . 'mgr/settings/status/window.js');
        $this->addJavascript($this->ms3->config['jsUrl'] . 'mgr/settings/vendor/grid.js');
        $this->addJavascript($this->ms3->config['jsUrl'] . 'mgr/settings/vendor/window.js');
        $this->addJavascript($this->ms3->config['jsUrl'] . 'mgr/settings/link/grid.js');
        $this->addJavascript($this->ms3->config['jsUrl'] . 'mgr/settings/link/window.js');
        $this->addJavascript($this->ms3->config['jsUrl'] . 'mgr/settings/option/grid.js');
        $this->addJavascript($this->ms3->config['jsUrl'] . 'mgr/settings/option/window.js');
        $this->addJavascript($this->ms3->config['jsUrl'] . 'mgr/settings/option/tree.js');

        $this->addJavascript($this->ms3->config['jsUrl'] . 'mgr/settings/settings.panel.js');
        $this->addJavascript($this->ms3->config['jsUrl'] . 'mgr/settings/settings.js');
        $this->addJavascript(MODX_MANAGER_URL . 'assets/modext/util/datetime.js');

        $types = $this->ms3->options->loadOptionTypeList();
        foreach ($types as $type) {
            $className = $this->ms3->options->loadOptionType($type);
            if (class_exists($className)) {
                /** @var msOptionType $className */
                if ($className::$script) {
                    $this->addJavascript($this->ms3->config['jsUrl'] . 'mgr/settings/option/types/' . $className::$script);
                }
            }
        }

        $config = $this->ms3->config;
        $config['default_thumb'] = $this->ms3->config['defaultThumb'];
        $this->addHtml('<script>
            MS3.config = ' . json_encode($config) . ';

            MODx.perm.msorder_list = ' . ($this->modx->hasPermission('msorder_list') ? 1 : 0) . ';

            Ext.onReady(function() {
                MODx.add({xtype: "minishop-page-settings"});
            });
        </script>');

        $this->modx->invokeEvent('msOnManagerCustomCssJs', [
            'controller' => $this,
            'page' => 'settings',
        ]);
    }
}
