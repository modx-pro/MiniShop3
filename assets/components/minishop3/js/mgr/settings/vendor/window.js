minishop.window.CreateVendor = function (config) {
    config = config || {};

    Ext.applyIf(config, {
        title: _('ms_menu_create'),
        width: 600,
        baseParams: {
            action: 'MiniShop3\\Processors\\Settings\\Vendor\\Create',
        }
    });
    minishop.window.CreateVendor.superclass.constructor.call(this, config);
};
Ext.extend(minishop.window.CreateVendor, minishop.window.Default, {

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
                        fieldLabel: _('ms_name'),
                        name: 'name',
                        anchor: '99%',
                        id: config.id + '-name'
                    }],
                }, {
                    columnWidth: .4,
                    layout: 'form',
                    items: [{
                        xtype: 'textfield',
                        fieldLabel: _('ms_country'),
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
                        fieldLabel: _('ms_email'),
                        name: 'email',
                        anchor: '99%',
                        id: config.id + '-email'
                    }],
                }, {
                    columnWidth: .6,
                    layout: 'form',
                    items: [{
                        xtype: 'minishop-combo-resource',
                        fieldLabel: _('ms_resource'),
                        name: 'resource',
                        anchor: '99%',
                        id: config.id + '-resource'
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
                fieldLabel: _('ms_address'),
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
                        fieldLabel: _('ms_phone'),
                        name: 'phone',
                        anchor: '99%',
                        id: config.id + '-phone'
                    }],
                }, {
                    columnWidth: .5,
                    layout: 'form',
                    items: [{
                        xtype: 'textfield',
                        fieldLabel: _('ms_fax'),
                        name: 'fax',
                        anchor: '99%',
                        id: config.id + '-fax'
                    }],
                }]
            }, {
                xtype: 'textarea',
                fieldLabel: _('ms_description'),
                name: 'description',
                anchor: '99%',
                id: config.id + '-description'
            }
        ];
    }

});
Ext.reg('minishop-window-vendor-create', minishop.window.CreateVendor);


minishop.window.UpdateVendor = function (config) {
    config = config || {};

    Ext.applyIf(config, {
        title: _('ms_menu_update'),
        baseParams: {
            action: 'MiniShop3\\Processors\\Settings\\Vendor\\Update',
        },
    });
    minishop.window.UpdateVendor.superclass.constructor.call(this, config);
};
Ext.extend(minishop.window.UpdateVendor, minishop.window.CreateVendor);
Ext.reg('minishop-window-vendor-update', minishop.window.UpdateVendor);
