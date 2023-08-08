ms3.grid.Vendor = function (config) {
    config = config || {};
    if (!config.id) {
        config.id = 'ms3-grid-vendor';
    }
    config.disableContextMenuAction = true;
    
    Ext.applyIf(config, {
        baseParams: {
            action: 'MiniShop3\\Processors\\Settings\\Vendor\\GetList',
        },
        stateful: true,
        stateId: config.id,
        multi_select: true,
    });
    ms3.grid.Vendor.superclass.constructor.call(this, config);
};
Ext.extend(ms3.grid.Vendor, ms3.grid.Default, {
    getFields: function () {
        return [
            'id', 'name', 'resource_id', 'country', 'email', 'logo', 'pagetitle',
            'address', 'phone', 'description', 'actions'
        ];
    },

    getColumns: function () {
        return [
            {header: _('ms3_id'), dataIndex: 'id', width: 30, sortable: true},
            {header: _('ms3_logo'), dataIndex: 'logo', id: 'image', width: 50, renderer: ms3.utils.renderImage},
            {header: _('ms3_name'), dataIndex: 'name', width: 100, sortable: true},
            {
                header: _('ms3_resource'),
                dataIndex: 'resource_id',
                width: 100,
                sortable: true,
                hidden: true,
                renderer: this._renderResource
            },
            {header: _('ms3_country'), dataIndex: 'country', width: 75, sortable: true},
            {header: _('ms3_email'), dataIndex: 'email', width: 100, sortable: true},
            {header: _('ms3_address'), dataIndex: 'address', width: 100, sortable: true, hidden: true},
            {header: _('ms3_phone'), dataIndex: 'phone', width: 75, sortable: true},
            {
                header: _('ms3_actions'),
                dataIndex: 'actions',
                id: 'actions',
                width: 50,
                renderer: ms3.utils.renderActions
            }
        ];
    },

    getTopBar: function () {
        return [{
            text: '<i class="icon icon-plus"></i> ' + _('ms3_btn_create'),
            handler: this.createVendor,
            scope: this
        }, '->', this.getSearchField()];
    },

    getListeners: function () {
        return {
            rowDblClick: function (grid, rowIndex, e) {
                const row = grid.store.getAt(rowIndex);
                this.updateVendor(grid, e, row);
            },
        };
    },

    vendorAction: function (method) {
        const ids = this._getSelectedIds();
        if (!ids.length) {
            return false;
        }
        MODx.Ajax.request({
            url: ms3.config.connector_url,
            params: {
                action: 'MiniShop3\\Processors\\Settings\\Vendor\\Multiple',
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

    createVendor: function (btn, e) {
        let w = Ext.getCmp('ms3-window-vendor-create');
        if (w) {
            w.close();
        }
        w = MODx.load({
            xtype: 'ms3-window-vendor-create',
            id: 'ms3-window-vendor-create',
            listeners: {
                success: {
                    fn: function () {
                        this.refresh();
                    }, scope: this
                }
            }
        });
        w.show(e.target);
    },

    updateVendor: function (btn, e, row) {
        if (typeof (row) != 'undefined') {
            this.menu.record = row.data;
        }

        let w = Ext.getCmp('ms3-window-vendor-update');
        if (w) {
            w.close();
        }
        w = MODx.load({
            xtype: 'ms3-window-vendor-update',
            id: 'ms3-window-vendor-update',
            title: this.menu.record['name'],
            record: this.menu.record,
            listeners: {
                success: {
                    fn: function () {
                        this.refresh();
                    }, scope: this
                }
            }
        });
        w.fp.getForm().reset();
        w.fp.getForm().setValues(this.menu.record);
        w.show(e.target);
    },

    removeVendor: function () {
        const ids = this._getSelectedIds();

        Ext.MessageBox.confirm(
            _('ms3_menu_remove_title'),
            ids.length > 1
                ? _('ms3_menu_remove_multiple_confirm')
                : _('ms3_menu_remove_confirm'),
            function (val) {
                if (val == 'yes') {
                    this.vendorAction('Remove');
                }
            }, this
        );
    },

    _renderResource: function (value, cell, row) {
        return value
            ? String.format('({0}) {1}', value, row.data['pagetitle'])
            : '';
    }
});
Ext.reg('ms3-grid-vendor', ms3.grid.Vendor);
