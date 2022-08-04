minishop.window.CreateProductLink = function (config) {
    config = config || {};

    Ext.applyIf(config, {
        title: _('ms_link'),
        width: 600,
        success: this.success,
        baseParams: {
            action: 'MiniShop3\\Processors\\Product\\ProductLink\\Create',
        },
        fields: config.fields,
    });
    minishop.window.CreateProductLink.superclass.constructor.call(this, config);
};
Ext.extend(minishop.window.CreateProductLink, minishop.window.Default, {

    getFields: function (config) {
        return [{
            xtype: 'minishop-combo-link',
            id: config.id + '-link',
            fieldLabel: _('ms_link'),
            name: 'link',
            allowBlank: false,
            anchor: '99%',
        }, {
            xtype: 'minishop-combo-product',
            id: config.id + '-product',
            fieldLabel: _('ms_product'),
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
Ext.reg('minishop-product-link-create', minishop.window.CreateProductLink);
