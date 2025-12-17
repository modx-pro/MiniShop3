/**
 * Simple HTML wrapper for Vue order edit page
 *
 * Creates container for mounting Vue application
 */

Ext.reg('ms3-order-vue-wrapper', Ext.extend(Ext.Component, {
    initComponent: function() {
        Ext.apply(this, {
            html: '<div id="ms3-order-vue-wrapper" class="vueApp" style="padding: 20px; height: 100%;"></div>'
        });

        this.constructor.superclass.initComponent.call(this);
    }
}));
