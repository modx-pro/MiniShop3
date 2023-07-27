ms3.grid.Status = function (config) {
    config = config || {};
    if (!config.id) {
        config.id = 'ms3-grid-status';
    }
    config.disableContextMenuAction = true;

    Ext.applyIf(config, {
        baseParams: {
            action: 'MiniShop3\\Processors\\Settings\\Status\\GetList',
            sort: 'position',
            dir: 'asc',
        },
        stateful: true,
        stateId: config.id,
        ddGroup: 'ms3-settings-status',
        ddAction: 'MiniShop3\\Processors\\Settings\\Status\\Sort',
        enableDragDrop: true,
        multi_select: true,
    });
    ms3.grid.Status.superclass.constructor.call(this, config);
};
Ext.extend(ms3.grid.Status, ms3.grid.Default, {

    getFields: function () {
        return [
            'id', 'name', 'description', 'color', 'email_user', 'email_manager',
            'subject_user', 'subject_manager', 'body_user', 'body_manager', 'active',
            'final', 'fixed', 'position', 'editable', 'actions'
        ];
    },

    getColumns: function () {
        return [
            {header: _('ms3_id'), dataIndex: 'id', width: 30},
            {header: _('ms3_name'), dataIndex: 'name', width: 50, renderer: ms3.utils.renderBadge},
            {header: _('ms3_email_user'), dataIndex: 'email_user', width: 50, renderer: this._renderBoolean},
            {header: _('ms3_email_manager'), dataIndex: 'email_manager', width: 50, renderer: this._renderBoolean},
            {header: _('ms3_status_final'), dataIndex: 'final', width: 50, renderer: this._renderBoolean},
            {header: _('ms3_status_fixed'), dataIndex: 'fixed', width: 50, renderer: this._renderBoolean},
            {header: _('ms3_rank'), dataIndex: 'position', width: 35, hidden: true},
            {
                header: _('ms3_actions'),
                dataIndex: 'actions',
                id: 'actions',
                width: 50,
                renderer: ms3.utils.renderActions
        }
        ];
    },

    getTopBar: function () {
        return [{
            text: '<i class="icon icon-plus"></i> ' + _('ms3_btn_create'),
            handler: this.createStatus,
            scope: this
        }, '->', this.getSearchField()];
    },

    getListeners: function () {
        return {
            rowDblClick: function (grid, rowIndex, e) {
                const row = grid.store.getAt(rowIndex);
                this.updateStatus(grid, e, row);
            },
        };
    },

    statusAction: function (method) {
        const ids = this._getSelectedIds();
        if (!ids.length) {
            return false;
        }
        MODx.Ajax.request({
            url: ms3.config['connector_url'],
            params: {
                action: 'MiniShop3\\Processors\\Settings\\Status\\Multiple',
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

    createStatus: function (btn, e) {
        let w = Ext.getCmp('ms3-window-status-create');
        if (w) {
            w.close();
        }
        w = MODx.load({
            xtype: 'ms3-window-status-create',
            id: 'ms3-window-status-create',
            record: {
                color: '000000',
                active: 1
            },
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

    updateStatus: function (btn, e, row) {
        if (typeof(row) != 'undefined') {
            this.menu.record = row.data;
        }

        let w = Ext.getCmp('ms3-window-status-update');
        if (w) {
            w.close();
        }
        w = MODx.load({
            xtype: 'ms3-window-status-update',
            id: 'ms3-window-status-update',
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

    enableStatus: function () {
        this.statusAction('Enable');
    },

    disableStatus: function () {
        this.statusAction('Disable');
    },

    removeStatus: function () {
        const ids = this._getSelectedIds();

        Ext.MessageBox.confirm(
            _('ms3_menu_remove_title'),
            ids.length > 1
                ? _('ms3_menu_remove_multiple_confirm')
                : _('ms3_menu_remove_confirm'),
            function (val) {
                if (val == 'yes') {
                    this.statusAction('Remove');
                }
            },
            this
        );
    },

    _renderBoolean: function (value, cell, row) {
        let color, text;

        if (value == 0 || value == false || value == undefined) {
            color = 'red';
            text = _('no');
        } else {
            color = 'green';
            text = _('yes');
        }

        return row.data['active']
            ? String.format('<span class="{0}">{1}</span>', color, text)
            : text;
    },
});
Ext.reg('ms3-grid-status', ms3.grid.Status);
