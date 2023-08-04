ms3.panel.Orders = function (config) {
    config = config || {};

    Ext.apply(config, {
        cls: 'container',
        items: [{
            html: '<h2>' + _('ms3_header') + ' :: ' + _('ms3_orders') + '</h2>',
            cls: 'modx-page-header',
        },{
            xtype: 'modx-tabs',
            id: 'ms3-orders-tabs',
            stateful: true,
            stateId: 'ms3-orders-tabs',
            stateEvents: ['tabchange'],
            getState: function () {
                return {
                    activeTab: this.items.indexOf(this.getActiveTab())
                };
            },
            deferredRender: false,
            items: [{
                title: _('ms3_orders'),
                layout: 'anchor',
                items: [{
                    xtype: 'ms3-form-orders',
                    id: 'ms3-form-orders',
                }, {
                    xtype: 'ms3-grid-orders',
                    id: 'ms3-grid-orders',
                }],
            }]
        }]
    });
    ms3.panel.Orders.superclass.constructor.call(this, config);
};
Ext.extend(ms3.panel.Orders, MODx.Panel);
Ext.reg('ms3-panel-orders', ms3.panel.Orders);
