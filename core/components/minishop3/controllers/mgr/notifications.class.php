<?php

if (!class_exists('msManagerController')) {
    require_once dirname(__FILE__, 2) . '/manager.class.php';
}

class MiniShop3MgrNotificationsManagerController extends msManagerController
{
    /**
     * @return string
     */
    public function getPageTitle()
    {
        return $this->modx->lexicon('ms3_notifications') . ' | MiniShop3';
    }

    /**
     * @return array
     */
    public function getLanguageTopics()
    {
        return ['minishop3:default', 'minishop3:notifications', 'minishop3:manager', 'minishop3:vue'];
    }

    /**
     *
     */
    public function loadCustomCssJs()
    {
        // Config before Vue modules so mount sees ms3.config (#525).
        $this->addVueConfig($this->ms3->config);

        $this->addCss($this->ms3->config['assetsUrl'] . 'css/mgr/vue-dist/primeicons.min.css');
        $this->addCss($this->ms3->config['assetsUrl'] . 'css/mgr/vue-dist/notifications.min.css');
        $this->addVueModule($this->ms3->config['jsUrl'] . 'mgr/vue-dist/notifications.min.js');

        $this->modx->invokeEvent('msOnManagerCustomCssJs', [
            'controller' => $this,
            'page' => 'notifications',
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
        return dirname(__FILE__, 3) . '/templates/default/notifications.tpl';
    }
}
