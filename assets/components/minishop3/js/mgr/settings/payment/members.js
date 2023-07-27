ms3.grid.PaymentDeliveries = function (config) {
    config = config || {};
    if (!config.id) {
        config.id = 'ms3-grid-payment-deliveries';
    }
    config.disableContextMenuAction = true;

    Ext.applyIf(config, {
        cls: 'ms3-grid',
        baseParams: {
            action: 'MiniShop3\\Processors\\Settings\\Payment\\Deliveries\\GetList',
            sort: 'position',
            dir: 'asc',
            payment: config.record.id,
        },
        pageSize: 5,
        multi_select: true,
    });
    ms3.grid.PaymentDeliveries.superclass.constructor.call(this, config);
};
Ext.extend(ms3.grid.PaymentDeliveries, ms3.grid.Default, {

    getFields: function () {
        return ['id', 'name', 'price', 'logo', 'position', 'active', 'class', 'actions'];
    },

    getColumns: function () {
        return [
            {header: _('ms3_logo'), dataIndex: 'logo', id: 'image', width: 30, renderer: ms3.utils.renderImage},
            {header: _('ms3_name'), dataIndex: 'name', width: 75},
            {header: _('ms3_add_cost'), dataIndex: 'price', width: 50},
            {
                header: _('ms3_actions'),
                dataIndex: 'actions',
                id: 'actions',
                width: 35,
                renderer: ms3.utils.renderActions
        }
        ];
    },

    getTopBar: function () {
        return [];
    },

    getListeners: function () {
        return [];
    },

    deliveriesAction: function (method) {
        const ids = this._getSelectedIds();
        if (!ids.length) {
            return false;
        }
        MODx.Ajax.request({
            url: ms3.config['connector_url'],
            params: {
                action: 'MiniShop3\\Processors\\Settings\\Payment\\Deliveries\\Multiple',
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

    enableDelivery: function () {
        this.deliveriesAction('Enable');
    },

    disableDelivery: function () {
        this.deliveriesAction('Disable');
    },

    _getSelectedIds: function () {
        const ids = [];
        const selected = this.getSelectionModel().getSelections();

        for (const i in selected) {
            if (!selected.hasOwnProperty(i)) {
                continue;
            }
            ids.push({
                delivery_id: selected[i]['id'],
                payment_id: this.config.record.id,
            });
        }

        return ids;
    },
});
Ext.reg('ms3-grid-payment-deliveries', ms3.grid.PaymentDeliveries);
