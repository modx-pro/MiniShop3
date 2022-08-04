minishop.panel.Orders = function (config) {
    config = config || {};

    Ext.apply(config, {
        cls: 'container',
        items: [{
            xtype: 'modx-tabs',
            id: 'minishop-orders-tabs',
            stateful: true,
            stateId: 'minishop-orders-tabs',
            stateEvents: ['tabchange'],
            getState: function () {
                return {
                    activeTab: this.items.indexOf(this.getActiveTab())
                };
            },
            deferredRender: false,
            items: [{
                title: _('ms_orders'),
                layout: 'anchor',
                items: [{
                    xtype: 'minishop-form-orders',
                    id: 'minishop-form-orders',
                }, {
                    xtype: 'minishop-grid-orders',
                    id: 'minishop-grid-orders',
                }],
            }]
        }]
    });
    minishop.panel.Orders.superclass.constructor.call(this, config);
};
Ext.extend(minishop.panel.Orders, MODx.Panel);
Ext.reg('minishop-panel-orders', minishop.panel.Orders);
