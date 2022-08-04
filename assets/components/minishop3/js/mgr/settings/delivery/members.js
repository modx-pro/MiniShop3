minishop.grid.DeliveryPayments = function (config) {
    config = config || {};
    if (!config.id) {
        config.id = 'minishop-grid-delivery-payments';
    }

    Ext.applyIf(config, {
        cls: 'minishop-grid',
        baseParams: {
            action: 'MiniShop3\\Processors\\Settings\\Delivery\\Payments\\GetList',
            sort: 'position',
            dir: 'asc',
            delivery: config.record.id,
        },
        pageSize: 5,
        multi_select: true,
    });
    minishop.grid.DeliveryPayments.superclass.constructor.call(this, config);
};
Ext.extend(minishop.grid.DeliveryPayments, minishop.grid.Default, {

    getFields: function () {
        return ['id', 'name', 'price', 'logo', 'position', 'active', 'class', 'actions'];
    },

    getColumns: function () {
        return [
            {header: _('ms_logo'), dataIndex: 'logo', id: 'image', width: 30, renderer: minishop.utils.renderImage},
            {header: _('ms_name'), dataIndex: 'name', width: 75},
            {header: _('ms_add_cost'), dataIndex: 'price', width: 50},
            {
                header: _('ms_actions'),
                dataIndex: 'actions',
                id: 'actions',
                width: 35,
                renderer: minishop.utils.renderActions
        }
        ];
    },

    getTopBar: function () {
        return [];
    },

    getListeners: function () {
        return [];
    },

    paymentsAction: function (method) {
        const ids = this._getSelectedIds();
        if (!ids.length) {
            return false;
        }
        MODx.Ajax.request({
            url: minishop.config['connector_url'],
            params: {
                action: 'MiniShop3\\Processors\\Settings\\Delivery\\Payments\\Multiple',
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

    enablePayment: function () {
        this.paymentsAction('Enable');
    },

    disablePayment: function () {
        this.paymentsAction('Disable');
    },

    _getSelectedIds: function () {
        const ids = [];
        const selected = this.getSelectionModel().getSelections();

        for (const i in selected) {
            if (!selected.hasOwnProperty(i)) {
                continue;
            }
            ids.push({
                delivery_id: this.config.record.id,
                payment_id: selected[i]['id'],
            });
        }

        return ids;
    },
});
Ext.reg('minishop-grid-delivery-payments', minishop.grid.DeliveryPayments);
