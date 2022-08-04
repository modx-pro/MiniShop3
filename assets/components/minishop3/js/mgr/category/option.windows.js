minishop.window.AddOption = function (config) {
    config = config || {};

    Ext.applyIf(config, {
        title: _('ms_category_option_add'),
        width: 600,
        baseParams: {
            action: 'MiniShop3\\Processors\\Category\\Option\\Add',
        },
    });
    minishop.window.AddOption.superclass.constructor.call(this, config);
};
Ext.extend(minishop.window.AddOption, minishop.window.Default, {
    getFields: function () {
        return [
            {xtype: 'hidden', name: 'category_id'},
            {
                xtype: 'minishop-combo-extra-options',
                anchor: '99%',
                name: 'option_id',
                hiddenName: 'option_id'
        }, {
            xtype: 'textfield',
            anchor: '99%',
            name: 'value',
            fieldLabel: _('ms_default_value')
        }, {
            xtype: 'checkboxgroup',
            fieldLabel: _('ms_options'),
            columns: 1,
            items: [
                {xtype: 'xcheckbox', boxLabel: _('ms_active'), name: 'active'},
                {xtype: 'xcheckbox', boxLabel: _('ms_required'), name: 'required'}
            ]
        }
        ];
    },

    getKeys: function () {
        return [{
            key: Ext.EventObject.ENTER,
            shift: true,
            fn: function () {
                this.submit()
            },
            scope: this
        }];
    }
});
Ext.reg('minishop-window-option-add', minishop.window.AddOption);


minishop.window.CopyCategory = function (config) {
    config = config || {};

    Ext.applyIf(config, {
        title: _('ms_category_option_copy'),
        width: 600,
        baseParams: {
            action: 'MiniShop3\\Processors\\Category\\Option\\Duplicate',
        },
    });
    minishop.window.CopyCategory.superclass.constructor.call(this, config);
};
Ext.extend(minishop.window.CopyCategory, minishop.window.Default, {

    getFields: function () {
        return [
            {xtype: 'hidden', name: 'category_to'},
            {
                xtype: 'minishop-combo-category',
                anchor: '99%',
                name: 'category_from',
                hiddenName: 'category_from'
        }
        ];
    },

    getKeys: function () {
        return [{
            key: Ext.EventObject.ENTER,
            shift: true,
            fn: function () {
                this.submit()
            },
            scope: this
        }];
    }
});
Ext.reg('minishop-window-copy-category', minishop.window.CopyCategory);
