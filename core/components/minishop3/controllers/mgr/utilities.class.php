<?php

use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductFile;
use MODX\Revolution\Sources\modMediaSource;

if (!class_exists('msManagerController')) {
    require_once dirname(__FILE__, 2) . '/manager.class.php';
}

class MiniShop3MgrUtilitiesManagerController extends msManagerController
{
    /**
     * @return string
     */
    public function getPageTitle()
    {
        return $this->modx->lexicon('ms3_utilities') . ' | MiniShop3';
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
        // Gallery CSS now included in Vue component (utilities-gallery.min.css)

        $this->addJavascript($this->ms3->config['jsUrl'] . 'mgr/minishop3.js');
        $this->addJavascript($this->ms3->config['jsUrl'] . 'mgr/misc/default.grid.js');
        $this->addJavascript($this->ms3->config['jsUrl'] . 'mgr/misc/default.window.js');
        $this->addJavascript($this->ms3->config['jsUrl'] . 'mgr/misc/ms3.utils.js');
        $this->addJavascript($this->ms3->config['jsUrl'] . 'mgr/misc/ms3.combo.js');

        $this->addJavascript($this->ms3->config['jsUrl'] . 'mgr/utilities/utilities.js');
        $this->addJavascript($this->ms3->config['jsUrl'] . 'mgr/utilities/utilities.panel.js');
        // Gallery panel now uses Vue component (utilities-gallery.min.js)
        $this->addJavascript($this->ms3->config['jsUrl'] . 'mgr/utilities/import/panel.js');

        $config = $this->ms3->config;

        $productSource = (int)$this->getOption('ms3_product_source_default', null, 1);
        $source = null;
        if ($productSource > 0) {
            $source = $this->modx->getObject('sources.modMediaSource', $productSource);
        }
        if ($source) {
            $config['utility_gallery_source_id'] = $productSource;
            $config['utility_gallery_source_name'] = $source->get('name');

            $properties = $source->get('properties');
            $propertiesString = '';
            foreach (json_decode($properties['thumbnails']['value'], true) as $key => $value) {
                $propertiesString .= "<strong>$key: </strong>" . json_encode($value) . "<br>";
            }
            $config['utility_gallery_thumbnails'] = $propertiesString;
        }

        // get information about products and files
        $config['utility_gallery_total_products'] = $this->modx->getCount(msProduct::class, ['class_key' => msProduct::class]);
        $config['utility_gallery_total_products_files'] = $this->modx->getCount(msProductFile::class, ['parent_id' => 0]);

        $config['utility_import_fields'] = $this->getOption('ms3_utility_import_fields', null, 'pagetitle,parent,price,article', true);
        $config['utility_import_fields_delimiter'] = $this->getOption('ms3_utility_import_fields_delimiter', null, ';', true);

        // CSS for Vue components
        $this->addHtml(
            '<link rel="stylesheet" href="' . $this->ms3->config['assetsUrl'] . 'css/mgr/vue-dist/useLexicon.min.css">
        <link rel="stylesheet" href="' . $this->ms3->config['assetsUrl'] . 'css/mgr/vue-dist/fields-management.min.css">
        <link rel="stylesheet" href="' . $this->ms3->config['assetsUrl'] . 'css/mgr/vue-dist/extra-fields.min.css">
        <link rel="stylesheet" href="' . $this->ms3->config['assetsUrl'] . 'css/mgr/vue-dist/grid-fields-config.min.css">
        <link rel="stylesheet" href="' . $this->ms3->config['assetsUrl'] . 'css/mgr/vue-dist/model-fields.min.css">
        <link rel="stylesheet" href="' . $this->ms3->config['assetsUrl'] . 'css/mgr/vue-dist/import.min.css">
        <link rel="stylesheet" href="' . $this->ms3->config['assetsUrl'] . 'css/mgr/vue-dist/utilities-gallery.min.css">'
        );

        // Config MUST be set BEFORE Vue modules load (they read from ms3.config)
        $this->addHtml('<script>Object.assign(ms3.config, ' . json_encode($config) . ');</script>');

        // Vue modules (ES modules are deferred, so config will be ready)
        $this->addHtml(
            '<script type="module" src="' . $this->ms3->config['assetsUrl'] . 'js/mgr/vue-dist/fields-management.min.js"></script>
        <script type="module" src="' . $this->ms3->config['assetsUrl'] . 'js/mgr/vue-dist/extra-fields.min.js"></script>
        <script type="module" src="' . $this->ms3->config['assetsUrl'] . 'js/mgr/vue-dist/grid-fields-config.min.js"></script>
        <script type="module" src="' . $this->ms3->config['assetsUrl'] . 'js/mgr/vue-dist/model-fields.min.js"></script>
        <script type="module" src="' . $this->ms3->config['assetsUrl'] . 'js/mgr/vue-dist/import.min.js"></script>
        <script type="module" src="' . $this->ms3->config['assetsUrl'] . 'js/mgr/vue-dist/utilities-gallery.min.js"></script>
        <script>
            Ext.onReady(function() {
                MODx.add({xtype: "ms3-page-utilities"});
            });
        </script>'
        );
    }
}
