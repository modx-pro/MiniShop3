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

    public function loadCustomCssJs()
    {
        $config = $this->ms3->config;
        $config['default_thumb'] = $this->ms3->config['defaultThumb'];
        $config['msorder_list'] = $this->modx->hasPermission('msorder_list');

        // Config must precede Vue modules (#523).
        $this->addVueConfig($config);

        $assetsUrl = $this->ms3->config['assetsUrl'];
        $cssBase = $assetsUrl . 'css/mgr/vue-dist/';
        // settings.min.css absorbs tab/grid styles; shared chunks keep stable names.
        foreach ([
            'primeicons',
            'settings',
            'DynamicField',
            'ActionsColumn',
            'ResourceCategoryTree',
        ] as $asset) {
            $this->addCss($cssBase . $asset . '.min.css');
        }
        $this->addVueModule($this->ms3->config['jsUrl'] . 'mgr/vue-dist/settings.min.js');

        $this->modx->invokeEvent('msOnManagerCustomCssJs', [
            'controller' => $this,
            'page' => 'settings',
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
        return dirname(__FILE__, 3) . '/templates/default/settings.tpl';
    }
}
