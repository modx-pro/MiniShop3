ms3.grid.ExtraField = function (config) {
    config = config || {};
    if (!config.id) {
        config.id = 'ms3-grid-extra-field';
    }
    config.disableContextMenuAction = true;

    Ext.applyIf(config, {
        baseParams: {
            action: 'MiniShop3\\Processors\\Utilities\\ExtraField\\GetList'
        },
        stateful: true,
        stateId: config.id,
        multi_select: true,
    });
    ms3.grid.ExtraField.superclass.constructor.call(this, config);
};

Ext.extend(ms3.grid.ExtraField, ms3.grid.Default, {

    getFields: function () {
        return ['id', 'class', 'key', 'label', 'dbtype', 'exists', 'active', 'actions'];
    },

    getTopBar: function () {
        return [{
            text: '<i class="icon icon-plus"></i> ' + _('ms3_btn_create'),
            handler: this.createExtraField,
            scope: this
        }, '->', this.getSearchField()];
    },

    getColumns: function () {
        return [
            {
                header: _('ms3_id'),
                dataIndex: 'id',
                width: 50,
                sortable: true
            }, {
                header: 'class', //_('ms3_class'),
                dataIndex: 'class',
                width: 150,
                sortable: true
            }, {
                header: 'key', //_('ms3_key'),
                dataIndex: 'key',
                width: 100,
                sortable: true
            }, {
                header: 'label', //_('ms3_label'),
                dataIndex: 'label',
                width: 100,
                sortable: true
            }, {
                header: 'dbtype', //_('ms3_label'),
                dataIndex: 'dbtype',
                width: 100,
                sortable: true
            }, {
                header: 'exists in db', //_('ms3_active'),
                dataIndex: 'exists',
                width: 80,
                sortable: true,
                renderer: ms3.utils.renderBoolean
            }, {
                header: 'active', //_('ms3_active'),
                dataIndex: 'active',
                width: 80,
                sortable: true,
                renderer: ms3.utils.renderBoolean
            }, {
                header: _('ms3_actions'),
                dataIndex: 'actions',
                id: 'actions',
                width: 50,
                renderer: ms3.utils.renderActions
            }
        ];
    },

    getListeners: function () {
        return {
            rowDblClick: function (grid, rowIndex, e) {
                const row = grid.store.getAt(rowIndex);
                this.updateExtraField(grid, e, row);
            },
        };
    },

    extraFieldAction: function (method) {
        const ids = this._getSelectedIds();
        if (!ids.length) {
            return false;
        }
        MODx.Ajax.request({
            url: ms3.config.connector_url,
            params: {
                action: 'MiniShop3\\Processors\\Utilities\\ExtraField\\Multiple',
                method: method,
                ids: Ext.util.JSON.encode(ids),
            },
            listeners: {
                success: {
                    fn: function () {
                        //noinspection JSUnresolvedFunction
                        this.refresh();
                    }, scope: this
                },
                failure: {
                    fn: function (response) {
                        MODx.msg.alert(_('error'), response.message);
                    }, scope: this
                },
            }
        })
    },

    createExtraField: function (btn, e) {
        let w = Ext.getCmp('ms3-window-extra-field-create');
        if (w) {
            w.close();
        }
        w = MODx.load({
            xtype: 'ms3-window-extra-field-create',
            id: 'ms3-window-extra-field-create',
            listeners: {
                success: {
                    fn: function () {
                        this.refresh();
                    }, scope: this
                }
            }
        });
        w.setValues({
            attributes: '',
            default: '',
        });
        w.show(e.target);
    },

    updateExtraField: function (btn, e, row) {
        if (typeof(row) != 'undefined') {
            this.menu.record = row.data;
        }
        const id = this.menu.record.id;

        MODx.Ajax.request({
            url: this.config.url,
            params: {
                action: 'MiniShop3\\Processors\\Utilities\\ExtraField\\Get',
                id: id
            },
            listeners: {
                success: {
                    fn: function (r) {
                        let w = Ext.getCmp('ms3-window-extra-field-update');
                        if (w) {
                            w.close();
                        }

                        w = MODx.load({
                            xtype: 'ms3-window-extra-field-update',
                            id: 'ms3-window-extra-field-update',
                            record: r.object,
                            listeners: {
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

    removeExtraField: function () {
        const ids = this._getSelectedIds();

        Ext.MessageBox.confirm(
            _('ms3_menu_remove_title'),
            ids.length > 1
                ? _('ms3_menu_remove_multiple_confirm')
                : _('ms3_menu_remove_confirm'),
            function (val) {
                if (val == 'yes') {
                    this.extraFieldAction('Remove');
                }
            },
            this
        );
    },

});
Ext.reg('ms3-grid-extra-field', ms3.grid.ExtraField);
