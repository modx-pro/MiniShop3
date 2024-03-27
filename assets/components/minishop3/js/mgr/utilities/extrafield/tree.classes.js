/**
 * Generates the Extra Fiels Classes Tree
 *
 * @class ms3.tree.ExtraFieldClass
 * @extends MODx.tree.Tree
 * @param {Object} config An object of options.
 * @xtype ms3-tree-extrafieldclass
 */
MODx.tree.ExtraFieldClass = function (config) {
    config = config || {};
    Ext.applyIf(config, {
        title: _('user_groups'),
        id: 'ms3-tree-extrafieldclass',
        url: MODx.config.connector_url,
        action: 'MiniShop3\\Processors\\Utilities\\ExtraField\\GetClassNodes',
        rootIconCls: 'icon-group',
        //root_id: 'n_ug_0',
        //root_name: _('user_groups'),
        enableDD: false,
        rootVisible: false,
        //ddAppendOnly: true,
        useDefaultToolbar: false,
        tbar: [],

        resize: {
            fn: function(cmp) {
                if (Ext.getCmp('ms3-grid-extrafields').hidden) {
                    cmp.layout.west.getSplitBar().el.hide();
                }
            },
            scope: this
        },
        refresh: {
            fn: function() {
                this.setActiveClassKey();
            },
            scope: this
        }
    });
    MODx.tree.ExtraFieldClass.superclass.constructor.call(this, config);
};

Ext.extend(MODx.tree.ExtraFieldClass, MODx.tree.Tree, {
    windows: {

    },

    /**
     * Handles tree clicks
     * @param {Object} n The node clicked
     * @param {Object} e The event object
     */
    _handleClick: function (n, e) {
        e.stopEvent();
        e.preventDefault();

        this.setActiveClassKey(n.attributes.type);

        if (this.disableHref) { return true; }
        if (e.ctrlKey) { return true; }
        return true;
    },
    getMenu: function () {
        return [];
    },
    setActiveClassKey: function(class_key) {
        const grid = Ext.getCmp('ms3-grid-extrafields'),
              westPanel = Ext.getCmp('ms3-tree-panel-extrafield').layout.west
        ;
        if (class_key) {
            grid.store.removeAll();
            grid.show();
            westPanel.getSplitBar().el.show();
            grid.config.class_key = class_key;
            grid.store.baseParams.class = class_key;
            grid.store.load();
        } else {
            grid.hide();
            westPanel.getSplitBar().el.hide();
           
        }
    }
});
Ext.reg('ms3-tree-extrafieldclass', MODx.tree.ExtraFieldClass);