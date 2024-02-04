ms3.window.CreateDelivery = function (config) {
    config = config || {};

    Ext.applyIf(config, {
        title: _('ms3_delivery'),
        width: 600,
        baseParams: {
            action: 'MiniShop3\\Processors\\Settings\\Delivery\\Create',
        },
    });
    ms3.window.CreateDelivery.superclass.constructor.call(this, config);
};
Ext.extend(ms3.window.CreateDelivery, ms3.window.Default, {

    getFields: function (config) {
        return [{
            xtype: 'hidden',
            name: 'id',
            id: config.id + '-id'
        }, {
            xtype: 'textfield',
            fieldLabel: _('ms3_name'),
            name: 'name',
            anchor: '99%',
            id: config.id + '-name'
        }, {
            xtype: 'textarea',
            fieldLabel: _('ms3_description'),
            name: 'description',
            anchor: '99%',
            id: config.id + '-description'
        }, {
            xtype: 'ms3-combo-browser',
            fieldLabel: _('ms3_logo'),
            name: 'logo',
            anchor: '99%',
            id: config.id + '-logo',
            triggerClass: 'x-form-image-trigger',
            allowedFileTypes: config.allowedFileTypes || MODx.config.upload_images
        }, {
            xtype: 'xcheckbox',
            boxLabel: _('ms3_active'),
            hideLabel: true,
            name: 'active',
            id: config.id + '-active'
        }];
    },
});
Ext.reg('ms3-window-delivery-create', ms3.window.CreateDelivery);


ms3.window.UpdateDelivery = function (config) {
    config = config || {};

    Ext.applyIf(config, {
        baseParams: {
            action: 'MiniShop3\\Processors\\Settings\\Delivery\\Update',
        },
        bodyCssClass: 'tabs',
    });
    ms3.window.UpdateDelivery.superclass.constructor.call(this, config);
};
Ext.extend(ms3.window.UpdateDelivery, ms3.window.CreateDelivery, {

    getFields: function (config) {
        return [{
            xtype: 'modx-tabs',
            items: [{
                title: _('ms3_delivery'),
                layout: 'form',
                items: ms3.window.CreateDelivery.prototype.getFields.call(this, config),
            },{
                title: _('ms3_settings'),
                layout: 'form',
                items: [ {
                    xtype: 'ms3-combo-classes',
                    type: 'delivery',
                    fieldLabel: _('ms3_class'),
                    name: 'class',
                    anchor: '99%',
                    id: config.id + '-class'
                }, {
                    xtype: 'textarea',
                    fieldLabel: _('ms3_order_validation_rules'),
                    description: _('ms3_order_validation_rules_help'),
                    name: 'validation_rules',
                    anchor: '99%',
                    id: config.id + '-validation_rules'
                },{
                    layout: 'column',
                    items: [{
                        columnWidth: .5,
                        layout: 'form',
                        defaults: {msgTarget: 'under'},
                        items: [
                            {
                                xtype: 'textfield',
                                fieldLabel: _('ms3_add_cost'),
                                name: 'price',
                                description: _('ms3_add_cost_help'),
                                anchor: '99%',
                                id: config.id + '-price'
                            }, {
                                xtype: 'numberfield',
                                fieldLabel: _('ms3_free_delivery_amount'),
                                name: 'free_delivery_amount',
                                description: _('ms3_free_delivery_amount_help'),
                                anchor: '99%',
                                decimalPrecision: 2,
                                id: config.id + '-free-delivery-amount'
                            }
                        ]
                    }, {
                        columnWidth: .5,
                        layout: 'form',
                        defaults: {msgTarget: 'under'},
                        pack: 'center',
                        items: [
                            {
                                xtype: 'numberfield',
                                fieldLabel: _('ms3_weight_price'),
                                description: _('ms3_weight_price_help'),
                                name: 'weight_price',
                                decimalPrecision: 2,
                                anchor: '99%',
                                id: config.id + '-weight-price'
                            }, {
                                xtype: 'numberfield',
                                fieldLabel: _('ms3_distance_price'),
                                description: _('ms3_distance_price_help'),
                                name: 'distance_price',
                                decimalPrecision: 2,
                                anchor: '99%',
                                id: config.id + '-distance-price'
                            }
                        ]
                    }]
                }],
            }, {
                title: _('ms3_payments'),
                items: [{
                    xtype: 'ms3-grid-delivery-payments',
                    record: config.record,
                }]
            }]
        }];
    }

});
Ext.reg('ms3-window-delivery-update', ms3.window.UpdateDelivery);
