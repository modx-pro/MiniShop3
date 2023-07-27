ms3.window.CreateProductLink = function (config) {
    config = config || {};

    Ext.applyIf(config, {
        title: _('ms3_link'),
        width: 600,
        success: this.success,
        baseParams: {
            action: 'MiniShop3\\Processors\\Product\\ProductLink\\Create',
        },
        fields: config.fields,
    });
    ms3.window.CreateProductLink.superclass.constructor.call(this, config);
};
Ext.extend(ms3.window.CreateProductLink, ms3.window.Default, {

    getFields: function (config) {
        return [{
            xtype: 'ms3-combo-link',
            id: config.id + '-link',
            fieldLabel: _('ms3_link'),
            name: 'link',
            allowBlank: false,
            anchor: '99%',
        }, {
            xtype: 'ms3-combo-product',
            id: config.id + '-product',
            fieldLabel: _('ms3_product'),
            name: 'slave',
            hiddenName: 'slave',
            allowBlank: false,
            anchor: '99%',
        }];
    },

    getButtons: function () {
        return [{
            text: _('close'),
            scope: this,
            handler: function () {
                this.hide();
            }
        }, {
            text: _('save'),
            cls: 'primary-button',
            scope: this,
            handler: function () {
                this.submit(false);
            }
        }, {
            text: _('save_and_close'),
            cls: 'primary-button',
            scope: this,
            handler: this.submit
        }];
    },

    success: function () {
        const product = Ext.getCmp(this.id + '-product');
        if (product) {
            product.clearValue();
        }
    },

});
Ext.reg('ms3-product-link-create', ms3.window.CreateProductLink);
