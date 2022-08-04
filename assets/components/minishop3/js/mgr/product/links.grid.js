minishop.grid.ProductLinks = function (config) {
    config = config || {};

    Ext.applyIf(config, {
        id: 'minishop-grid-product-link',
        baseParams: {
            action: 'MiniShop3\\Processors\\Product\\ProductLink\\GetList',
            master: config.record.id,
            sort: 'name',
            dir: 'ASC',
        },
        multi_select: true,
    });
    minishop.grid.ProductLinks.superclass.constructor.call(this, config);
};
Ext.extend(minishop.grid.ProductLinks, minishop.grid.Default, {

    getFields: function () {
        return [
            'link', 'type', 'name', 'master', 'slave', 'description',
            'master_pagetitle', 'slave_pagetitle', 'actions'
        ];
    },

    getColumns: function () {
        return [
            {header: _('ms_link_name'), dataIndex: 'name', width: 75, sortable: true},
            {header: _('ms_type'), dataIndex: 'type', width: 75, sortable: true, renderer: this._renderType},
            {
                header: _('ms_link_master'),
                dataIndex: 'master_pagetitle',
                width: 125,
                sortable: true,
                renderer: this._renderMaster,
                scope: this,
        },
            {
                header: _('ms_link_slave'),
                dataIndex: 'slave_pagetitle',
                width: 125,
                sortable: true,
                renderer: this._renderSlave,
                scope: this
        },
            {header: '', dataIndex: 'actions', width: 35, id: 'actions', renderer: minishop.utils.renderActions}
        ];
    },

    getTopBar: function () {
        return [{
            text: '<i class="icon icon-plus"></i> ' + _('ms_btn_create'),
            handler: this.createLink,
            scope: this
        }, '->', this.getSearchField()];
    },

    createLink: function (btn, e) {
        let w = Ext.getCmp('minishop-product-link-create');
        if (w) {
            w.close();
        }
        w = MODx.load({
            xtype: 'minishop-product-link-create',
            id: 'minishop-product-link-create',
            baseParams: {
                action: 'MiniShop3\\Processors\\Product\\ProductLink\\Create',
                master: btn.scope.record.id
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

    linkAction: function (method) {
        const ids = this._getSelectedIds();
        if (!ids.length) {
            return false;
        }
        MODx.Ajax.request({
            url: this.config.url,
            params: {
                action: 'MiniShop3\\Processors\\Product\\ProductLink\\Multiple',
                method: method,
                ids: Ext.util.JSON.encode(ids),
            },
            listeners: {
                success: {
                    fn: function () {
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

    _renderType: function (value) {
        return _('ms_link_' + value);
    },

    _renderMaster: function (value, cell, row) {
        return row.data.master == this.record.id
            ? value
            : minishop.utils.productLink(value, row.data.master);
    },

    _renderSlave: function (value, cell, row) {
        return row.data.slave == this.record.id
            ? value
            : minishop.utils.productLink(value, row.data.slave);
    },

    _getSelectedIds: function () {
        const ids = [];
        const selected = this.getSelectionModel().getSelections();

        for (const i in selected) {
            if (!selected.hasOwnProperty(i)) {
                continue;
            }
            ids.push({
                link: selected[i]['data']['link'],
                master: selected[i]['data']['master'],
                slave: selected[i]['data']['slave'],
            });
        }

        return ids;
    },

});
Ext.reg('minishop-product-links', minishop.grid.ProductLinks);
