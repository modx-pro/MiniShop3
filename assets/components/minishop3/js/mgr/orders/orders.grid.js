minishop.grid.Orders = function (config) {
    config = config || {};
    if (!config.id) {
        config.id = 'minishop-grid-orders';
    }

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
    minishop.grid.Orders.superclass.constructor.call(this, config);
};
Ext.extend(minishop.grid.Orders, minishop.grid.Default, {

    getFields: function () {
        return minishop.config['order_grid_fields'];
    },

    getColumns: function () {
        const all = {
            id: {width: 35},
            customer: {width: 100, renderer: function (val, cell, row) {
                return minishop.utils.userLink(val, row.data['user_id'], true);
            }},
            num: {width: 50},
            receiver: {width: 100},
            createdon: {width: 75, renderer: minishop.utils.formatDate},
            updatedon: {width: 75, renderer: minishop.utils.formatDate},
            cost: {width: 50, renderer: this._renderCost},
            cart_cost: {width: 50},
            delivery_cost: {width: 75},
            weight: {width: 50},
            status_name: {width: 75, renderer: minishop.utils.renderBadge},
            delivery_name: {width: 75},
            payment_name: {width: 75},
            context: {width: 50},
            actions: {width: 75, id: 'actions', renderer: minishop.utils.renderActions, sortable: false},
        };

        const fields = this.getFields();
        const columns = [];
        for (let i = 0; i < fields.length; i++) {
            const field = fields[i];
            if (all[field]) {
                Ext.applyIf(all[field], {
                    header: _('ms_' + field),
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
                const params = minishop.utils.Hash.get();
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
                        let w = Ext.getCmp('minishop-window-order-update');
                        if (w) {
                            w.close();
                        }

                        w = MODx.load({
                            xtype: 'minishop-window-order-update',
                            id: 'minishop-window-order-update',
                            record: r.object,
                            listeners: {
                                success: {
                                    fn: function () {
                                        this.refresh();
                                    }, scope: this
                                },
                                hide: {
                                    fn: function () {
                                        minishop.utils.Hash.remove('order');
                                        if (minishop.grid.Orders.changed === true) {
                                            Ext.getCmp('minishop-grid-orders').getStore().reload();
                                            minishop.grid.Orders.changed = false;
                                        }
                                    }
                                },
                                afterrender: function () {
                                    minishop.utils.Hash.add('order', r.object['id']);
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
            _('ms_menu_remove_title'),
            ids.length > 1
                ? _('ms_menu_remove_multiple_confirm')
                : _('ms_menu_remove_confirm'),
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
Ext.reg('minishop-grid-orders', minishop.grid.Orders);
