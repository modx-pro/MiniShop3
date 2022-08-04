minishop.grid.ComboboxOptions = function (config) {
    config = config || {};
    if (!config.id) {
        config.id = 'minishop-grid-combobox-options';
    }

    Ext.applyIf(config, {
        autoHeight: false,
        height: 200,
        style: 'padding-top: 5px;',
        hideHeaders: true,
        anchor: '100%',
        layout: 'anchor',
        viewConfig: {
            forceFit: true
        },
        fields: ['dd', 'value', 'remove'],
        columns: this.getColumns(config),
        plugins: this.getPlugins(config),
        listeners: this.getListeners(config),
        bbar: this.getBottomBar(config),
        bodyCssClass: 'x-menu',
        cls: 'minishop-grid',
    });
    minishop.grid.ComboboxOptions.superclass.constructor.call(this, config);
};

Ext.extend(minishop.grid.ComboboxOptions, MODx.grid.LocalGrid, {

    getColumns: function () {
        return [{
            header: _('sort'),
            dataIndex: 'dd',
            width: 10,
            align: 'center',
            renderer: function () {
                return String.format(
                    '<div class="sort icon icon-sort" style="cursor:move;" title="{0}"></div>',
                    _('move')
                );
            }
        }, {
            header: _('value'),
            dataIndex: 'value',
            editor: {
                xtype: 'textfield',
                listeners: {
                    change: {fn: this.prepareProperties, scope: this}
                }
            }
        }, {
            header: _('remove'),
            dataIndex: 'remove',
            width: 10,
            id: 'actions',
            align: 'center',
            renderer: function () {
                return String.format(
                    '\
                    <ul class="minishop-row-actions">\
                        <li>\
                            <button class="btn btn-default icon icon-remove action-red" title="{0}" action="removeOption"></button>\
                        </li>\
                    </ul>',
                    _('remove')
                );
            }
        }];
    },

    getBottomBar: function (config) {
        return [{
            xtype: 'hidden',
            id: config.id + '-properties',
            name: 'properties'
        }];
    },

    getPlugins: function () {
        return [new Ext.ux.dd.GridDragDropRowOrder({
            copy: false,
            scrollable: true,
            targetCfg: {},
            listeners: {
                afterrowmove: {fn: this.prepareProperties, scope: this}
            }
        })]
    },

    getListeners: function () {
        return {
            viewready: {fn: this.prepareValues, scope: this},
            afteredit: {
                fn: function () {
                    this.prepareProperties();
                    this.addOption();
                }, scope: this
            }
        };
    },

    prepareValues: function () {
        if (this.record.properties && this.record.properties['values']) {
            Ext.each(this.record.properties['values'], function (item) {
                this.store.add(new Ext.data.Record({
                    value: item
                }));
            }, this);
            this.store.add(new Ext.data.Record({
                value: ''
            }));
        } else {
            this.store.add(new Ext.data.Record({
                value: ''
            }));
            this.focusValueCell(0);
        }
        this.prepareProperties();
    },

    prepareProperties: function () {
        let properties = {
            values: this.store.collect('value')
        };
        properties = Ext.util.JSON.encode(properties);
        Ext.getCmp(this.config.id + '-properties').setValue(properties);
    },

    addOption: function () {
        if (this.store.collect('value').length == this.store.data.length) {
            this.store.add(new Ext.data.Record({
                value: ''
            }));
            this.focusValueCell(this.store.data.length - 1);
        } else {
            Ext.Msg.alert(_('error'), _('ms_err_value_duplicate'), function () {
                this.focusValueCell(this.store.data.length - 1);
            }, this);
        }

        this.prepareProperties();
    },

    removeOption: function () {
        const record = this.getSelectionModel().getSelected();
        if (!record) {
            return false;
        }
        if (this.store.data.length == 1) {
            this.store.getAt(0).set('value', '');
            this.focusValueCell(0);
        } else if ((this.store.collect('value').length != this.store.data.length) && record.data['value'] == '') {
            this.focusValueCell(this.store.data.length - 1);
        } else {
            this.store.remove(record);
        }
        this.prepareProperties();
    },

    focusValueCell: function (row) {
        this.startEditing(row, 1);
    },

    onClick: function (e) {
        const elem = e.getTarget();
        if (elem.nodeName == 'BUTTON') {
            const row = this.getSelectionModel().getSelected();
            if (typeof(row) != 'undefined') {
                const action = elem.getAttribute('action');
                if (typeof this[action] === 'function') {
                    this.menu.record = row.data;
                    return this[action](this);
                }
            }
        }

        return this.processEvent('click', e);
    },

});
Ext.reg('minishop-grid-combobox-options', minishop.grid.ComboboxOptions);
