ms3.page.Settings = function (config) {
    config = config || {};
    Ext.apply(config, {
        formpanel: 'ms3-panel-settings',
        cls: 'container',
        buttons: this.getButtons(),
        components: [{
            xtype: 'ms3-panel-settings'
        }]
    });
    ms3.page.Settings.superclass.constructor.call(this, config);
};
Ext.extend(ms3.page.Settings, MODx.Component, {
    getButtons: function (config) {
        const b = [];

        if (MODx.perm.msorder_list) {
            b.push({
                text: _('ms3_orders'),
                id: 'ms-abtn-orders',
                cls: 'primary-button',
                handler: function () {
                    MODx.loadPage('?', 'a=mgr/orders&namespace=minishop3');
                }
            });
        }

        return b;
    }
});
Ext.reg('ms3-page-settings', ms3.page.Settings);
