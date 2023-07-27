minishop.page.Orders = function (config) {
    config = config || {};
    Ext.apply(config, {
        formpanel: 'minishop-panel-orders',
        cls: 'container',
        buttons: this.getButtons(config),
        components: [{
            xtype: 'minishop-panel-orders'
        }]
    });
    minishop.page.Orders.superclass.constructor.call(this, config);
};
Ext.extend(minishop.page.Orders, MODx.Component, {
    getButtons: function (config) {
        const b = [];

        if (MODx.perm.mssetting_list) {
            b.push({
                text: _('ms_settings')
                ,id: 'ms2-abtn-settings'
                ,handler: function () {
                    MODx.loadPage('?', 'a=mgr/settings&namespace=minishop3');
                }
            });
        }

        return b;
    }
});
Ext.reg('minishop-page-orders', minishop.page.Orders);
