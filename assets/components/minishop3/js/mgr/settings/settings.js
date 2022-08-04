minishop.page.Settings = function (config) {
    config = config || {};
    Ext.apply(config, {
        formpanel: 'minishop-panel-settings',
        cls: 'container',
        buttons: this.getButtons(),
        components: [{
            xtype: 'minishop-panel-settings'
        }]
    });
    minishop.page.Settings.superclass.constructor.call(this, config);
};
Ext.extend(minishop.page.Settings, MODx.Component, {
    getButtons: function (config) {
        const b = [];

        if (MODx.perm.msorder_list) {
            b.push({
                text: _('ms_orders'),
                id: 'ms-abtn-orders',
                cls: 'primary-button',
                handler: function () {
                    MODx.loadPage('?', 'a=mgr/orders&namespace=minishop');
                }
            });
        }

        return b;
    }
});
Ext.reg('minishop-page-settings', minishop.page.Settings);
