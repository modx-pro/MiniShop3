<?php

use MiniShop3\Model\msCategory;

if (!class_exists('msResourceUpdateController')) {
    require_once dirname(__FILE__, 2) . '/resource_update.class.php';
}

class msCategoryUpdateManagerController extends msResourceUpdateController
{
    /** @var msCategory $resource */
    public $resource;


    /**
    * Returns language topics
    * @return array
    */
    public function getLanguageTopics()
    {
        return array('resource', 'minishop3:default', 'minishop3:product', 'minishop3:manager', 'minishop3:vue');
    }


    /**
    * Check for any permissions or requirements to load page
    * @return bool
    */
    public function checkPermissions()
    {
        return $this->modx->hasPermission('edit_document');
    }


    /**
    * Register custom CSS/JS for the page
    */
    public function loadCustomCssJs()
    {
        $mgrUrl = $this->getOption('manager_url', null, MODX_MANAGER_URL);
        $assetsUrl = $this->ms3->config['assetsUrl'];

        $showOptions = (bool)$this->getOption('ms3_category_show_options', null, true);

        if ($this->resource instanceof msCategory) {
            $neighborhood = $this->resource->getNeighborhood();
        }

        $this->addCss($assetsUrl . 'css/mgr/main.css');
        $this->addCss($assetsUrl . 'css/mgr/bootstrap.buttons.css');
        $this->addJavascript($mgrUrl . 'assets/modext/util/datetime.js');
        $this->addJavascript($mgrUrl . 'assets/modext/widgets/element/modx.panel.tv.renders.js');
        $this->addJavascript($mgrUrl . 'assets/modext/widgets/resource/modx.grid.resource.security.local.js');
        $this->addJavascript($mgrUrl . 'assets/modext/widgets/resource/modx.panel.resource.tv.js');
        $this->addJavascript($mgrUrl . 'assets/modext/widgets/resource/modx.panel.resource.js');
        $this->addJavascript($mgrUrl . 'assets/modext/sections/resource/update.js');
        $this->addJavascript($assetsUrl . 'js/mgr/minishop3.js');
        $this->addJavascript($assetsUrl . 'js/mgr/category/category.common.js');
        $this->addLastJavascript($assetsUrl . 'js/mgr/category/update.js');

        // Vue modules with VueTools dependency check
        $this->addCss($assetsUrl . 'css/mgr/vue-dist/primeicons.min.css');
        $this->addCss($assetsUrl . 'css/mgr/vue-dist/category-products.min.css');
        $this->addCss($assetsUrl . 'css/mgr/vue-dist/category-options.min.css');
        $this->addVueModule($assetsUrl . 'js/mgr/vue-dist/category-products.min.js');
        $this->addVueModule($assetsUrl . 'js/mgr/vue-dist/category-options.min.js');

        $config = array(
            'assets_url' => $this->ms3->config['assetsUrl'],
            'connector_url' => $this->ms3->config['connectorUrl'],
            'show_options' => $showOptions,
            'default_thumb' => $this->ms3->config['defaultThumb'],
            'show_nested_products' => (bool) $this->modx->getOption('ms3_category_show_nested_products', null, true),
            'category_products_rows' => (int) $this->getOption('ms3_category_products_default_rows', null, 20),
        );
        // Parent already sets $this->canSave (save_document, checkPolicy('save'), lock). Add component permission only.
        // All permission flags must be cast to (int) because MODX JS uses strict comparison (=== 1).
        $ready = array(
            'xtype' => 'ms3-page-category-update',
            'resource' => $this->resource->get('id'),
            'record' => $this->resourceArray,
            'publish_document' => (int) $this->canPublish,
            'preview_url' => $this->previewUrl,
            'locked' => $this->locked,
            'lockedText' => $this->lockedText,
            'canSave' => (int) ($this->canSave && $this->modx->hasPermission('mscategory_save')),
            'canEdit' => (int) $this->canEdit,
            'canCreate' => (int) $this->canCreate,
            'canCreateRoot' => (int) $this->canCreateRoot,
            'canDuplicate' => (int) $this->canDuplicate,
            'canDelete' => (int) $this->canDelete,
            'canPublish' => (int) $this->canPublish,
            'show_tvs' => (int) !empty($this->tvCounts),
            'next_page' => !empty($neighborhood['right'][0]) ? $neighborhood['right'][0] : 0,
            'prev_page' => !empty($neighborhood['left'][0]) ? $neighborhood['left'][0] : 0,
            'up_page' => $this->resource->parent,
            'mode' => 'update',
        );

        $this->addHtml('
        <script>
        // <![CDATA[
            MODx.config.publish_document = "' . $this->canPublish . '";
            MODx.onDocFormRender = "' . $this->onDocFormRender . '";
            MODx.ctx = "' . $this->ctx . '";
            ms3.config = ' . json_encode($config) . ';
            Ext.onReady(function() {
                MODx.load(' . json_encode($ready) . ');
            });
            MODx.perm.tree_show_resource_ids = ' . ($this->modx->hasPermission('tree_show_resource_ids') ? 1 : 0) . ';
        // ]]>
        </script>');
        // Load RTE
        $this->loadRichTextEditor();
        $this->modx->invokeEvent('msOnManagerCustomCssJs', array('controller' => $this, 'page' => 'category_update'));
    }
}
