minishop.grid.Link = function (config) {
    config = config || {};
    if (!config.id) {
        config.id = 'minishop-grid-link';
    }

    Ext.applyIf(config, {
        baseParams: {
            action: 'MiniShop3\\Processors\\Settings\\Link\\GetList'
        },
        stateful: true,
        stateId: config.id,
        multi_select: true,
    });
    minishop.grid.Link.superclass.constructor.call(this, config);
};
Ext.extend(minishop.grid.Link, minishop.grid.Default, {

    getFields: function () {
        return ['id', 'type', 'name', 'description', 'actions'];
    },

    getTopBar: function () {
        return [{
            text: '<i class="icon icon-plus"></i> ' + _('ms_btn_create'),
            handler: this.createLink,
            scope: this
        }, '->', this.getSearchField()];
    },

    getColumns: function () {
        return [
            {header: _('ms_id'), dataIndex: 'id', width: 50, sortable: true},
            {header: _('ms_name'), dataIndex: 'name', width: 100, sortable: true},
            {
                header: _('ms_type'),
                dataIndex: 'type',
                width: 100,
                renderer: function (value) {
                    return _('ms_link_' + value);
                }
            },
            {header: _('ms_description'), dataIndex: 'description', width: 100},
            {
                header: _('ms_actions'),
                dataIndex: 'actions',
                id: 'actions',
                width: 50,
                renderer: minishop.utils.renderActions
            }
        ];
    },

    actionsColumnRenderer: function(value, metaData, record, rowIndex, colIndex, store) {
        const actions = this.getActions.apply(this, [record, rowIndex, colIndex, store]);
        return this._getActionsColumnTpl().apply({
            actions: actions
        });
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
            url: minishop.config['connector_url'],
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
        let w = Ext.getCmp('minishop-window-link-create');
        if (w) {
            w.close();
        }
        w = MODx.load({
            xtype: 'minishop-window-link-create',
            id: 'minishop-window-link-create',
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

        let w = Ext.getCmp('minishop-window-link-update');
        if (w) {
            w.close();
        }
        w = MODx.load({
            xtype: 'minishop-window-link-update',
            id: 'minishop-window-link-update',
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
            _('ms_menu_remove_title'),
            ids.length > 1
                ? _('ms_menu_remove_multiple_confirm')
                : _('ms_menu_remove_confirm'),
            function (val) {
                if (val == 'yes') {
                    this.linkAction('Remove');
                }
            },
            this
        );
    },

});
Ext.reg('minishop-grid-link', minishop.grid.Link);
