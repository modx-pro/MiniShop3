<?php

use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductFile;

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

    public function loadCustomCssJs()
    {
        $config = $this->ms3->config;
        $config['mssetting_list'] = $this->modx->hasPermission('mssetting_list');

        $productSource = (int)$this->getOption('ms3_product_source_default', null, 1);
        $source = $productSource > 0
            ? $this->modx->getObject('sources.modMediaSource', $productSource)
            : null;
        if ($source) {
            $config['utility_gallery_source_id'] = $productSource;
            $config['utility_gallery_source_name'] = $source->get('name');

            $thumbnails = json_decode(
                (string)($source->get('properties')['thumbnails']['value'] ?? ''),
                true
            );
            $propertiesString = '';
            if (is_array($thumbnails)) {
                foreach ($thumbnails as $key => $value) {
                    $propertiesString .= '<strong>' . htmlspecialchars((string)$key, ENT_QUOTES, 'UTF-8')
                        . ': </strong>' . htmlspecialchars(json_encode($value), ENT_QUOTES, 'UTF-8') . '<br>';
                }
            }
            $config['utility_gallery_thumbnails'] = $propertiesString;
        }

        $config['utility_gallery_total_products'] = $this->modx->getCount(
            msProduct::class,
            ['class_key' => msProduct::class]
        );
        $config['utility_gallery_total_products_files'] = $this->modx->getCount(
            msProductFile::class,
            ['parent_id' => 0]
        );

        // Config must precede Vue modules (#524).
        $this->addVueConfig($config);

        $cssBase = $this->ms3->config['assetsUrl'] . 'css/mgr/vue-dist/';
        foreach (['primeicons', 'utilities'] as $asset) {
            $this->addCss($cssBase . $asset . '.min.css');
        }
        $this->addVueModule($this->ms3->config['jsUrl'] . 'mgr/vue-dist/utilities.min.js');

        $this->modx->invokeEvent('msOnManagerCustomCssJs', [
            'controller' => $this,
            'page' => 'utilities',
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
        return dirname(__FILE__, 3) . '/templates/default/utilities.tpl';
    }
}
