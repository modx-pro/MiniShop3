ms3.grid.CategoryOption = function (config) {
    config = config || {};
    if (!config.id) {
        config.id = 'ms3-grid-category-option';
    }

    Ext.applyIf(config, {
        cls: 'ms3-grid' || config['cls'],
        baseParams: {
            action: 'MiniShop3\\Processors\\Category\\Option\\GetList',
            category: config.record['id'],
            sort: 'rank',
            dir: 'asc',
        },
        multi_select: true,
        stateful: true,
        stateId: config.id,
        autosave: true,
        save_action: 'MiniShop3\\Processors\\Category\\Option\\UpdateFromGrid',
        plugins: this.getPlugins(config),
        ddGroup: 'dd-option-grid',
        enableDragDrop: true,
    });
    ms3.grid.CategoryOption.superclass.constructor.call(this, config);
};
Ext.extend(ms3.grid.CategoryOption, ms3.grid.Default, {

    getFields: function () {
        return [
            'id', 'key', 'caption', 'type', 'active', 'required', 'rank', 'value',
            'category_id', 'option_id', 'actions'
        ];
    },

    getColumns: function () {
        return [
            {header: _('id'), dataIndex: 'id', width: 35, sortable: true},
            {header: _('ms3_ft_name'), dataIndex: 'key', width: 50, sortable: true},
            {header: _('ms3_ft_caption'), dataIndex: 'caption', width: 75, sortable: true},
            {header: _('ms3_ft_type'), dataIndex: 'type', width: 75, renderer: this._renderType},
            {header: _('ms3_default_value'), dataIndex: 'value', width: 75, editor: {xtype: 'textfield'}},
            {header: _('ms3_ft_rank'), dataIndex: 'rank', width: 50, editor: {xtype: 'numberfield'}, hidden: true, sortable: true},
            {
                header: _('ms3_actions'),
                width: 75,
                id: 'actions',
                renderer: ms3.utils.renderActions,
                sortable: false
        }
        ];
    },

    getTopBar: function () {
        return [{
            text: '<i class="icon icon-plus"></i> ' + _('ms3_btn_addoption'),
            handler: this.addOption,
            scope: this
        }, {
            text: '<i class="icon icon-files-o"></i> ' + _('ms3_btn_copy'),
            handler: this.copyCategory,
            scope: this
        }, '->', this.getSearchField()];
    },

    getPlugins: function () {
        return [new Ext.ux.dd.GridDragDropRowOrder({
            copy: false,
            scrollable: true,
            targetCfg: {},
            listeners: {
                afterrowmove: {
                    fn: this.onAfterRowMove,
                    scope: this
                }
            }
        })];
    },

    addOption: function (btn, e) {
        let w = Ext.getCmp('ms3-window-option-add');
        if (w) {
            return false;
        }
        w = MODx.load({
            id: 'ms3-window-option-add',
            xtype: 'ms3-window-option-add',
            listeners: {
                success: {
                    fn: function () {
                        this.refresh();
                    }, scope: this
                }
            }
        });

        const f = w.fp.getForm();
        f.reset();
        f.setValues({category_id: MODx.request.id});
        w.show(e.target);
    },

    copyCategory: function (btn, e) {
        let w = Ext.getCmp('ms3-window-copy-category');
        if (w) {
            return false;
        }
        w = MODx.load({
            id: 'ms3-window-copy-category',
            xtype: 'ms3-window-copy-category',
            listeners: {
                success: {
                    fn: function () {
                        this.refresh();
                    }, scope: this
                }
            }
        });

        const f = w.fp.getForm();
        f.reset();
        f.setValues({category_to: MODx.request.id});
        w.show(e.target);
    },

    optionAction: function (method) {
        const ids = this._getSelectedIds();
        if (!ids.length) {
            return false;
        }

        MODx.Ajax.request({
            url: ms3.config['connector_url'],
            params: {
                action: 'MiniShop3\\Processors\\Category\\Option\\Multiple',
                method: method,
                ids: Ext.util.JSON.encode(ids),
            },
            listeners: {
                success: {
                    fn: function () {
                        //noinspection JSUnresolvedFunction
                        this.refresh();
                        //noinspection JSUnresolvedFunction
                        this.getSelectionModel().clearSelections(true)
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

    activateOption: function () {
        this.optionAction('Activate');
    },

    deactivateOption: function () {
        this.optionAction('Deactivate');
    },

    requireOption: function () {
        this.optionAction('Require');
    },

    unrequireOption: function () {
        this.optionAction('Unrequire');
    },

    deleteOption: function () {
        this.optionAction('Delete');
    },

    onAfterRowMove: function () {
        const s = this.getStore();
        const start = this.getBottomToolbar().cursor;
        let size = this.getBottomToolbar().pageSize;
        const total = s.getTotalCount();
        if (size > total) {
            size = total;
        }
        for (let x = 0; x < size; x++) {
            const brec = s.getAt(x);
            brec.set('rank', start + x);
            brec.commit();
            this.saveRecord({record: brec});
        }
        return true;
    },

    _renderType: function (value) {
        return _('ms3_ft_' + value);
    },

    _getSelectedIds: function () {
        const ids = [];
        const selected = this.getSelectionModel().getSelections();
        for (const i in selected) {
            if (!selected.hasOwnProperty(i)) {
                continue;
            }
            ids.push({
                option_id: selected[i]['data']['option_id'],
                category_id: selected[i]['data']['category_id'],
            });
        }

        return ids;
    },

});
Ext.reg('ms3-grid-category-option', ms3.grid.CategoryOption);
