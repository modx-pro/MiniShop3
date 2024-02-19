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
        return ['minishop3:default', 'minishop3:product', 'minishop3:manager'];
    }

    /**
     *
     */
    public function loadCustomCssJs()
    {
        $this->addCss($this->ms3->config['cssUrl'] . 'mgr/utilities/gallery.css');

        $this->addJavascript($this->ms3->config['jsUrl'] . 'mgr/minishop3.js');
        $this->addJavascript($this->ms3->config['jsUrl'] . 'mgr/utilities/panel.js');
        $this->addJavascript($this->ms3->config['jsUrl'] . 'mgr/utilities/gallery/panel.js');
        $this->addJavascript($this->ms3->config['jsUrl'] . 'mgr/utilities/import/panel.js');

        $config = $this->ms3->config;

        // get source properties
        $productSource = $this->getOption('ms3_product_source_default', null, 1);
        if ($source = $this->modx->getObject(modMediaSource::class, $productSource)) {
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

        // get params for import
        $config['utility_import_fields'] = $this->getOption('ms3_utility_import_fields', null, 'pagetitle,parent,price,article', true);
        $config['utility_import_fields_delimiter'] = $this->getOption('ms3_utility_import_fields_delimiter', null, ';', true);

        $this->addHtml(
            '<script>
            ms3.config = ' . json_encode($config) . ';
            Ext.onReady(function() {
                MODx.add({xtype: "ms3-utilities"});
            });
        </script>'
        );
    }
}
