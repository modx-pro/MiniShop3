ms3.grid.OrderProducts = function (config) {
    config = config || {};
    if (!config.id) {
        config.id = 'ms3-grid-order-products';
    }
    Ext.applyIf(config, {
        baseParams: {
            action: 'MiniShop3\\Processors\\Order\\Product\\GetList',
            order_id: config.order_id,
        },
        cls: 'ms3-grid',
        multi_select: false,
        stateful: true,
        stateId: config.id,
        pageSize: Math.round(MODx.config['default_per_page'] / 2),
    });
    ms3.grid.OrderProducts.superclass.constructor.call(this, config);
};
Ext.extend(ms3.grid.OrderProducts, ms3.grid.Default, {

    getFields: function () {
        return ms3.config['order_product_fields'];
    },

    getColumns: function () {
        const fields = {
            //id: {hidden: true, sortable: true, width: 40},
            product_id: {hidden: true, sortable: true, width: 40},
            name: {
                header: _('ms3_name'),
                width: 100,
                renderer: function (value, metaData, record) {
                    return ms3.utils.productLink(value, record['data']['product_id']);
                }
            },
            product_weight: {header: _('ms3_product_weight'), width: 50},
            product_price: {header: _('ms3_product_price'), width: 50},
            product_article: {width: 50},
            weight: {sortable: true, width: 50},
            price: {sortable: true, header: _('ms3_product_price'), width: 50},
            count: {sortable: true, width: 50},
            cost: {width: 50},
            options: {width: 100},
            actions: {width: 75, id: 'actions', renderer: ms3.utils.renderActions, sortable: false},
        };

        const columns = [];
        for (let i = 0; i < ms3.config['order_product_fields'].length; i++) {
            const field = ms3.config['order_product_fields'][i];
            if (fields[field]) {
                Ext.applyIf(fields[field], {
                    header: _('ms3_' + field),
                    dataIndex: field
                });
                columns.push(fields[field]);
            } else if (/^option_/.test(field)) {
                columns.push(
                    {header: _(field.replace(/^option_/, 'ms3_')), dataIndex: field, width: 50}
                );
            } else if (/^product_/.test(field)) {
                columns.push(
                    {header: _(field.replace(/^product_/, 'ms3_')), dataIndex: field, width: 75}
                );
            } else if (/^category_/.test(field)) {
                columns.push(
                    {header: _(field.replace(/^category_/, 'ms3_')), dataIndex: field, width: 75}
                );
            } else if(/^vendor_name/.test(field)) {
                columns.push(
                    {header: _('ms3_product_vendor'), dataIndex: field, width: 75}
                );
            } else if(/^vendor_/.test(field)) {
                columns.push(
                    {header: _(field.replace(/^vendor_/, 'ms3_')), dataIndex: field, width: 75}
                );
            }
        }

        return columns;
    },

    getTopBar: function () {
        return [{
            xtype: 'ms3-combo-product',
            allowBlank: true,
            width: '50%',
            listeners: {
                select: {
                    fn: this.addOrderProduct,
                    scope: this
                }
            }
        }];
    },

    getListeners: function () {
        return {
            rowDblClick: function (grid, rowIndex, e) {
                const row = grid.store.getAt(rowIndex);
                this.updateOrderProduct(grid, e, row);
            }
        };
    },

    addOrderProduct: function (combo, row) {
        const id = row.id;
        combo.reset();

        MODx.Ajax.request({
            url: ms3.config['connector_url'],
            params: {
                action: 'MiniShop3\\Processors\\Product\\Get',
                id: id
            },
            listeners: {
                success: {
                    fn: function (r) {
                        let w = Ext.getCmp('ms3-window-orderproduct-update');
                        if (w) {
                            w.close();
                        }

                        r.object.order_id = this.config.order_id;
                        r.object.count = 1;
                        r.object.name = r.object['pagetitle'];
                        w = MODx.load({
                            xtype: 'ms3-window-orderproduct-update',
                            id: 'ms3-window-orderproduct-update',
                            record: r.object,
                            action: 'MiniShop3\\Processors\\Order\\Product\\Create',
                            listeners: {
                                success: {
                                    fn: function () {
                                        ms3.grid.Orders.changed = true;
                                        this.refresh();
                                    }, scope: this
                                }
                            }
                        });
                        w.fp.getForm().reset();
                        w.fp.getForm().setValues(r.object);
                        w.show(Ext.EventObject.target);
                    }, scope: this
                }
            }
        });
    },

    updateOrderProduct: function (btn, e, row) {
        if (typeof(row) != 'undefined') {
            this.menu.record = row.data;
        }
        const id = this.menu.record.id;

        MODx.Ajax.request({
            url: ms3.config['connector_url'],
            params: {
                action: 'MiniShop3\\Processors\\Order\\Product\\Get',
                id: id
            },
            listeners: {
                success: {
                    fn: function (r) {
                        let w = Ext.getCmp('ms3-window-orderproduct-update');
                        if (w) {
                            w.close();
                        }

                        r.object.order_id = this.config.order_id;
                        w = MODx.load({
                            xtype: 'ms3-window-orderproduct-update',
                            id: 'ms3-window-orderproduct-update',
                            record: r.object,
                            action: 'MiniShop3\\Processors\\Order\\Product\\Update',
                            listeners: {
                                success: {
                                    fn: function () {
                                        ms3.grid.Orders.changed = true;
                                        this.refresh();
                                    }, scope: this
                                },
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

    removeOrderProduct: function () {
        if (!this.menu.record) {
            return;
        }

        MODx.msg.confirm({
            title: _('ms3_menu_remove'),
            text: _('ms3_menu_remove_confirm'),
            url: ms3.config['connector_url'],
            params: {
                action: 'MiniShop3\\Processors\\Order\\Product\\Remove',
                id: this.menu.record.id
            },
            listeners: {
                success: {
                    fn: function () {
                        ms3.grid.Orders.changed = true;
                        this.refresh();
                    }, scope: this
                }
            }
        });
    }
});
Ext.reg('ms3-grid-order-products', ms3.grid.OrderProducts);
