ms3.page.UpdateProduct = function (config) {
    config = config || {record: {}};
    config.record = config.record || {};

    Ext.applyIf(config, {
        panelXType: 'ms3-panel-product-update',
        mode: 'update',
        url: ms3.config.connector_url
    });
    ms3.page.UpdateProduct.superclass.constructor.call(this, config);
};
Ext.extend(ms3.page.UpdateProduct, MODx.page.UpdateResource, {

    getButtons: function (config) {
        const buttons = [];
        const originals = MODx.page.UpdateResource.prototype.getButtons.call(this, config);
        for (const i in originals) {
            if (!originals.hasOwnProperty(i)) {
                continue;
            }
            const button = originals[i];
            switch (button.id) {
                case 'modx-abtn-save':
                    button.text = '<i class="icon icon-save"></i> ' + button.text;
                    break;
                case 'modx-abtn-preview':
                    button.text = '<i class="icon icon-eye"></i>';
                    break;
                case 'modx-abtn-duplicate':
                    button.text = '<i class="icon icon-files-o"></i> ' + button.text;
                    break;
                case 'modx-abtn-cancel':
                    continue;
                case 'modx-abtn-help':
                    buttons.push(this.getAdditionalButtons(config));
                    button.text = '<i class="icon icon-question-circle"></i>';
                    break;
            }
            buttons.push(button)
        }

        return buttons;
    },

    getAdditionalButtons: function (config) {
        return [{
            text: '<i class="icon icon-arrow-left"></i>',
            handler: this.prevPage,
            disabled: !config['prev_page'],
            scope: this,
            tooltip: _('ms3_btn_prev'),
            keys: [{key: 37, alt: true, scope: this, fn: this.prevPage}]
        }, {
            text: '<i class="icon icon-arrow-up"></i>',
            handler: this.cancel,
            scope: this,
            tooltip: _('ms3_btn_back'),
            keys: [{key: 38, alt: true, scope: this, fn: this.upPage}]
        }, {
            text: '<i class="icon icon-arrow-right"></i>',
            handler: this.nextPage,
            disabled: !config['next_page'],
            scope: this,
            tooltip: _('ms3_btn_next'),
            keys: [{key: 39, alt: true, scope: this, fn: this.nextPage}]
        }];
    },

    prevPage: function () {
        if (this.config['prev_page'] > 0) {
            MODx.loadPage('resource/update', 'id=' + this.config['prev_page'])
        }
    },

    nextPage: function () {
        if (this.config['next_page'] > 0) {
            MODx.loadPage('resource/update', 'id=' + this.config['next_page'])
        }
    },

    cancel: function () {
        const id = this.config['up_page'];
        const action = id != 0
            ? 'resource/update'
            : 'welcome';

        MODx.loadPage(action, 'id=' + id)
    },

});
Ext.reg('ms3-page-product-update', ms3.page.UpdateProduct);


ms3.panel.UpdateProduct = function (config) {
    config = config || {};
    ms3.panel.UpdateProduct.superclass.constructor.call(this, config);
};
Ext.extend(ms3.panel.UpdateProduct, ms3.panel.Product, {

    getFields: function (config) {
        const fields = [];
        const originals = ms3.panel.Product.prototype.getFields.call(this, config);

        for (const i in originals) {
            if (!originals.hasOwnProperty(i)) {
                continue;
            }
            const item = originals[i];
            if (item.id === 'modx-header-breadcrumbs') {
                fields.push(item);
                continue;
            }
            const tabs = [];

            let accessPermissionsTab;
            let pageSettingsTab;

            item.items.forEach(tabItem => {
                switch (tabItem.id) {
                    case 'modx-page-settings':
                        pageSettingsTab = tabItem;
                        break;
                    case 'modx-resource-access-permissions':
                        accessPermissionsTab = tabItem;
                        break;
                    default:
                        tabs.push(tabItem);
                }
            });



            if (parseInt(ms3.config['show_gallery']) !== 0) {
                const galleryTab = this.getGallery(config);
                tabs.push(galleryTab);
            }
            tabs.push(pageSettingsTab);
            tabs.push(accessPermissionsTab);

            item.items = tabs;
            fields.push(item);
        }

        return fields;
    },

    getGallery: function (config) {
        return {
            title: _('ms3_tab_product_gallery'),
            layout: 'anchor',
            items: [{
                xtype: 'ms3-gallery-page',
                record: config.record,
                pageSize: 50,
                border: false,
            }]
        };
    },

});
Ext.reg('ms3-panel-product-update', ms3.panel.UpdateProduct);
