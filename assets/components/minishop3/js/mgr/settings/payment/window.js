ms3.window.CreatePayment = function (config) {
    config = config || {};

    Ext.applyIf(config, {
        title: _('ms3_payment'),
        width: 600,
        baseParams: {
            action: 'MiniShop3\\Processors\\Settings\\Payment\\Create',
        },
    });
    ms3.window.CreatePayment.superclass.constructor.call(this, config);
};
Ext.extend(ms3.window.CreatePayment, ms3.window.Default, {

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
                    fieldLabel: _('ms3_name'),
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
                    fieldLabel: _('ms3_add_cost'),
                    name: 'price',
                    description: _('ms3_add_cost_help'),
                    anchor: '99%',
                    id: config.id + '-price'
                }],
            }]

        }, {
            columnWidth: .5,
            layout: 'form',
            defaults: {msgTarget: 'under'},
            items: [{
                xtype: 'ms3-combo-classes',
                type: 'payment',
                fieldLabel: _('ms3_class'),
                name: 'class',
                anchor: '99%',
                id: config.id + '-class',
            }],
        }, {
            xtype: 'ms3-combo-browser',
            fieldLabel: _('ms3_logo'),
            name: 'logo',
            anchor: '99%',
            id: config.id + '-logo',
            triggerClass: 'x-form-image-trigger',
            allowedFileTypes: config.allowedFileTypes || MODx.config.upload_images
        }, {
            xtype: 'textarea',
            fieldLabel: _('ms3_description'),
            name: 'description',
            anchor: '99%',
            id: config.id + '-description'
        }, {
            xtype: 'xcheckbox',
            boxLabel: _('ms3_active'),
            hideLabel: true,
            name: 'active',
            id: config.id + '-active'
        }];
    },
});
Ext.reg('ms3-window-payment-create', ms3.window.CreatePayment);


ms3.window.UpdatePayment = function (config) {
    config = config || {};

    Ext.applyIf(config, {
        baseParams: {
            action: 'MiniShop3\\Processors\\Settings\\Payment\\Update',
        },
        bodyCssClass: 'tabs',
    });
    ms3.window.UpdatePayment.superclass.constructor.call(this, config);
};
Ext.extend(ms3.window.UpdatePayment, ms3.window.CreatePayment, {

    getFields: function (config) {
        return [{
            xtype: 'modx-tabs',
            items: [{
                title: _('ms3_payment'),
                layout: 'form',
                items: ms3.window.CreatePayment.prototype.getFields.call(this, config),
            }, {
                title: _('ms3_deliveries'),
                items: [{
                    xtype: 'ms3-grid-payment-deliveries',
                    record: config.record,
                }]
            }]
        }];
    }

});
Ext.reg('ms3-window-payment-update', ms3.window.UpdatePayment);
