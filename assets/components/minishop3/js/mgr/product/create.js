minishop.page.CreateProduct = function (config) {
    config = config || {record: {}};
    config.record = config.record || {};

    Ext.applyIf(config, {
        panelXType: 'minishop-panel-product-create',
        mode: 'create'
    });
    minishop.page.CreateProduct.superclass.constructor.call(this, config);
};
Ext.extend(minishop.page.CreateProduct, MODx.page.CreateResource, {

    getButtons: function (config) {
        const buttons = [];
        const originals = MODx.page.CreateResource.prototype.getButtons.call(this, config);
        for (const i in originals) {
            if (!originals.hasOwnProperty(i)) {
                continue;
            }
            const button = originals[i];
            switch (button.id) {
                case 'modx-abtn-save':
                    button.text = '<i class="icon icon-check"></i> ' + button.text;
                    break;
                case 'modx-abtn-cancel':
                    button.text = '<i class="icon icon-ban"></i> ' + button.text;
                    button.handler = this.cancel;
                    break;
                case 'modx-abtn-help':
                    button.text = '<i class="icon icon-question-circle"></i>';
                    break;
            }
            buttons.push(button)
        }

        return buttons;
    },

    cancel: function () {
        const id = MODx.request.parent;
        const action = id !== 0
            ? 'resource/update'
            : 'welcome';

        MODx.loadPage(action, 'id=' + id)
    },

});
Ext.reg('minishop-page-product-create', minishop.page.CreateProduct);


minishop.panel.CreateProduct = function (config) {
    config = config || {};
    minishop.panel.CreateProduct.superclass.constructor.call(this, config);
};
Ext.extend(minishop.panel.CreateProduct, minishop.panel.Product, {
    formatMainPanelTitle(formId, record, realtimeValue = null, returnBaseTitle = false) {

    },
    getFields: function (config) {
        const fields = [];
        const originals = minishop.panel.Product.prototype.getFields.call(this, config);
        for (const i in originals) {
            if (!originals.hasOwnProperty(i)) {
                continue;
            }
            const item = originals[i];
            fields.push(item);
        }
        return fields;

        // for (const i in originals) {
        //     if (!originals.hasOwnProperty(i)) {
        //         continue;
        //     }
        //     const item = originals[i];
        //     if (item.id == 'modx-resource-tabs') {
        //         // Additional "Gallery" tab
        //         if (minishop.config['show_gallery'] != 0) {
        //             item.items.push(this.getGallery(config));
        //
        //             // Get the "Resource Groups" tab and move it to the end
        //             let accessPermissionsTab;
        //             const index = item.items.findIndex(function (tab) {
        //                 return tab.id == 'modx-resource-access-permissions';
        //             });
        //             if (index != -1) {
        //                 accessPermissionsTab = item.items.splice(index, 1);
        //                 accessPermissionsTab && item.items.push(accessPermissionsTab);
        //             }
        //         }
        //     }
        //     fields.push(item);
        // }
        //
        // return fields;
    },

    getGallery: function (config) {
        return {
            title: _('ms_tab_product_gallery'),
            disabled: true,
            listeners: {
                afterrender: function (p) {
                    Ext.get(p.tabEl).on('click', function () {
                        MODx.msg.alert(_('warning'), _('ms_gallery_unavailablemsg'));
                    });
                }
            }
        };
    },
});
Ext.reg('minishop-panel-product-create', minishop.panel.CreateProduct);
