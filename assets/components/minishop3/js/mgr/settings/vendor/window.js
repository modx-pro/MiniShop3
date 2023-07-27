ms3.window.CreateVendor = function (config) {
    config = config || {};

    Ext.applyIf(config, {
        title: _('ms3_menu_create'),
        width: 600,
        baseParams: {
            action: 'MiniShop3\\Processors\\Settings\\Vendor\\Create',
        }
    });
    ms3.window.CreateVendor.superclass.constructor.call(this, config);
};
Ext.extend(ms3.window.CreateVendor, ms3.window.Default, {

    getFields: function (config) {
        return [
            {xtype: 'hidden', name: 'id', id: config.id + '-id'},
            {
                layout: 'column',
                items: [{
                    columnWidth: .6,
                    layout: 'form',
                    defaults: {msgTarget: 'under'},
                    items: [{
                        xtype: 'textfield',
                        fieldLabel: _('ms3_name'),
                        name: 'name',
                        anchor: '99%',
                        id: config.id + '-name'
                    }],
                }, {
                    columnWidth: .4,
                    layout: 'form',
                    items: [{
                        xtype: 'textfield',
                        fieldLabel: _('ms3_country'),
                        name: 'country',
                        anchor: '99%',
                        id: config.id + '-country'
                    }],
                }]
            }, {
                layout: 'column',
                items: [{
                    columnWidth: .4,
                    layout: 'form',
                    items: [{
                        xtype: 'textfield',
                        fieldLabel: _('ms3_email'),
                        name: 'email',
                        anchor: '99%',
                        id: config.id + '-email'
                    }],
                }, {
                    columnWidth: .6,
                    layout: 'form',
                    items: [{
                        xtype: 'ms3-combo-resource',
                        fieldLabel: _('ms3_resource'),
                        name: 'resource',
                        anchor: '99%',
                        id: config.id + '-resource'
                    }],
                }]
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
                fieldLabel: _('ms3_address'),
                name: 'address',
                anchor: '99%',
                id: config.id + '-address'
            }, {
                layout: 'column',
                items: [{
                    columnWidth: .5,
                    layout: 'form',
                    items: [{
                        xtype: 'textfield',
                        fieldLabel: _('ms3_phone'),
                        name: 'phone',
                        anchor: '99%',
                        id: config.id + '-phone'
                    }],
                }, {
                    columnWidth: .5,
                    layout: 'form',
                    items: [{
                        xtype: 'textfield',
                        fieldLabel: _('ms3_fax'),
                        name: 'fax',
                        anchor: '99%',
                        id: config.id + '-fax'
                    }],
                }]
            }, {
                xtype: 'textarea',
                fieldLabel: _('ms3_description'),
                name: 'description',
                anchor: '99%',
                id: config.id + '-description'
            }
        ];
    }

});
Ext.reg('ms3-window-vendor-create', ms3.window.CreateVendor);


ms3.window.UpdateVendor = function (config) {
    config = config || {};

    Ext.applyIf(config, {
        title: _('ms3_menu_update'),
        baseParams: {
            action: 'MiniShop3\\Processors\\Settings\\Vendor\\Update',
        },
    });
    ms3.window.UpdateVendor.superclass.constructor.call(this, config);
};
Ext.extend(ms3.window.UpdateVendor, ms3.window.CreateVendor);
Ext.reg('ms3-window-vendor-update', ms3.window.UpdateVendor);
