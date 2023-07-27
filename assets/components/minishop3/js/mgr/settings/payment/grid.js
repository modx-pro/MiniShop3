minishop.grid.Payment = function (config) {
    config = config || {};
    if (!config.id) {
        config.id = 'minishop-grid-payment';
    }
    config.disableContextMenuAction = true;

    Ext.applyIf(config, {
        baseParams: {
            action: 'MiniShop3\\Processors\\Settings\\Payment\\GetList',
            sort: 'position',
            dir: 'asc',
        },
        stateful: true,
        stateId: config.id,
        ddGroup: 'ms-settings-payment',
        ddAction: 'MiniShop3\\Processors\\Settings\\Payment\\Sort',
        enableDragDrop: true,
        multi_select: true,
    });
    minishop.grid.Payment.superclass.constructor.call(this, config);
};
Ext.extend(minishop.grid.Payment, minishop.grid.Default, {

    getFields: function () {
        return ['id', 'name', 'description', 'price', 'logo', 'position', 'active', 'class', 'deliveries', 'actions'];
    },

    getColumns: function () {
        return [
            {header: _('ms_id'), dataIndex: 'id', width: 20},
            {header: _('ms_logo'), dataIndex: 'logo', id: 'image', width: 30, renderer: minishop.utils.renderImage},
            {header: _('ms_name'), dataIndex: 'name', width: 75},
            {header: _('ms_add_cost'), dataIndex: 'price', width: 50},
            {header: _('ms_deliveries'), dataIndex: 'deliveries', width: 50},
            {header: _('ms_class'), dataIndex: 'class', width: 50},
            {header: _('ms_rank'), dataIndex: 'position', width: 35, hidden: true},
            {
                header: _('ms_actions'),
                dataIndex: 'actions',
                id: 'actions',
                width: 50,
                renderer: minishop.utils.renderActions
        }
        ];
    },

    getTopBar: function () {
        return [{
            text: '<i class="icon icon-plus"></i> ' + _('ms_btn_create'),
            handler: this.createPayment,
            scope: this
        }, '->', this.getSearchField()];
    },

    getListeners: function () {
        return {
            rowDblClick: function (grid, rowIndex, e) {
                const row = grid.store.getAt(rowIndex);
                this.updatePayment(grid, e, row);
            },
        };
    },

    paymentAction: function (method) {
        const ids = this._getSelectedIds();
        if (!ids.length) {
            return false;
        }
        MODx.Ajax.request({
            url: minishop.config['connector_url'],
            params: {
                action: 'MiniShop3\\Processors\\Settings\\Payment\\Multiple',
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

    createPayment: function (btn, e) {
        let w = Ext.getCmp('minishop-window-payment-create');
        if (w) {
            w.hide().getEl().remove();
        }

        w = MODx.load({
            xtype: 'minishop-window-payment-create',
            id: 'minishop-window-payment-create',
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
            active: true,
        });
        w.show(e.target);
    },

    updatePayment: function (btn, e, row) {
        if (typeof(row) != 'undefined') {
            this.menu.record = row.data;
        }

        let w = Ext.getCmp('minishop-window-payment-update');
        if (w) {
            w.close();
        }
        w = MODx.load({
            xtype: 'minishop-window-payment-update',
            id: 'minishop-window-payment-update',
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

    enablePayment: function () {
        this.paymentAction('Enable');
    },

    disablePayment: function () {
        this.paymentAction('Disable');
    },

    removePayment: function () {
        const ids = this._getSelectedIds();

        Ext.MessageBox.confirm(
            _('ms_menu_remove_title'),
            ids.length > 1
                ? _('ms_menu_remove_multiple_confirm')
                : _('ms_menu_remove_confirm'),
            function (val) {
                if (val == 'yes') {
                    this.paymentAction('Remove');
                }
            },
            this
        );
    },
});
Ext.reg('minishop-grid-payment', minishop.grid.Payment);
