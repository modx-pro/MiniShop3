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
        $this->addCss($this->ms3->config['cssUrl'] . 'mgr/utilities/gallery.css');

        $this->addJavascript($this->ms3->config['jsUrl'] . 'mgr/minishop3.js');
        $this->addJavascript($this->ms3->config['jsUrl'] . 'mgr/misc/default.grid.js');
        $this->addJavascript($this->ms3->config['jsUrl'] . 'mgr/misc/default.window.js');
        $this->addJavascript($this->ms3->config['jsUrl'] . 'mgr/misc/ms3.utils.js');
        $this->addJavascript($this->ms3->config['jsUrl'] . 'mgr/misc/ms3.combo.js');

        $this->addJavascript($this->ms3->config['jsUrl'] . 'mgr/utilities/utilities.js');
        $this->addJavascript($this->ms3->config['jsUrl'] . 'mgr/utilities/utilities.panel.js');
        $this->addJavascript($this->ms3->config['jsUrl'] . 'mgr/utilities/gallery/panel.js');
        $this->addJavascript($this->ms3->config['jsUrl'] . 'mgr/utilities/import/panel.js');
        // Старые ExtJS файлы для extra fields удалены - используется новый Vue виджет

        $config = $this->ms3->config;

        // Принудительно перезагружаем топик vue
        $this->modx->lexicon->clearCache('minishop3:vue');
        $this->modx->lexicon->load('minishop3:vue');

        // Добавляем лексиконы для Vue виджета
        $lexiconKeys = [
            'sections', 'fields', 'section_add', 'section_delete',
            'section_delete_confirm_title', 'section_delete_confirm_message',
            'section_delete_btn', 'section_cancel_btn', 'save_changes',
            'save_success', 'save_error', 'sections_saved', 'section_deleted',
            'loading', 'error_loading_sections', 'error_deleting_section',
            'error_saving_sections', 'visible', 'section_key', 'section_label', 'actions',
            // Редактирование поля
            'field_edit_title', 'field_xtype', 'field_xtype_readonly',
            'field_section', 'field_section_help', 'field_section_placeholder',
            'field_label', 'field_label_placeholder', 'field_label_help',
            'field_width', 'field_width_placeholder', 'field_width_help',
            'field_placeholder', 'field_placeholder_placeholder',
            'field_description', 'field_description_placeholder',
            'field_visible', 'field_visible_help',
            'field_save', 'field_cancel', 'field_saved'
        ];

        $config['lexicon'] = [];
        foreach ($lexiconKeys as $key) {
            $lexiconValue = $this->modx->lexicon('ms3_vue_' . $key);
            // Защита от null/empty значений
            $config['lexicon'][$key] = $lexiconValue ?: $key;
        }

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

        // ВАЖНО: Сначала конфигурация, потом Vue модули
        $this->addHtml('<script>Object.assign(ms3.config, ' . json_encode($config) . ');</script>');

        $this->addHtml(
            '<link rel="stylesheet" href="' . $this->ms3->config['assetsUrl'] . 'css/mgr/utilities/_plugin-vue_export-helper.min.css">
        <link rel="stylesheet" href="' . $this->ms3->config['assetsUrl'] . 'css/mgr/utilities/fields-management.min.css">
        <link rel="stylesheet" href="' . $this->ms3->config['assetsUrl'] . 'css/mgr/utilities/extra-fields.min.css">
        <script type="module" src="' . $this->ms3->config['assetsUrl'] . 'js/mgr/utilities/fields-management.min.js"></script>
        <script type="module" src="' . $this->ms3->config['assetsUrl'] . 'js/mgr/utilities/extra-fields.min.js"></script>
        <script>
            Ext.onReady(function() {
                MODx.add({xtype: "ms3-page-utilities"});
            });
        </script>'
        );
    }
}
