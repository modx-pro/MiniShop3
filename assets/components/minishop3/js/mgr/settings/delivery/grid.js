ms3.grid.Delivery = function (config) {
    config = config || {};
    if (!config.id) {
        config.id = 'ms3-grid-delivery';
    }
    config.disableContextMenuAction = true;

    Ext.applyIf(config, {
        baseParams: {
            action: 'MiniShop3\\Processors\\Settings\\Delivery\\GetList',
            sort: 'position',
            dir: 'asc',
        },
        stateful: true,
        stateId: config.id,
        ddGroup: 'ms3-settings-delivery',
        ddAction: 'MiniShop3\\Processors\\Settings\\Delivery\\Sort',
        enableDragDrop: true,
        multi_select: true,
    });
    ms3.grid.Delivery.superclass.constructor.call(this, config);
};
Ext.extend(ms3.grid.Delivery, ms3.grid.Default, {

    getFields: function () {
        return [
            'id', 'name', 'price', 'weight_price', 'distance_price', 'position', 'payments',
            'logo', 'active', 'class', 'description', 'requires', 'actions', 'free_delivery_amount'
        ];
    },

    getColumns: function () {
        return [
            {header: _('ms3_id'), dataIndex: 'id', width: 20},
            {header: _('ms3_logo'), dataIndex: 'logo', id: 'image', width: 30, renderer: ms3.utils.renderImage},
            {header: _('ms3_name'), dataIndex: 'name', width: 75},
            {header: _('ms3_add_cost'), dataIndex: 'price', width: 50},
            {header: _('ms3_weight_price'), dataIndex: 'weight_price', width: 50, hidden: true},
            {header: _('ms3_distance_price'), dataIndex: 'distance_price', width: 50, hidden: true},
            {header: _('ms3_payments'), dataIndex: 'payments', width: 50},
            {header: _('ms3_class'), dataIndex: 'class', width: 50},
            {header: _('ms3_rank'), dataIndex: 'position', width: 35, hidden: true},
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
            handler: this.createDelivery,
            scope: this
        }, '->', this.getSearchField()];
    },

    getListeners: function () {
        return {
            rowDblClick: function (grid, rowIndex, e) {
                const row = grid.store.getAt(rowIndex);
                this.updateDelivery(grid, e, row);
            },
        };
    },

    deliveryAction: function (method) {
        const ids = this._getSelectedIds();
        if (!ids.length) {
            return false;
        }
        MODx.Ajax.request({
            url: ms3.config['connector_url'],
            params: {
                action: 'MiniShop3\\Processors\\Settings\\Delivery\\Multiple',
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

    createDelivery: function (btn, e) {
        let w = Ext.getCmp('ms3-window-delivery-create');
        if (w) {
            w.hide().getEl().remove();
        }

        w = MODx.load({
            xtype: 'ms3-window-delivery-create',
            id: 'ms3-window-delivery-create',
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
        w.fp.getForm().setValues({
            price: 0,
            weight_price: 0,
            distance_price: 0,
            active: true,
        });
        w.show(e.target);
    },

    updateDelivery: function (btn, e, row) {
        if (typeof(row) != 'undefined') {
            this.menu.record = row.data;
        }

        let w = Ext.getCmp('ms3-window-delivery-update');
        if (w) {
            w.close();
        }
        w = MODx.load({
            xtype: 'ms3-window-delivery-update',
            id: 'ms3-window-delivery-update',
            record: this.menu.record,
            title: this.menu.record['name'],
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

    enableDelivery: function () {
        this.deliveryAction('Enable');
    },

    disableDelivery: function () {
        this.deliveryAction('Disable');
    },

    removeDelivery: function () {
        const ids = this._getSelectedIds();

        Ext.MessageBox.confirm(
            _('ms3_menu_remove_title'),
            ids.length > 1
                ? _('ms3_menu_remove_multiple_confirm')
                : _('ms3_menu_remove_confirm'),
            function (val) {
                if (val == 'yes') {
                    this.deliveryAction('Remove');
                }
            },
            this
        );
    },
});
Ext.reg('ms3-grid-delivery', ms3.grid.Delivery);
