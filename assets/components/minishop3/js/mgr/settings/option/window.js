ms3.window.CreateOption = function (config) {
    config = config || {};

    Ext.applyIf(config, {
        title: _('ms3_menu_create'),
        width: 800,
        baseParams: {
            action: 'MiniShop3\\Processors\\Settings\\Option\\Create',
        },
    });
    ms3.window.CreateOption.superclass.constructor.call(this, config);

    this.on('success', function () {
        const c = Ext.getCmp('ms3-grid-option-modcategory');
        if (c) {
            c.getStore().load();
        }
    });
};
Ext.extend(ms3.window.CreateOption, ms3.window.Default, {

    getFields: function (config) {
        return [{
            layout: 'column',
            items: [{
                columnWidth: .3,
                items: [
                    this.getTree(config)
                ]
            }, {
                columnWidth: .7,
                layout: 'form',
                items: this.getForm(config)
            }]
        }];
    },

    getTree: function (config) {
        return [{
            xtype: 'ms3-tree-option-categories',
            id: config.id + '-option-categories',
            categories: config.record['categories'] || '',
            maxHeight: 320,
            listeners: {
                checkchange: function (node, checked) {
                    const catField = Ext.getCmp(config.id + '-categories');
                    if (node && catField) {
                        let value;
                        if (catField.getValue() == '[]') {
                            value = {};
                        } else {
                            value = Ext.util.JSON.decode(catField.getValue());
                        }
                        value[node.attributes.pk] = Number(checked);
                        catField.setValue(Ext.util.JSON.encode(value));
                    }
                }
            }
        }];
    },

    getForm: function (config) {
        return [
            {xtype: 'hidden', name: 'id', id: config.id + '-id'},
            {xtype: 'hidden', name: 'categories', id: config.id + '-categories'},
            {
                layout: 'column',
                items: [{
                    columnWidth: .5,
                    layout: 'form',
                    defaults: {msgTarget: 'under'},
                    items: [{
                        xtype: 'textfield',
                        fieldLabel: _('ms3_ft_name'),
                        name: 'key',
                        //allowBlank: false,
                        anchor: '99%',
                        id: config.id + '-name'
                    }]
                }, {
                    columnWidth: .5,
                    layout: 'form',
                    defaults: {msgTarget: 'under'},
                    items: [{
                        xtype: 'textfield',
                        fieldLabel: _('ms3_ft_caption'),
                        name: 'caption',
                        //allowBlank: false,
                        anchor: '99%',
                        id: config.id + '-caption'
                    }]
                }]
        }, {
            xtype: 'ms3-combo-option-types',
            anchor: '99%',
            id: config.id + '-types',
            listeners: {
                select: {fn: this.onSelectType, scope: this},
            }
        }, {
            xtype: 'panel',
            anchor: '99%',
            id: config.id + '-properties-panel',
        }, {
            layout: 'column',
            items: [{
                columnWidth: .5,
                layout: 'form',
                defaults: {msgTarget: 'under'},
                items: [{
                    xtype: 'textfield',
                    fieldLabel: _('ms3_ft_measure_unit'),
                    name: 'measure_unit',
                    allowBlank: true,
                    anchor: '99%',
                    id: config.id + '-measure-unit',
                }]
            }, {
                columnWidth: .5,
                layout: 'form',
                defaults: {msgTarget: 'under'},
                items: [{
                    xtype: 'modx-combo-category',
                    fieldLabel: _('ms3_ft_group'),
                    name: 'category',
                    anchor: '99%',
                    id: config.id + '-category',
                }]
            }]
        }, {
            xtype: 'textarea',
            fieldLabel: _('ms3_ft_description'),
            name: 'description',
            anchor: '99%',
            id: config.id + '-description'
        }
        ];
    },

    onSelectType: function (combo, row) {
        const panel = Ext.getCmp(this.config.id + '-properties-panel');
        if (panel) {
            panel.getEl().update('');
        }
        if (!row.data || !row.data['xtype']) {
            return;
        }

        MODx.load({
            xtype: row.data['xtype'],
            renderTo: this.config.id + '-properties-panel',
            record: this.record,
            name: 'properties',
        });
    },
});
Ext.reg('ms3-window-option-create', ms3.window.CreateOption);


ms3.window.UpdateOption = function (config) {
    config = config || {};

    Ext.applyIf(config, {
        title: _('ms3_menu_update'),
        baseParams: {
            action: 'MiniShop3\\Processors\\Settings\\Option\\Update',
        }
    });
    ms3.window.UpdateOption.superclass.constructor.call(this, config);
};
Ext.extend(ms3.window.UpdateOption, ms3.window.CreateOption);
Ext.reg('ms3-window-option-update', ms3.window.UpdateOption);


ms3.window.AssignOptions = function (config) {
    config = config || {};

    Ext.applyIf(config, {
        title: _('ms3_category_options_assign'),
        width: 600,
        baseParams: {
            action: 'MiniShop3\\Processors\\Settings\\Option\\Multiple',
            method: 'assign',
        }
    });
    ms3.window.AssignOptions.superclass.constructor.call(this, config);
};
Ext.extend(ms3.window.AssignOptions, ms3.window.Default, {

    getFields: function (config) {
        return [
            {xtype: 'hidden', name: 'options', id: config.id + '-options'},
            {xtype: 'hidden', name: 'categories', id: config.id + '-categories'},
            {
                xtype: 'ms3-tree-option-categories',
                id: config.id + '-assign-tree',
                options: config['options'] || '',
                listeners: {
                    checkchange: function () {
                        const nodes = this.getChecked();
                        const categories = [];
                        for (let i = 0; i < nodes.length; i++) {
                            categories.push(nodes[i].attributes.pk);
                        }

                        const catField = Ext.getCmp(config.id + '-categories');
                        if (catField) {
                            catField.setValue(Ext.util.JSON.encode(categories));
                        }
                    }
                }
        }
        ];
    }

});
Ext.reg('ms3-window-option-assign', ms3.window.AssignOptions);
