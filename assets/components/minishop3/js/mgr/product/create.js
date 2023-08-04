ms3.page.CreateProduct = function (config) {
    config = config || {record: {}};
    config.record = config.record || {};

    Ext.applyIf(config, {
        panelXType: 'ms3-panel-product-create',
        mode: 'create',
        url: ms3.config.connector_url
    });
    ms3.page.CreateProduct.superclass.constructor.call(this, config);
};
Ext.extend(ms3.page.CreateProduct, MODx.page.CreateResource, {

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
Ext.reg('ms3-page-product-create', ms3.page.CreateProduct);


ms3.panel.CreateProduct = function (config) {
    config = config || {};
    ms3.panel.CreateProduct.superclass.constructor.call(this, config);
};
Ext.extend(ms3.panel.CreateProduct, ms3.panel.Product, {
    formatMainPanelTitle(formId, record, realtimeValue = null, returnBaseTitle = false) {

    },
    getFields: function (config) {
        const fields = [];
        const originals = ms3.panel.Product.prototype.getFields.call(this, config);
        for (const i in originals) {
            if (!originals.hasOwnProperty(i)) {
                continue;
            }
            const item = originals[i];
            fields.push(item);
        }
        console.log(fields)
        return fields;
    },

    getGallery: function (config) {
        return {
            title: _('ms3_tab_product_gallery'),
            disabled: true,
            listeners: {
                afterrender: function (p) {
                    Ext.get(p.tabEl).on('click', function () {
                        MODx.msg.alert(_('warning'), _('ms3_gallery_unavailablemsg'));
                    });
                }
            }
        };
    },
});
Ext.reg('ms3-panel-product-create', ms3.panel.CreateProduct);
