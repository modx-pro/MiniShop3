<?php

if (!class_exists('msManagerController')) {
    require_once dirname(__FILE__, 2) . '/manager.class.php';
}

class MiniShop3MgrHelpManagerController extends msManagerController
{
    /**
     * @return string
     */
    public function getPageTitle()
    {
        return $this->modx->lexicon('ms3_help') . ' | MiniShop3';
    }

    /**
     * @return array
     */
    public function getLanguageTopics()
    {
        return ['minishop3:help'];
    }

    /**
     *
     */
    public function loadCustomCssJs()
    {
        // Vue CSS
        $this->addCss($this->ms3->config['assetsUrl'] . 'css/mgr/vue-dist/help.min.css');

        // Vue module
        $this->addVueModule($this->ms3->config['jsUrl'] . 'mgr/vue-dist/help.min.js');

        // Pass config to JavaScript
        $this->addHtml('<script>
            ms3.config = ' . json_encode($this->ms3->config) . ';
        </script>');
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
        return dirname(__FILE__, 3) . '/templates/default/help.tpl';
    }
}
