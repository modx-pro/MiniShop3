minishop.window.CreateDelivery = function (config) {
    config = config || {};

    Ext.applyIf(config, {
        title: _('ms_delivery'),
        width: 600,
        baseParams: {
            action: 'MiniShop3\\Processors\\Settings\\Delivery\\Create',
        },
    });
    minishop.window.CreateDelivery.superclass.constructor.call(this, config);
};
Ext.extend(minishop.window.CreateDelivery, minishop.window.Default, {

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
            xtype: 'numberfield',
            fieldLabel: _('ms_free_delivery_amount'),
            name: 'free_delivery_amount',
            description: _('ms_free_delivery_amount_help'),
            anchor: '99%',
            decimalPrecision: 2,
            id: config.id + '-free-delivery-amount'
        }, {
            layout: 'column',
            items: [{
                columnWidth: .5,
                layout: 'form',
                defaults: {msgTarget: 'under'},
                items: [{
                    xtype: 'numberfield',
                    fieldLabel: _('ms_weight_price'),
                    description: _('ms_weight_price_help'),
                    name: 'weight_price',
                    decimalPrecision: 2,
                    anchor: '99%',
                    id: config.id + '-weight-price'
                }, {
                    xtype: 'textfield',
                    fieldLabel: _('ms_order_requires'),
                    description: _('ms_order_requires_help'),
                    name: 'requires',
                    anchor: '99%',
                    id: config.id + '-requires'
                }]
            }, {
                columnWidth: .5,
                layout: 'form',
                defaults: {msgTarget: 'under'},
                items: [{
                    xtype: 'numberfield',
                    fieldLabel: _('ms_distance_price'),
                    description: _('ms_distance_price_help'),
                    name: 'distance_price',
                    decimalPrecision: 2,
                    anchor: '99%',
                    id: config.id + '-distance-price'
                }, {
                    xtype: 'minishop-combo-classes',
                    type: 'delivery',
                    fieldLabel: _('ms_class'),
                    name: 'class',
                    anchor: '99%',
                    id: config.id + '-class'
                }],
            }]
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
Ext.reg('minishop-window-delivery-create', minishop.window.CreateDelivery);


minishop.window.UpdateDelivery = function (config) {
    config = config || {};

    Ext.applyIf(config, {
        baseParams: {
            action: 'MiniShop3\\Processors\\Settings\\Delivery\\Update',
        },
        bodyCssClass: 'tabs',
    });
    minishop.window.UpdateDelivery.superclass.constructor.call(this, config);
};
Ext.extend(minishop.window.UpdateDelivery, minishop.window.CreateDelivery, {

    getFields: function (config) {
        return [{
            xtype: 'modx-tabs',
            items: [{
                title: _('ms_delivery'),
                layout: 'form',
                items: minishop.window.CreateDelivery.prototype.getFields.call(this, config),
            }, {
                title: _('ms_payments'),
                items: [{
                    xtype: 'minishop-grid-delivery-payments',
                    record: config.record,
                }]
            }]
        }];
    }

});
Ext.reg('minishop-window-delivery-update', minishop.window.UpdateDelivery);
