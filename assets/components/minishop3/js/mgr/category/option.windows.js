ms3.window.AddOption = function (config) {
    config = config || {};

    Ext.applyIf(config, {
        title: _('ms3_category_option_add'),
        width: 600,
        baseParams: {
            action: 'MiniShop3\\Processors\\Category\\Option\\Add',
        },
    });
    ms3.window.AddOption.superclass.constructor.call(this, config);
};
Ext.extend(ms3.window.AddOption, ms3.window.Default, {
    getFields: function () {
        return [
            {xtype: 'hidden', name: 'category_id'},
            {
                xtype: 'ms3-combo-extra-options',
                anchor: '99%',
                name: 'option_id',
                hiddenName: 'option_id'
        }, {
            xtype: 'textfield',
            anchor: '99%',
            name: 'value',
            fieldLabel: _('ms3_default_value')
        }, {
            xtype: 'checkboxgroup',
            fieldLabel: _('ms3_options'),
            columns: 1,
            items: [
                {xtype: 'xcheckbox', boxLabel: _('ms3_active'), name: 'active'},
                {xtype: 'xcheckbox', boxLabel: _('ms3_required'), name: 'required'}
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
Ext.reg('ms3-window-option-add', ms3.window.AddOption);


ms3.window.CopyCategory = function (config) {
    config = config || {};

    Ext.applyIf(config, {
        title: _('ms3_category_option_copy'),
        width: 600,
        baseParams: {
            action: 'MiniShop3\\Processors\\Category\\Option\\Duplicate',
        },
    });
    ms3.window.CopyCategory.superclass.constructor.call(this, config);
};
Ext.extend(ms3.window.CopyCategory, ms3.window.Default, {

    getFields: function () {
        return [
            {xtype: 'hidden', name: 'category_to'},
            {
                xtype: 'ms3-combo-category',
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
Ext.reg('ms3-window-copy-category', ms3.window.CopyCategory);
