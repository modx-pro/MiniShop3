minishop.window.CreateLink = function (config) {
    config = config || {};

    Ext.applyIf(config, {
        title: _('ms_menu_create'),
        width: 600,
        baseParams: {
            action: 'MiniShop3\\Processors\\Settings\\Link\\Create',
        },
    });
    minishop.window.CreateLink.superclass.constructor.call(this, config);
};
Ext.extend(minishop.window.CreateLink, minishop.window.Default, {

    getFields: function (config) {
        return [
            {xtype: 'hidden', name: 'id', id: config.id + '-id'},
            {
                xtype: 'textfield',
                fieldLabel: _('ms_name'),
                name: 'name',
                anchor: '99%',
                id: config.id + '-name'
        }, {
            xtype: 'minishop-combo-link-type',
            fieldLabel: _('ms_type'),
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
            fieldLabel: _('ms_description'),
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
                desc.setValue(_('ms_link_' + value + '_desc'));
            }
        }
    },

});
Ext.reg('minishop-window-link-create', minishop.window.CreateLink);


minishop.window.UpdateLink = function (config) {
    config = config || {};

    Ext.applyIf(config, {
        title: _('ms_menu_update'),
        baseParams: {
            action: 'MiniShop3\\Processors\\Settings\\Link\\Update',
        }
    });
    minishop.window.UpdateLink.superclass.constructor.call(this, config);
};
Ext.extend(minishop.window.UpdateLink, minishop.window.CreateLink, {

    getFields: function (config) {
        const fields = minishop.window.CreateLink.prototype.getFields.call(this, config);

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
Ext.reg('minishop-window-link-update', minishop.window.UpdateLink);
