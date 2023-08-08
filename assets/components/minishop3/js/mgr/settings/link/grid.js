ms3.grid.Link = function (config) {
    config = config || {};
    if (!config.id) {
        config.id = 'ms3-grid-link';
    }
    config.disableContextMenuAction = true;

    Ext.applyIf(config, {
        baseParams: {
            action: 'MiniShop3\\Processors\\Settings\\Link\\GetList'
        },
        stateful: true,
        stateId: config.id,
        multi_select: true,
    });
    ms3.grid.Link.superclass.constructor.call(this, config);
};
Ext.extend(ms3.grid.Link, ms3.grid.Default, {

    getFields: function () {
        return ['id', 'type', 'name', 'description', 'actions'];
    },

    getTopBar: function () {
        return [{
            text: '<i class="icon icon-plus"></i> ' + _('ms3_btn_create'),
            handler: this.createLink,
            scope: this
        }, '->', this.getSearchField()];
    },

    getColumns: function () {
        return [
            {header: _('ms3_id'), dataIndex: 'id', width: 50, sortable: true},
            {header: _('ms3_name'), dataIndex: 'name', width: 100, sortable: true},
            {
                header: _('ms3_type'),
                dataIndex: 'type',
                width: 100,
                renderer: function (value) {
                    return _('ms3_link_' + value);
                }
            },
            {header: _('ms3_description'), dataIndex: 'description', width: 100},
            {
                header: _('ms3_actions'),
                dataIndex: 'actions',
                id: 'actions',
                width: 50,
                renderer: ms3.utils.renderActions
            }
        ];
    },

    getListeners: function () {
        return {
            rowDblClick: function (grid, rowIndex, e) {
                const row = grid.store.getAt(rowIndex);
                this.updateLink(grid, e, row);
            },
        };
    },

    linkAction: function (method) {
        const ids = this._getSelectedIds();
        if (!ids.length) {
            return false;
        }
        MODx.Ajax.request({
            url: ms3.config.connector_url,
            params: {
                action: 'MiniShop3\\Processors\\Settings\\Link\\Multiple',
                method: method,
                ids: Ext.util.JSON.encode(ids),
            },
            listeners: {
                success: {
                    fn: function () {
                        //noinspection JSUnresolvedFunction
                        this.refresh();
                    }, scope: this
                },
                failure: {
                    fn: function (response) {
                        MODx.msg.alert(_('error'), response.message);
                    }, scope: this
                },
            }
        })
    },

    createLink: function (btn, e) {
        let w = Ext.getCmp('ms3-window-link-create');
        if (w) {
            w.close();
        }
        w = MODx.load({
            xtype: 'ms3-window-link-create',
            id: 'ms3-window-link-create',
            listeners: {
                success: {
                    fn: function () {
                        this.refresh();
                    }, scope: this
                }
            }
        });
        w.show(e.target);
    },

    updateLink: function (btn, e, row) {
        if (typeof(row) != 'undefined') {
            this.menu.record = row.data;
        }

        let w = Ext.getCmp('ms3-window-link-update');
        if (w) {
            w.close();
        }
        w = MODx.load({
            xtype: 'ms3-window-link-update',
            id: 'ms3-window-link-update',
            title: this.menu.record['name'],
            record: this.menu.record,
            listeners: {
                success: {
                    fn: function () {
                        this.refresh();
                    }, scope: this
                }
            }
        });
        w.fp.getForm().reset();
        w.fp.getForm().setValues(this.menu.record);
        w.show(e.target);
    },

    removeLink: function () {
        const ids = this._getSelectedIds();

        Ext.MessageBox.confirm(
            _('ms3_menu_remove_title'),
            ids.length > 1
                ? _('ms3_menu_remove_multiple_confirm')
                : _('ms3_menu_remove_confirm'),
            function (val) {
                if (val == 'yes') {
                    this.linkAction('Remove');
                }
            },
            this
        );
    },

});
Ext.reg('ms3-grid-link', ms3.grid.Link);
