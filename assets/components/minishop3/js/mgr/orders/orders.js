ms3.page.Orders = function (config) {
    config = config || {};
    Ext.apply(config, {
        formpanel: 'ms3-panel-orders',
        cls: 'container',
        buttons: this.getButtons(config),
        components: [{
            xtype: 'ms3-panel-orders'
        }]
    });
    ms3.page.Orders.superclass.constructor.call(this, config);
};
Ext.extend(ms3.page.Orders, MODx.Component, {
    getButtons: function (config) {
        const b = [];

        if (MODx.perm.mssetting_list) {
            b.push({
                text: _('ms3_settings')
                ,id: 'ms2-abtn-settings'
                ,handler: function () {
                    MODx.loadPage('?', 'a=mgr/settings&namespace=minishop3');
                }
            });
        }

        return b;
    }
});
Ext.reg('ms3-page-orders', ms3.page.Orders);
