ms3.grid.Orders = function (config) {
    config = config || {};
    if (!config.id) {
        config.id = 'ms3-grid-orders';
    }
    config.disableContextMenuAction = true;

    Ext.applyIf(config, {
        baseParams: {
            action: 'MiniShop3\\Processors\\Order\\GetList',
            sort: 'id',
            dir: 'desc',
        },
        multi_select: true,
        changed: false,
        stateful: true,
        stateId: config.id,
    });
    ms3.grid.Orders.superclass.constructor.call(this, config);
};
Ext.extend(ms3.grid.Orders, ms3.grid.Default, {

    getFields: function () {
        return ms3.config['order_grid_fields'];
    },

    getColumns: function () {
        const all = {
            id: {width: 35},
            customer: {width: 100, renderer: function (val, cell, row) {
                return row.data['customer'];
              }},
            num: {width: 50},
            first_name: {width: 100},
            last_name: {width: 100},
            createdon: {width: 75, renderer: ms3.utils.formatDate},
            updatedon: {width: 75, renderer: ms3.utils.formatDate},
            cost: {width: 50, renderer: this._renderCost},
            cart_cost: {width: 50},
            delivery_cost: {width: 75},
            weight: {width: 50},
            status_name: {width: 75, renderer: ms3.utils.renderBadge},
            delivery_name: {width: 75},
            payment_name: {width: 75},
            context: {width: 50},
            actions: {width: 75, id: 'actions', renderer: ms3.utils.renderActions, sortable: false},
        };

        const fields = this.getFields();
        const columns = [];
        for (let i = 0; i < fields.length; i++) {
            const field = fields[i];
            if (all[field]) {
                Ext.applyIf(all[field], {
                    header: _('ms3_' + field),
                    dataIndex: field,
                    sortable: true,
                });
                columns.push(all[field]);
            }
        }

        return columns;
    },

    getTopBar: function () {
        return [];
    },

    getListeners: function () {
        return {
            rowDblClick: function (grid, rowIndex, e) {
                const row = grid.store.getAt(rowIndex);
                this.updateOrder(grid, e, row);
            },
            afterrender: function (grid) {
                const params = ms3.utils.Hash.get();
                const order = params['order'] || '';
                if (order) {
                    this.updateOrder(grid, Ext.EventObject, {data: {id: order}});
                }
            },
        };
    },

    orderAction: function (method) {
        const ids = this._getSelectedIds();
        if (!ids.length) {
            return false;
        }
        MODx.Ajax.request({
            url: this.config.url,
            params: {
                action: 'MiniShop3\\Processors\\Order\\Multiple',
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

    updateOrder: function (btn, e, row) {
        if (typeof(row) != 'undefined') {
            this.menu.record = row.data;
        }
        const id = this.menu.record.id;

        MODx.Ajax.request({
            url: this.config.url,
            params: {
                action: 'MiniShop3\\Processors\\Order\\Get',
                id: id
            },
            listeners: {
                success: {
                    fn: function (r) {
                        let w = Ext.getCmp('ms3-window-order-update');
                        if (w) {
                            w.close();
                        }

                        w = MODx.load({
                            xtype: 'ms3-window-order-update',
                            id: 'ms3-window-order-update',
                            record: r.object,
                            listeners: {
                                success: {
                                    fn: function () {
                                        this.refresh();
                                    }, scope: this
                                },
                                hide: {
                                    fn: function () {
                                        ms3.utils.Hash.remove('order');
                                        if (ms3.grid.Orders.changed === true) {
                                            Ext.getCmp('ms3-grid-orders').getStore().reload();
                                            ms3.grid.Orders.changed = false;
                                        }
                                    }
                                },
                                afterrender: function () {
                                    ms3.utils.Hash.add('order', r.object['id']);
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

    removeOrder: function () {
        const ids = this._getSelectedIds();

        Ext.MessageBox.confirm(
            _('ms3_menu_remove_title'),
            ids.length > 1
                ? _('ms3_menu_remove_multiple_confirm')
                : _('ms3_menu_remove_confirm'),
            function (val) {
                if (val == 'yes') {
                    this.orderAction('Remove');
                }
            },
            this
        );
    },

    _renderCost: function (val, idx, rec) {
        return rec.data['type'] != undefined && rec.data['type'] == 1
            ? '-' + val
            : val;
    },

});
Ext.reg('ms3-grid-orders', ms3.grid.Orders);
