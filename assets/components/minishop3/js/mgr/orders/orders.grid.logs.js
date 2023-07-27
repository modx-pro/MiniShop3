ms3.grid.Logs = function (config) {
    config = config || {};
    if (!config.id) {
        config.id = 'ms3-grid-order-logs';
    }
    Ext.applyIf(config, {
        baseParams: {
            action: 'MiniShop3\\Processors\\Order\\GetLog',
            order_id: config.order_id,
            type: 'status'
        },
        cls: 'ms3-grid',
        multi_select: false,
        stateful: true,
        stateId: config.id,
        pageSize: Math.round(MODx.config['default_per_page'] / 2),
    });
    ms3.grid.Logs.superclass.constructor.call(this, config);
};
Ext.extend(ms3.grid.Logs, ms3.grid.Default, {

    getFields: function () {
        return ['id', 'user_id', 'username', 'fullname', 'timestamp', 'action', 'entry', 'color'];
    },

    getColumns: function () {
        return [
            {header: _('ms3_id'), dataIndex: 'id', hidden: true, sortable: true, width: 50},
            {header: _('ms3_username'), dataIndex: 'username', width: 75, renderer: function (val, cell, row) {
                return ms3.utils.userLink(val, row.data['user_id'], true);
            }},
            {header: _('ms3_fullname'), dataIndex: 'fullname', width: 100},
            {
                header: _('ms3_timestamp'),
                dataIndex: 'timestamp',
                sortable: true,
                renderer: ms3.utils.formatDate,
                width: 75
        },
            {header: _('ms3_action'), dataIndex: 'action', width: 50},
            {header: _('ms3_entry'), dataIndex: 'entry', width: 50, renderer: ms3.utils.renderBadge}
        ];
    },

    getTopBar: function () {
        return [];
    },

});
Ext.reg('ms3-grid-order-logs', ms3.grid.Logs);
