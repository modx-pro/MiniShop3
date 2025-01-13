ms3.page.Utilities = function (config) {
    config = config || {};
    Ext.apply(config, {
        formpanel: 'ms3-panel-utilities',
        cls: 'container',
        buttons: this.getButtons(config),
        components: [{
            xtype: 'ms3-panel-utilities'
        }]
    });
    ms3.page.Utilities.superclass.constructor.call(this, config);
};
Ext.extend(ms3.page.Utilities, MODx.Component, {
    getButtons: function (config) {
        const b = [];

        if (MODx.perm.mssetting_list) {
            b.push({
                text: _('ms3_orders'),
                id: 'ms-abtn-orders',
                cls: 'primary-button',
                handler: function () {
                    MODx.loadPage('?', 'a=mgr/orders&namespace=minishop3');
                }
            }, {
                text: _('ms3_settings'),
                id: 'ms2-abtn-settings',
                handler: function () {
                    MODx.loadPage('?', 'a=mgr/settings&namespace=minishop3');
                }
            });
        }

        return b;
    }
});
Ext.reg('ms3-page-utilities', ms3.page.Utilities);
