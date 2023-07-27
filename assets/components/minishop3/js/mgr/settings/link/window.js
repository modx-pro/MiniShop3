ms3.window.CreateLink = function (config) {
    config = config || {};

    Ext.applyIf(config, {
        title: _('ms3_menu_create'),
        width: 600,
        baseParams: {
            action: 'MiniShop3\\Processors\\Settings\\Link\\Create',
        },
    });
    ms3.window.CreateLink.superclass.constructor.call(this, config);
};
Ext.extend(ms3.window.CreateLink, ms3.window.Default, {

    getFields: function (config) {
        return [
            {xtype: 'hidden', name: 'id', id: config.id + '-id'},
            {
                xtype: 'textfield',
                fieldLabel: _('ms3_name'),
                name: 'name',
                anchor: '99%',
                id: config.id + '-name'
        }, {
            xtype: 'ms3-combo-link-type',
            fieldLabel: _('ms3_type'),
            name: 'type',
            anchor: '99%',
            id: config.id + '-type',
            listeners: {
                select: {
                    fn: function (combo) {
                        this.handleLinkFields(combo);
                    }, scope: this
                },
                afterrender: {
                    fn: function (combo) {
                        this.handleLinkFields(combo);
                    }, scope: this
                }
            },
            disabled: config.mode === 'update'
        }, {
            xtype: 'displayfield',
            hideLabel: true,
            cls: 'desc',
            id: config.id + '-type-desc'
        }, {
            xtype: 'textarea',
            fieldLabel: _('ms3_description'),
            name: 'description',
            anchor: '99%',
            id: config.id + '-description'
        }
        ];
    },

    handleLinkFields: function (combo) {
        const value = combo.getValue();
        if (value) {
            const desc = Ext.getCmp(this.config.id + '-type-desc');
            if (desc) {
                desc.setValue(_('ms3_link_' + value + '_desc'));
            }
        }
    },

});
Ext.reg('ms3-window-link-create', ms3.window.CreateLink);


ms3.window.UpdateLink = function (config) {
    config = config || {};

    Ext.applyIf(config, {
        title: _('ms3_menu_update'),
        baseParams: {
            action: 'MiniShop3\\Processors\\Settings\\Link\\Update',
        }
    });
    ms3.window.UpdateLink.superclass.constructor.call(this, config);
};
Ext.extend(ms3.window.UpdateLink, ms3.window.CreateLink, {

    getFields: function (config) {
        const fields = ms3.window.CreateLink.prototype.getFields.call(this, config);

        for (const i in fields) {
            if (!fields.hasOwnProperty(i)) {
                continue;
            }
            const field = fields[i];
            if (field.name === 'type') {
                field.disabled = true;
            }
        }

        return fields;
    }

});
Ext.reg('ms3-window-link-update', ms3.window.UpdateLink);
