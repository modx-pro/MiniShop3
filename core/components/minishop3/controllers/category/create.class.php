<?php

if (!class_exists('msResourceCreateController')) {
    require_once dirname(__FILE__, 2) . '/resource_create.class.php';
}

class msCategoryCreateManagerController extends msResourceCreateController
{
    /**
     * Returns language topics
     * @return array
     */
    public function getLanguageTopics()
    {
        return ['resource', 'minishop3:default', 'minishop3:product', 'minishop3:manager'];
    }

    /**
     * Check for any permissions or requirements to load page
     * @return bool
     */
    public function checkPermissions()
    {
        return $this->modx->hasPermission('new_document');
    }

    /**
     * Return the default template for this resource
     * @return int|mixed
     */
    public function getDefaultTemplate()
    {
        if (!$template = $this->getOption('ms_template_category_default')) {
            $template = parent::getDefaultTemplate();
        }

        return $template;
    }

    /**
     * Register custom CSS/JS for the page
     * @return void
     */
    public function loadCustomCssJs()
    {
        $ms3 = $this->modx->services->get('ms3');
        $mgrUrl = $this->getOption('manager_url', null, MODX_MANAGER_URL);
        $assetsUrl = $ms3->config['assetsUrl'];

        $this->addCss($assetsUrl . 'css/mgr/main.css');
        $this->addJavascript($mgrUrl . 'assets/modext/util/datetime.js');
        $this->addJavascript($mgrUrl . 'assets/modext/widgets/element/modx.panel.tv.renders.js');
        $this->addJavascript($mgrUrl . 'assets/modext/widgets/resource/modx.grid.resource.security.local.js');
        $this->addJavascript($mgrUrl . 'assets/modext/widgets/resource/modx.panel.resource.tv.js');
        $this->addJavascript($mgrUrl . 'assets/modext/widgets/resource/modx.panel.resource.js');
        $this->addJavascript($mgrUrl . 'assets/modext/sections/resource/create.js');
        $this->addJavascript($assetsUrl . 'js/mgr/minishop.js');
        $this->addJavascript($assetsUrl . 'js/mgr/misc/ms.combo.js');
        $this->addJavascript($assetsUrl . 'js/mgr/misc/ms.utils.js');
        $this->addJavascript($assetsUrl . 'js/mgr/category/category.common.js');
        $this->addLastJavascript($assetsUrl . 'js/mgr/category/create.js');

        $config = [
            'assets_url' => $ms3->config['assetsUrl'],
            'connector_url' => $ms3->config['connectorUrl'],
            'isHideContent' => $this->isHideContent(),
        ];
        $ready = [
            'xtype' => 'minishop-page-category-create',
            'record' => array_merge($this->resourceArray, [
                'isfolder' => true,
            ]),
            'publish_document' => $this->canPublish,
            'canSave' => $this->modx->hasPermission('mscategory_save'),
            'show_tvs' => !empty($this->tvCounts),
            'mode' => 'create',
        ];

        $this->addHtml('
        <script>
        // <![CDATA[
            MODx.config.publish_document = "' . $this->canPublish . '";
            MODx.onDocFormRender = "' . $this->onDocFormRender . '";
            MODx.ctx = "' . $this->ctx . '";
            minishop.config = ' . json_encode($config) . ';
            Ext.onReady(function() {
                MODx.load(' . json_encode($ready) . ');
            });
        // ]]>
        </script>');

        // load RTE
        $this->loadRichTextEditor();
        $this->modx->invokeEvent('msOnManagerCustomCssJs', ['controller' => $this, 'page' => 'category_create']);
    }
}
