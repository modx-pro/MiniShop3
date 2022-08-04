minishop.grid.Logs = function (config) {
    config = config || {};
    if (!config.id) {
        config.id = 'minishop-grid-order-logs';
    }
    Ext.applyIf(config, {
        baseParams: {
            action: 'MiniShop3\\Processors\\Order\\GetLog',
            order_id: config.order_id,
            type: 'status'
        },
        cls: 'minishop-grid',
        multi_select: false,
        stateful: true,
        stateId: config.id,
        pageSize: Math.round(MODx.config['default_per_page'] / 2),
    });
    minishop.grid.Logs.superclass.constructor.call(this, config);
};
Ext.extend(minishop.grid.Logs, minishop.grid.Default, {

    getFields: function () {
        return ['id', 'user_id', 'username', 'fullname', 'timestamp', 'action', 'entry', 'color'];
    },

    getColumns: function () {
        return [
            {header: _('ms_id'), dataIndex: 'id', hidden: true, sortable: true, width: 50},
            {header: _('ms_username'), dataIndex: 'username', width: 75, renderer: function (val, cell, row) {
                return minishop.utils.userLink(val, row.data['user_id'], true);
            }},
            {header: _('ms_fullname'), dataIndex: 'fullname', width: 100},
            {
                header: _('ms_timestamp'),
                dataIndex: 'timestamp',
                sortable: true,
                renderer: minishop.utils.formatDate,
                width: 75
        },
            {header: _('ms_action'), dataIndex: 'action', width: 50},
            {header: _('ms_entry'), dataIndex: 'entry', width: 50, renderer: minishop.utils.renderBadge}
        ];
    },

    getTopBar: function () {
        return [];
    },

});
Ext.reg('minishop-grid-order-logs', minishop.grid.Logs);
