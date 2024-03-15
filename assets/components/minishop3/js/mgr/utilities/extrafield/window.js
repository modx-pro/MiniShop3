ms3.window.CreateExtraField = function (config) {
    config = config || {};

    Ext.applyIf(config, {
        title: _('ms3_menu_create'),
        width: 600,
        baseParams: {
            action: 'MiniShop3\\Processors\\Utilities\\ExtraField\\Create',
        },
    });
    ms3.window.CreateExtraField.superclass.constructor.call(this, config);
};

Ext.extend(ms3.window.CreateExtraField, ms3.window.Default, {

    getFields: function (config) {
        const existsInDatabase = (config.record !== undefined) ? config.record.exists : false;
        return [
            {
                xtype: 'hidden',
                name: 'id',
                id: config.id + '-id'
            },{
                xtype: 'ms3-combo-combobox-default',
                fieldLabel: 'class',//_('ms3_class'),
                name: 'class',
                hiddenName: 'class',
                anchor: '99%',
                id: config.id + '-class',
                allowBlank: true,
                disabled: existsInDatabase,
                mode: 'local',
                displayField: 'class',
                valueField: 'class',
                store: new Ext.data.ArrayStore({
                    id: 0,
                    fields: ['class'],
                    data: [
                        ['MiniShop3\\Model\\msProductData'],
                        ['MiniShop3\\Model\\msVendor']
                    ]
                }),
            }, {
                layout: 'column',
                items: [{
                    columnWidth: .33,
                    layout: 'form',
                    defaults: { msgTarget: 'under' },
                    items: [{
                        xtype: 'textfield',
                        fieldLabel: 'key',//_('ms3_key'),
                        name: 'key',
                        anchor: '99%',
                        id: config.id + '-key',
                        allowBlank: true,
                        disabled: existsInDatabase
                    }],
                }, {
                    columnWidth: .33,
                    layout: 'form',
                    items: [{
                        xtype: 'textfield',
                        fieldLabel: 'label',//_('ms3_key'),
                        name: 'label',
                        anchor: '99%',
                        id: config.id + '-label',
                        allowBlank: true,
                        disabled: existsInDatabase
                    }],
                }, {
                    columnWidth: .33,
                    layout: 'form',
                    items: [{
                        xtype: 'xcheckbox',
                        fieldLabel: _('ms3_active'),
                        boxLabel: _('ms3_active'),
                        name: 'active',
                        anchor: '99%',
                        id: config.id + '-active',
                        style: { paddingTop: '10px' },
                        disabled: !existsInDatabase
                    }],
                }]
            }, {
                xtype: 'displayfield',
                cls: 'text-success',
                html: 'Столбец <strong>website</strong> существует в таблице <strong>modx_ms3_products</strong>, редактирование большинства полей недоступно.',
                id: config.id + '-exists-message',
                hidden: !existsInDatabase
            },{
                id: config.id + '-create',
                name: 'create',
                xtype: 'hidden',
                value: false
            }, {
                xtype: 'fieldset',
                title: 'Создать колонку в БД',//_('ms3_key'),
                layout: 'column',
                defaults: { msgTarget: 'under', border: false },
                checkboxToggle: !existsInDatabase,
                cls: existsInDatabase ? '' : 'x-fieldset-checkbox-toggle',
                collapsed: !existsInDatabase,
                listeners: {
                    expand: {
                        fn: function (p) {
                            Ext.getCmp(config.id + '-create').setValue(true);
                            Ext.getCmp(config.id + '-active').enable();
                       }, scope: this
                    },
                    collapse: {
                        fn: function (p) {
                            Ext.getCmp(config.id + '-create').setValue(false);
                            Ext.getCmp(config.id + '-active').disable();
                            Ext.getCmp(config.id + '-active').setValue(false);
                        }, scope: this
                    }
                },
                items: [{
                    columnWidth: 1,
                    layout: 'form',
                    items: [{
                        layout: 'column',
                        items: [{
                            columnWidth: .33,
                            layout: 'form',
                            defaults: { msgTarget: 'under' },
                            items: [{
                                xtype: 'ms3-combo-combobox-default',
                                fieldLabel: 'dbtype',//_('ms3_class'),
                                name: 'dbtype',
                                hiddenName: 'dbtype',
                                anchor: '99%',
                                id: config.id + '-dbtype',
                                allowBlank: true,
                                disabled: existsInDatabase,
                                mode: 'local',
                                displayField: 'value',
                                valueField: 'value',
                                store: new Ext.data.ArrayStore({
                                    id: 0,
                                    fields: ['value'],
                                    // https://cheatography.com/beeftornado/cheat-sheets/mysql-5-7-data-types/
                                    data: [
                                        ['tinyint'],
                                        ['smallint'],
                                        ['mediumint'],
                                        ['int'],
                                        ['bigint'],
                                        ['float'],
                                        ['double'],
                                        ['decimal'],
                                        ['char'],
                                        ['varchar'],
                                        ['tinytext'],
                                        ['text'],
                                        ['mediumtext'],
                                        ['longtext'],
                                        ['year'],
                                        ['date'],
                                        ['time'],
                                        ['datetime'],
                                        ['timestamp']
                                    ]
                                }),
                            }],
                        }, {
                            columnWidth: .33,
                            layout: 'form',
                            items: [{
                                xtype: 'textfield',
                                fieldLabel: 'precision',//_('ms3_key'),
                                name: 'precision',
                                anchor: '99%',
                                id: config.id + '-precision',
                                allowBlank: true,
                                disabled: existsInDatabase
                            }]
                        }, {
                            columnWidth: .33,
                            layout: 'form',
                            items: [{
                                xtype: 'ms3-combo-combobox-default',
                                fieldLabel: 'phptype',//_('ms3_class'),
                                name: 'phptype',
                                hiddenName: 'phptype',
                                anchor: '99%',
                                id: config.id + '-phptype',
                                allowBlank: true,
                                //disabled: existsInDatabase,
                                mode: 'local',
                                displayField: 'value',
                                valueField: 'value',
                                store: new Ext.data.ArrayStore({
                                    id: 0,
                                    fields: ['value'],
                                    data: [
                                        ['string'],
                                        ['boolean'],
                                        ['integer'],
                                        ['float'],
                                        ['json'],
                                        ['datetime']
                                    ]
                                })
                            }],
                        }]
                    }, {
                        layout: 'column',
                        items: [{
                            columnWidth: .33,
                            layout: 'form',
                            defaults: { msgTarget: 'under' },
                            items: [{
                                xtype: 'ms3-combo-combobox-default',
                                fieldLabel: 'attributes',//_('ms3_class'),
                                name: 'attributes',
                                hiddenName: 'attributes',
                                anchor: '99%',
                                id: config.id + '-attributes',
                                allowBlank: true,
                                disabled: existsInDatabase,
                                mode: 'local',
                                displayField: 'title',
                                valueField: 'value',
                                store: new Ext.data.ArrayStore({
                                    id: 0,
                                    fields: ['value', 'title'],
                                    data: [
                                        ['', _('no')],
                                        ['BINARY', 'BINARY'],
                                        ['UNSIGNED', 'UNSIGNED'],
                                        ['UNSIGNED ZEROFILL', 'UNSIGNED ZEROFILL'],
                                        ['on update CURRENT_TIMESTAMP', 'on update CURRENT_TIMESTAMP'],
                                    ]
                                })
                            }],
                        }, {
                            columnWidth: .33,
                            layout: 'form',
                            items: [{
                                xtype: 'ms3-combo-combobox-default',
                                fieldLabel: 'default',//_('ms3_class'),
                                name: 'default',
                                hiddenName: 'default',
                                anchor: '99%',
                                id: config.id + '-default',
                                allowBlank: true,
                                disabled: existsInDatabase,
                                mode: 'local',
                                displayField: 'title',
                                valueField: 'value',
                                store: new Ext.data.ArrayStore({
                                    id: 0,
                                    fields: ['value', 'title'],
                                    data: [
                                        ['', _('no')],
                                        ['NULL', 'NULL'],
                                        ['CURRENT_TIMESTAMP', 'CURRENT_TIMESTAMP'],
                                        ['USER_DEFINED', 'Как определено:']
                                    ]
                                }),
                                listeners: {
                                    afterrender: {
                                        fn: function (select, rec) {
                                            this.handleDefaultFields(select);
                                        }, scope: this
                                    },
                                    select: {
                                        fn: function (select, rec) {
                                            this.handleDefaultFields(select);
                                        }, scope: this
                                    }
                                }
                            }]
                        }, {
                            columnWidth: .33,
                            layout: 'form',
                            items: [{
                                xtype: 'textfield',
                                fieldLabel: 'default_value',//_('ms3_key'),
                                name: 'default_value',
                                anchor: '99%',
                                id: config.id + '-default_value',
                                allowBlank: true,
                                disabled: existsInDatabase
                            }],
                        }]
                    }, {
                        xtype: 'xcheckbox',
                        fieldLabel: 'null',//_('ms3_key'),
                        boxLabel: 'null 2',//_('ms3_key'),
                        name: 'null',
                        anchor: '99%',
                        id: config.id + '-null',
                        allowBlank: true,
                        disabled: existsInDatabase
                    }]
                }]
            }
        ];
    },

    handleDefaultFields: function (select) {
        const value = select.getValue();
        let defaultValueElement = Ext.getCmp(this.config.id + '-default_value');
        if (value === 'USER_DEFINED') {
            defaultValueElement.show();
        } else {
            defaultValueElement.setValue('');
            defaultValueElement.hide();
        }
    },

});
Ext.reg('ms3-window-extra-field-create', ms3.window.CreateExtraField);


ms3.window.UpdateExtraField = function (config) {
    config = config || {};

    Ext.applyIf(config, {
        title: _('ms3_menu_update'),
        baseParams: {
            action: 'MiniShop3\\Processors\\Utilities\\ExtraField\\Update',
        }
    });
    ms3.window.UpdateExtraField.superclass.constructor.call(this, config);
};
Ext.extend(ms3.window.UpdateExtraField, ms3.window.CreateExtraField, {

    getFields: function (config) {
        const fields = ms3.window.CreateExtraField.prototype.getFields.call(this, config);

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
Ext.reg('ms3-window-extra-field-update', ms3.window.UpdateExtraField);
