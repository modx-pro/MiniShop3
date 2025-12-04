/**
 * HTML wrapper for Vue Notification Center application
 *
 * Creates container for mounting Vue application
 */

// Create simple component for MODX
Ext.reg('ms3-notifications-vue-wrapper', Ext.extend(Ext.Component, {
    initComponent: function() {
        Ext.apply(this, {
            html: '<div id="ms3-notifications-vue-wrapper" class="vueApp" style="padding: 20px; height: 100%;"></div>'
        });

        this.constructor.superclass.initComponent.call(this);
    }
}));
