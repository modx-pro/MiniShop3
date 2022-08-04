minishop.window.CreatePayment = function (config) {
    config = config || {};

    Ext.applyIf(config, {
        title: _('ms_payment'),
        width: 600,
        baseParams: {
            action: 'MiniShop3\\Processors\\Settings\\Payment\\Create',
        },
    });
    minishop.window.CreatePayment.superclass.constructor.call(this, config);
};
Ext.extend(minishop.window.CreatePayment, minishop.window.Default, {

    getFields: function (config) {
        return [{
            xtype: 'hidden',
            name: 'id',
            id: config.id + '-id'
        }, {
            layout: 'column',
            items: [{
                columnWidth: .7,
                layout: 'form',
                defaults: {msgTarget: 'under'},
                items: [{
                    xtype: 'textfield',
                    fieldLabel: _('ms_name'),
                    name: 'name',
                    anchor: '99%',
                    id: config.id + '-name'
                }]
            }, {
                columnWidth: .3,
                layout: 'form',
                defaults: {msgTarget: 'under'},
                items: [{
                    xtype: 'textfield',
                    fieldLabel: _('ms_add_cost'),
                    name: 'price',
                    description: _('ms_add_cost_help'),
                    anchor: '99%',
                    id: config.id + '-price'
                }],
            }]

        }, {
            columnWidth: .5,
            layout: 'form',
            defaults: {msgTarget: 'under'},
            items: [{
                xtype: 'minishop-combo-classes',
                type: 'payment',
                fieldLabel: _('ms_class'),
                name: 'class',
                anchor: '99%',
                id: config.id + '-class',
            }],
        }, {
            xtype: 'minishop-combo-browser',
            fieldLabel: _('ms_logo'),
            name: 'logo',
            anchor: '99%',
            id: config.id + '-logo',
            triggerClass: 'x-form-image-trigger',
            allowedFileTypes: config.allowedFileTypes || MODx.config.upload_images
        }, {
            xtype: 'textarea',
            fieldLabel: _('ms_description'),
            name: 'description',
            anchor: '99%',
            id: config.id + '-description'
        }, {
            xtype: 'xcheckbox',
            boxLabel: _('ms_active'),
            hideLabel: true,
            name: 'active',
            id: config.id + '-active'
        }];
    },
});
Ext.reg('minishop-window-payment-create', minishop.window.CreatePayment);


minishop.window.UpdatePayment = function (config) {
    config = config || {};

    Ext.applyIf(config, {
        baseParams: {
            action: 'MiniShop3\\Processors\\Settings\\Payment\\Update',
        },
        bodyCssClass: 'tabs',
    });
    minishop.window.UpdatePayment.superclass.constructor.call(this, config);
};
Ext.extend(minishop.window.UpdatePayment, minishop.window.CreatePayment, {

    getFields: function (config) {
        return [{
            xtype: 'modx-tabs',
            items: [{
                title: _('ms_payment'),
                layout: 'form',
                items: minishop.window.CreatePayment.prototype.getFields.call(this, config),
            }, {
                title: _('ms_deliveries'),
                items: [{
                    xtype: 'minishop-grid-payment-deliveries',
                    record: config.record,
                }]
            }]
        }];
    }

});
Ext.reg('minishop-window-payment-update', minishop.window.UpdatePayment);
