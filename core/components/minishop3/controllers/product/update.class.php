<?php

use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductData;

if (!class_exists('msResourceUpdateController')) {
    require_once dirname(__FILE__, 2) . '/resource_update.class.php';
}

class msProductUpdateManagerController extends msResourceUpdateController
{
    /** @var msProduct $resource */
    public $resource;

    /**
     * Returns language topics
     * @return array
     */
    public function getLanguageTopics()
    {
        return ['resource', 'minishop3:default', 'minishop3:product', 'minishop3:manager', 'minishop3:vue'];
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
     * @return void
     */
    public function loadCustomCssJs()
    {
        $mgrUrl = $this->getOption('manager_url', null, MODX_MANAGER_URL);
        $assetsUrl = $this->ms3->config['assetsUrl'];

        $this->addCss($assetsUrl . 'css/mgr/bootstrap.buttons.css');
        $this->addCss($assetsUrl . 'css/mgr/main.css');
        $this->addCss($assetsUrl . 'css/mgr/extjs-boxmodel-fix.css');
        $this->addJavascript($mgrUrl . 'assets/modext/util/datetime.js');
        $this->addJavascript($mgrUrl . 'assets/modext/widgets/element/modx.panel.tv.renders.js');
        $this->addJavascript($mgrUrl . 'assets/modext/widgets/resource/modx.grid.resource.security.local.js');
        $this->addJavascript($mgrUrl . 'assets/modext/widgets/resource/modx.panel.resource.tv.js');
        $this->addJavascript($mgrUrl . 'assets/modext/widgets/resource/modx.panel.resource.js');
        $this->addJavascript($mgrUrl . 'assets/modext/sections/resource/update.js');
        $this->addJavascript($assetsUrl . 'js/mgr/minishop3.js');
        // Product links tab is Vue (ProductLinksTab); Ext links.grid/window no longer loaded (#114/#350).
        $this->addLastJavascript($assetsUrl . 'js/mgr/product/product.common.js');
        $this->addLastJavascript($assetsUrl . 'js/mgr/product/update.js');

        // Product Tabs Vue (Properties, Gallery, Categories, Links, Options).
        // Only these vue-dist assets — do not add main.min.css (Vite never emits it; #503).
        $this->addCss($assetsUrl . 'css/mgr/vue-dist/primeicons.min.css');
        $this->addCss($assetsUrl . 'css/mgr/vue-dist/product-tabs.min.css');
        $this->addCss($assetsUrl . 'css/mgr/vue-dist/DynamicField.min.css');
        // Shared Vite chunk: tree styles are not inlined into product-tabs.min.css (#555).
        $this->addCss($assetsUrl . 'css/mgr/vue-dist/ResourceCategoryTree.min.css');
        $this->addVueModule($assetsUrl . 'js/mgr/vue-dist/product-tabs.min.js');

        $show_gallery = $this->getOption('ms3_product_tab_gallery', null, true);

        // Customizable product fields feature
        $product_fields = array_merge($this->resource->getAllFieldsNames(), ['syncsite']);
        $product_data_fields = $this->resource->getDataFieldsNames();

        if (!$product_main_fields = $this->getOption('ms3_product_main_fields')) {
            $product_main_fields = 'pagetitle,longtitle,introtext,content,publishedon,pub_date,unpub_date,template,
                parent,alias,menutitle,searchable,cacheable,richtext,uri_override,uri,hidemenu,show_in_tree';
        }
        $product_main_fields = array_map('trim', explode(',', $product_main_fields));
        $product_main_fields = array_values(array_intersect($product_main_fields, $product_fields));

        if (!$product_extra_fields = $this->getOption('ms3_product_extra_fields')) {
            $product_extra_fields = 'price,old_price,article,weight,color,size,vendor,made_in,tags,new,popular,favorite';
        }
        $product_extra_fields = array_map('trim', explode(',', $product_extra_fields));
        $product_extra_fields = array_values(array_intersect($product_extra_fields, $product_fields));

        $productData = $this->resource->loadData();
        $product_option_keys = $productData->getOptionKeys();
        $product_option_fields = $productData->getOptionFields();

        $this->resourceArray['categories'] = $productData->get('categories');

        $this->prepareFields();

        $neighborhood = [];
        if ($this->resource instanceof msProduct) {
            $neighborhood = $this->resource->getNeighborhood();
        }

        $this->modx->lexicon->load('minishop3:product');
        $configService = new \MiniShop3\Services\ConfigService($this->modx);
        $fieldsConfig = $configService->getAllPageFields('product_data');

        $config = [
            'assets_url' => $this->ms3->config['assetsUrl'],
            'connector_url' => $this->ms3->config['connectorUrl'],
            'show_gallery' => $show_gallery,
            'show_extra' => (bool)$this->getOption('ms3_product_tab_extra', null, true),
            'show_options' => (bool)$this->getOption('ms3_product_tab_options', null, true),
            'show_links' => (bool)$this->getOption('ms3_product_tab_links', null, true),
            'show_categories' => (bool)$this->getOption('ms3_product_tab_categories', null, true),
            'default_thumb' => $this->ms3->config['defaultThumb'],
            'main_fields' => $product_main_fields,
            'extra_fields' => $product_extra_fields,
            'option_keys' => $product_option_keys,
            'option_fields' => $product_option_fields,
            'data_fields' => $product_data_fields,
            'additional_fields' => [],
            'media_source' => $this->getSourceProperties(),
            'sources' => $this->getMediaSourcesList(),
            'isHideContent' => $this->isHideContent(),
            'product_remember_tabs' => (bool)$this->getOption('ms3_product_remember_tabs', null, true),
            'lexicon' => [
                'ms3_product_data_vue' => $this->modx->lexicon('ms3_product_data_vue'),
            ],
            'fields_config' => $fieldsConfig,
        ];

        // Parent already sets $this->canSave (save_document, checkPolicy('save'), lock). Add component permission only.
        // All permission flags must be cast to (int) because MODX JS uses strict comparison (=== 1).
        $ready = [
            'xtype' => 'ms3-page-product-update',
            'resource' => $this->resource->get('id'),
            'record' => $this->resourceArray,
            'publish_document' => (int) $this->canPublish,
            'preview_url' => $this->previewUrl,
            'locked' => $this->locked,
            'lockedText' => $this->lockedText,
            'canSave' => (int) ($this->canSave && $this->modx->hasPermission('msproduct_save')),
            'canEdit' => (int) $this->canEdit,
            'canCreate' => (int) $this->canCreate,
            'canCreateRoot' => (int) $this->canCreateRoot,
            'canDuplicate' => (int) $this->canDuplicate,
            'canDelete' => (int) $this->canDelete,
            'canPublish' => (int) $this->canPublish,
            'show_tvs' => (int) !empty($this->tvCounts),
            'next_page' => !empty($neighborhood['right'][0])
                ? $neighborhood['right'][0]
                : 0,
            'prev_page' => !empty($neighborhood['left'][0])
                ? $neighborhood['left'][0]
                : 0,
            'up_page' => $this->resource->parent,
            'mode' => 'update',
        ];

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

        $this->loadRichTextEditor();
        $this->modx->invokeEvent('msOnManagerCustomCssJs', ['controller' => $this, 'page' => 'product_update']);
    }

    /**
     * Additional preparation of the resource fields
     */
    public function prepareFields()
    {
        $data = array_keys($this->modx->getFieldMeta(msProductData::class));
        foreach ($this->resourceArray as $k => $v) {
            if (is_array($v) && in_array($k, $data)) {
                $tmp = $this->resourceArray[$k];
                $this->resourceArray[$k] = [];
                foreach ($tmp as $v2) {
                    if (!empty($v2)) {
                        $this->resourceArray[$k][] = ['value' => $v2];
                    }
                }
            }
        }

        if (empty($this->resourceArray['vendor'])) {
            $this->resourceArray['vendor'] = '';
        }
    }

    /**
     * Load media source properties
     *
     * @return array
     */
    public function getSourceProperties()
    {
        $properties = [];
        /** @var $source modMediaSource */
        if ($source = $this->resource->initializeMediaSource()) {
            $tmp = $source->getProperties();
            $properties = [];
            foreach ($tmp as $v) {
                $properties[$v['name']] = $v['value'];
            }
        }

        return $properties;
    }

    /**
     * Get list of available media sources
     *
     * @return array<int, array{id: int, name: string}>
     */
    public function getMediaSourcesList(): array
    {
        $sources = [];
        $c = $this->modx->newQuery(\MODX\Revolution\Sources\modMediaSource::class);
        $c->sortby('name', 'ASC');
        $collection = $this->modx->getIterator(\MODX\Revolution\Sources\modMediaSource::class, $c);
        foreach ($collection as $source) {
            if ($source->checkPolicy('view')) {
                $sources[] = [
                    'id' => $source->get('id'),
                    'name' => $source->get('name'),
                ];
            }
        }

        return $sources;
    }
}
