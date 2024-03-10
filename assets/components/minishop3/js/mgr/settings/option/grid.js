ms3.grid.Option = function (config) {
    config = config || {};
    if (!config.id) {
        config.id = 'ms3-grid-option';
    }
    config.disableContextMenuAction = true;

    Ext.applyIf(config, {
        baseParams: {
            action: 'MiniShop3\\Processors\\Settings\\Option\\GetList',
            sort: 'key',
            dir: 'asc'
        },
        cls: 'ms3-grid',
        multi_select: true,
    });
    ms3.grid.Option.superclass.constructor.call(this, config);

    config.sm.on('selectionchange', function () {
        const ids = this._getSelectedIds();
        const btn = Ext.getCmp(config.id + '-btn-assign');
        if (btn) {
            if (ids.length > 1) {
                btn.enable();
            } else {
                btn.disable();
            }
        }
    }, this);
};

Ext.extend(ms3.grid.Option, ms3.grid.Default, {

    getFields: function () {
        return [
            'id', 'key', 'caption', 'description', 'measure_unit',
            'modcategory_id', 'type', 'properties', 'rank', 'actions'
        ];
    },

    getColumns: function () {
        return [
            {
                header: _('id'),
                dataIndex: 'id',
                width: 30,
                sortable: true
            }, {
                header: _('ms3_ft_name'),
                dataIndex: 'key',
                width: 100,
                sortable: true
            }, {
                header: _('ms3_ft_caption'),
                dataIndex: 'caption',
                width: 100,
                sortable: true
            }, {
                header: _('ms3_ft_type'),
                dataIndex: 'type',
                width: 100,
                sortable: true,
                renderer: function (v) {
                    return _('ms3_ft_' + v)
                }
            }, {
                header: _('ms3_actions'),
                dataIndex: 'actions',
                id: 'actions',
                width: 70,
                renderer: ms3.utils.renderActions
            }
        ];
    },

    getTopBar: function (config) {
        return [{
            text: '<i class="icon icon-plus"></i> ' + _('ms3_btn_create'),
            handler: this.createOption,
            scope: this
        }, {
            text: '<i class="icon icon-check"></i> ' + _('ms3_btn_assign'),
            id: config.id + '-btn-assign',
            handler: this.assignOption,
            scope: this,
            disabled: true,
        }, '->', {
            xtype: 'ms3-combo-modcategory',
            id: config.id + '-modcategory',
            listeners: {
                select: {
                    fn: function (field) {
                        this.baseParams.modcategory = field.value;
                        this.getBottomToolbar().changePage(1);
                    }, scope: this
                }
            }
        }, this.getSearchField()];
    },

    getListeners: function () {
        return {
            rowDblClick: function (grid, rowIndex, e) {
                const row = grid.store.getAt(rowIndex);
                this.updateOption(grid, e, row);
            },
        };
    },

    actionsColumnRenderer: function (value, metaData, record, rowIndex, colIndex, store) {
        const actions = this.getActions.apply(this, [record, rowIndex, colIndex, store]);
        return this._getActionsColumnTpl().apply({
            actions: actions
        });
    },


    createOption: function (btn, e) {
        let w = Ext.getCmp('ms3-window-option-create');
        if (w) {
            w.hide().getEl().remove();
        }

        w = MODx.load({
            xtype: 'ms3-window-option-create',
            id: 'ms3-window-option-create',
            record: [],
            listeners: {
                success: {
                    fn: function () {
                        this.refresh();
                    }, scope: this
                }
            }
        });
        w.fp.getForm().setValues({
            type: 'textfield',
            categories: '[]'
        });
        w.show(e.target);
    },

    updateOption: function (btn, e, row) {
        if (typeof (row) != 'undefined') {
            this.menu.record = row.data;
        }

        let w = Ext.getCmp('ms3-window-option-update');
        if (w) {
            w.close();
        }
        MODx.Ajax.request({
            url: this.config.url,
            params: {
                action: 'MiniShop3\\Processors\\Settings\\Option\\Get',
                id: this.menu.record.id
            },
            listeners: {
                success: {
                    fn: function (r) {
                        w = MODx.load({
                            xtype: 'ms3-window-option-update',
                            id: 'ms3-window-option-update',
                            title: r.object['caption'],
                            record: r.object,
                            listeners: {
                                afterrender: function () {
                                    const combo = Ext.getCmp(this.config.id + '-types');
                                    combo.getStore().on('load', function () {
                                        const row = combo.findRecord('name', combo.getValue());
                                        if (row && row.data['xtype']) {
                                            w.onSelectType(combo, row);
                                        }
                                    });
                                },
                                success: {
                                    fn: function () {
                                        this.refresh();
                                    }, scope: this
                                }
                            }
                        });
                        w.fp.getForm().reset();
                        w.fp.getForm().setValues(r.object);
                        w.show(e.target);
                    }, scope: this
                }
            }
        });
    },

    removeOption: function () {
        if (!this.menu.record) {
            return false;
        }

        MODx.msg.confirm({
            title: _('ms3_menu_remove') + '"' + this.menu.record.key + '"',
            text: _('ms3_menu_remove_confirm'),
            url: this.config.url,
            params: {
                action: 'MiniShop3\\Processors\\Settings\\Option\\Multiple',
                method: 'Remove',
                ids: Ext.util.JSON.encode(this._getSelectedIds()),
            },
            listeners: {
                success: {
                    fn: function () {
                        this.refresh();
                    }, scope: this
                }
            }
        });
    },

    assignOption: function (btn, e) {
        const options = Ext.util.JSON.encode(this._getSelectedIds());
        let w = Ext.getCmp('ms3-window-option-assign');
        if (w) {
            w.close();
        }

        w = MODx.load({
            xtype: 'ms3-window-option-assign',
            id: 'ms3-window-option-assign',
            options: options,
            listeners: {
                success: {
                    fn: function () {
                        this.refresh();
                    }, scope: this
                }
            }
        });
        w.fp.getForm().setValues({ options: options });
        w.show(e.target);
    },

    _clearSearch: function () {
        this.getStore().baseParams.query = '';

        const c = Ext.getCmp(this.config.id + '-modcategory');
        if (c) {
            c.clearValue();
            this.getStore().baseParams.modcategory = '';
        }

        this.getBottomToolbar().changePage(1);
    },

});
Ext.reg('ms3-grid-option', ms3.grid.Option);
