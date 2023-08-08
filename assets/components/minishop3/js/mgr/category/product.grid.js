ms3.grid.Products = function (config) {
    config = config || {};
    if (!config.id) {
        config.id = 'ms3-grid-products';
    }
    config.disableContextMenuAction = true;

    Ext.applyIf(config, {
        baseParams: {
            action: 'MiniShop3\\Processors\\Product\\GetList',
            parent: config.resource,
            sort: 'menuindex',
            dir: 'asc',
        },
        multi_select: true,
        stateful: true,
        stateId: config.id,
        save_action: 'MiniShop3\\Processors\\Product\\UpdateFromGrid',
        autosave: true,
        save_callback: this.updateRow,
        ddGroup: 'ms-products',
        ddAction: 'MiniShop3\\Processors\\Product\\Sort',
        enableDragDrop: true,
        defaultNotify: false
    });
    ms3.grid.Products.superclass.constructor.call(this, config);
    if (!this.defaultNotify) {
        this.ddText = ''; }
};
Ext.extend(ms3.grid.Products, ms3.grid.Default, {

    getFields: function () {
        const fields = ms3.config['product_fields'];
        const options = ms3.config['option_fields'];

        for (let i = 0; i < options.length; i++) {
            const index = fields.indexOf(options[i].key);
            if (index > 0) {
                fields[index] = 'options-' + fields[index];
            }
        }
        return fields;
    },

    getOptionFields: function (config) {
        const options = ms3.config['option_fields'];
        const fields = {};
        for (let i = 0; i < options.length; i++) {
            const field = ms3.utils.getExtField(config, options[i].key, options[i], 'extra-column');
            if (field) {
                Ext.apply(fields, field);
            }
        }

        return fields;
    },

    getCategoryOptions: function (config) {
        const option_columns = [];
        const options = this.getOptionFields(config);

        for (i in options) {
            if (!options.hasOwnProperty(i)) {
                continue;
            }
            option_columns[i] = options[i];
        }

        return option_columns;
    },

    getColumns: function () {
        const columns = {
            id: {sortable: true, width: 40},
            pagetitle: {width: 100, sortable: true, id: 'product-title', renderer: this._renderPagetitle},
            longtitle: {width: 50, sortable: true, editor: {xtype: 'textfield'}},
            description: {width: 100, sortable: false, editor: {xtype: 'textarea'}},
            alias: {width: 50, sortable: true, editor: {xtype: 'textfield'}},
            introtext: {width: 100, sortable: false, editor: {xtype: 'textarea'}},
            content: {width: 100, sortable: false, editor: {xtype: 'textarea'}},
            template: {width: 100, sortable: true, editor: {xtype: 'modx-combo-template'}},
            createdby: {width: 100, sortable: true, editor: {xtype: 'ms3-combo-user', name: 'createdby'}},
            createdon: {
                width: 50,
                sortable: true,
                editor: {xtype: 'ms3-xdatetime', timePosition: 'below'},
                renderer: ms3.utils.formatDate
            },
            editedby: {width: 100, sortable: true, editor: {xtype: 'ms3-combo-user', name: 'editedby'}},
            editedon: {
                width: 50,
                sortable: true,
                editor: {xtype: 'ms3-xdatetime', timePosition: 'below'},
                renderer: ms3.utils.formatDate
            },
            deleted: {width: 50, sortable: true, editor: {xtype: 'combo-boolean', renderer: 'boolean'}},
            deletedon: {
                width: 50,
                sortable: true,
                editor: {xtype: 'ms3-xdatetime', timePosition: 'below'},
                renderer: ms3.utils.formatDate
            },
            deletedby: {width: 100, sortable: true, editor: {xtype: 'ms3-combo-user', name: 'deletedby'}},
            published: {width: 50, sortable: true, editor: {xtype: 'combo-boolean', renderer: 'boolean'}},
            publishedon: {
                width: 50,
                sortable: true,
                editor: {xtype: 'ms3-xdatetime', timePosition: 'below'},
                renderer: ms3.utils.formatDate
            },
            publishedby: {width: 100, sortable: true, editor: {xtype: 'ms3-combo-user', name: 'publishedby'}},
            menutitle: {width: 100, sortable: true, editor: {xtype: 'textfield'}},
            menuindex: {width: 35, sortable: true, header: 'IDx', editor: {xtype: 'numberfield'}},
            uri: {width: 50, sortable: true, editor: {xtype: 'textfield'}},
            uri_override: {width: 50, sortable: true, editor: {xtype: 'combo-boolean', renderer: 'boolean'}},
            show_in_tree: {width: 50, sortable: true, editor: {xtype: 'combo-boolean', renderer: 'boolean'}},
            hidemenu: {width: 50, sortable: true, editor: {xtype: 'combo-boolean', renderer: 'boolean'}},
            richtext: {width: 100, sortable: true, editor: {xtype: 'combo-boolean', renderer: 'boolean'}},
            searchable: {width: 100, sortable: true, editor: {xtype: 'combo-boolean', renderer: 'boolean'}},
            cacheable: {width: 100, sortable: true, editor: {xtype: 'combo-boolean', renderer: 'boolean'}},

            'new': {width: 50, sortable: true, editor: {xtype: 'combo-boolean', renderer: 'boolean'}},
            favorite: {width: 50, sortable: true, editor: {xtype: 'combo-boolean', renderer: 'boolean'}},
            popular: {width: 50, sortable: true, editor: {xtype: 'combo-boolean', renderer: 'boolean'}},
            article: {width: 50, sortable: true, editor: {xtype: 'textfield'}},
            price: {width: 50, sortable: true, editor: {xtype: 'numberfield', decimalPrecision: 2}},
            old_price: {width: 50, sortable: true, editor: {xtype: 'numberfield', decimalPrecision: 2}},
            weight: {width: 50, sortable: true, editor: {xtype: 'numberfield', decimalPrecision: 3}},
            image: {width: 50, sortable: false, renderer: ms3.utils.renderImage, id: 'product-image'},
            thumb: {width: 50, sortable: false, renderer: ms3.utils.renderImage, id: 'product-thumb'},
            vendor_id: {
                width: 50,
                sortable: true,
                renderer: this._renderVendor,
                editor: {xtype: 'ms3-combo-vendor'},
            },
            vendor_name: {width: 50, sortable: true, header: _('ms3_product_vendor')},
            made_in: {width: 50, sortable: true, editor: {xtype: 'ms3-combo-autocomplete', name: 'made_in'}},
            //color: {width:50, sortable:false, editor: {xtype: 'ms3-combo-options', name: 'color'}},
            //size: {width:50, sortable:false, editor: {xtype: 'ms3-combo-options', name: 'size'}},
            //tags: {width:50, sortable:false, editor: {xtype: 'ms3-combo-options', name: 'tags'}},
            actions: {
                header: _('ms3_actions'),
                id: 'actions',
                width: 75,
                sortable: false,
                renderer: ms3.utils.renderActions
            }
        };

        let i,add;
        for (i in ms3.plugin) {
            if (!ms3.plugin.hasOwnProperty(i)) {
                continue;
            }
            if (typeof(ms3.plugin[i]['getColumns']) == 'function') {
                add = ms3.plugin[i].getColumns();
                Ext.apply(columns, add);
            }
        }

        let option_columns = [];
        if (ms3.config['show_options']) {
            option_columns = this.getCategoryOptions(ms3.config);
        }

        const fields = [];
        for (i in ms3.config['grid_fields']) {
            if (!ms3.config['grid_fields'].hasOwnProperty(i)) {
                continue;
            }
            const field = ms3.config['grid_fields'][i];
            if (columns[field]) {
                Ext.applyIf(columns[field], {
                    header: _('ms3_product_' + field),
                    dataIndex: field
                });
                fields.push(columns[field]);
            } else if (option_columns[field]) {
                fields.push(option_columns[field]);
            }
        }

        return fields;
    },

    getTopBar: function () {
        return [{
            text: (MODx.config.mgr_tree_icon_msproduct ? String.format('<i class="{0}"></i> ', Ext.util.Format.htmlEncode(MODx.config.mgr_tree_icon_msproduct)) : '') + _('ms3_product_create'),
            handler: this.createProduct,
            scope: this
        }, '-', {
            text: (MODx.config.mgr_tree_icon_mscategory ? String.format('<i class="{0}"></i> ', Ext.util.Format.htmlEncode(MODx.config.mgr_tree_icon_mscategory)) : '') + _('ms3_category_create'),
            handler: this.createCategory,
            scope: this
        }, '-', {
            text: '<i class="icon icon-trash-o action-red"></i>',
            handler: this._emptyRecycleBin,
            scope: this,
        }, '->', {
            xtype: 'xcheckbox',
            name: 'nested',
            width: 200,
            boxLabel: _('ms3_category_show_nested'),
            ctCls: 'tbar-checkbox',
            checked: MODx.config['ms3_category_show_nested_products'] == 1,
            listeners: {
                check: {fn: this.nestedFilter, scope: this}
            }
        }, '-', this.getSearchField()];
    },

    nestedFilter: function (checkbox, checked) {
        const s = this.getStore();
        s.baseParams.nested = checked ? 1 : 0;
        this.getBottomToolbar().changePage(1);
    },

    updateRow: function () {
        this.refresh();
    },

    productAction: function (method) {
        const ids = this._getSelectedIds();
        if (!ids.length) {
            return false;
        }
        MODx.Ajax.request({
            url: ms3.config.connector_url,
            params: {
                action: 'MiniShop3\\Processors\\Product\\Multiple',
                method: method,
                ids: Ext.util.JSON.encode(ids),
            },
            listeners: {
                success: {
                    fn: function () {
                        //noinspection JSUnresolvedFunction
                        this.reloadTree();
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

    createProduct: function () {
        MODx.loadPage('resource/create', 'class_key=MiniShop3\\Model\\msProduct&parent=' + MODx.request.id + '&context_key=' + MODx.ctx);
    },

    createCategory: function () {
        MODx.loadPage('resource/create', 'class_key=MiniShop3\\Model\\msCategory&parent=' + MODx.request.id + '&context_key=' + MODx.ctx);
    },

    viewProduct: function () {
        window.open(this.menu.record['preview_url']);
        return false;
    },

    editProduct: function () {
        MODx.loadPage('resource/update', 'id=' + this.menu.record.id);
    },

    deleteProduct: function () {
        this.productAction('Delete');
    },

    undeleteProduct: function () {
        this.productAction('Undelete');
    },

    publishProduct: function () {
        this.productAction('Publish');
    },

    unpublishProduct: function () {
        this.productAction('Unpublish');
    },

    showProduct: function () {
        this.productAction('Show');
    },

    hideProduct: function () {
        this.productAction('Hide');
    },

    duplicateProduct: function () {
        const r = this.menu.record;
        const w = MODx.load({
            xtype: 'modx-window-resource-duplicate',
            resource: r.id,
            hasChildren: 0,
            listeners: {
                success: {
                    fn: function () {
                        this.reloadTree();
                        this.refresh();
                    }, scope: this
                }
            }
        });
        w.config.hasChildren = 0;
        w.setValues(r.data);
        w.show();
    },

    generatePreview: function(){
        const ids = this._getSelectedIds();
        if (!ids.length) {
            return false;
        }
        MODx.Ajax.request({
            url: ms3.config.connector_url,
            params: {
                action: 'MiniShop3\\Processors\\Gallery\\Multiple',
                method: 'GenerateAll',
                ids: Ext.util.JSON.encode(ids),
            },
            listeners: {
                success: {
                    fn: function () {
                        //noinspection JSUnresolvedFunction
                        this.reloadTree();
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

    reloadTree: function (ids) {
        if (ids === undefined || typeof(ids) != 'object') {
            ids = this._getSelectedIds();
        }
        const store = this.getStore();
        const parents = {};
        for (const i in ids) {
            if (!ids.hasOwnProperty(i)) {
                continue;
            }
            const item = store.data.map[Number(ids[i])];
            if (item !== undefined) {
                parents[item['data']['parent']] = item['data']['context_key'];
            }
        }
        const tree = Ext.getCmp('modx-resource-tree');
        if (tree) {
            for (const parent in parents) {
                if (!parents.hasOwnProperty(parent)) {
                    continue;
                }
                const ctx = parents[parent];
                const node = tree.getNodeById(ctx + '_' + parent);
                if (typeof(node) !== 'undefined') {
                    node.leaf = false;
                    node.reload(function () {
                        this.expand();
                    });
                }
            }
        }
    },

    _renderVendor: function (value, cell, row) {
        return row.data['vendor_name'];
    },

    _renderPagetitle: function (value, cell, row) {
        const link = ms3.utils.productLink(value, row['data']['id']);
        if (!row.data['category_name']) {
            return String.format(
                '<div class="native-product"><span class="id">({0})</span>{1}</div>',
                row['data']['id'],
                link
            );
        } else {
            const category_link = ms3.utils.productLink(row.data['category_name'], row.data['parent']);
            return String.format(
                '<div class="nested-product">\
                    <span class="id">({0})</span>{1}\
                    <div class="product-category">{2}</div>\
                </div>',
                row['data']['id'],
                link,
                category_link
            );
        }
    },

    _emptyRecycleBin: function () {
        MODx.msg.confirm({
            title: _('empty_recycle_bin'),
            text: _('empty_recycle_bin_confirm'),
            url: MODx.config['connector_url'],
            params: {
                action: 'MODX\\Revolution\\Processors\\Resource\\EmptyRecycleBin',
            },
            listeners: {
                success: {
                    fn: function () {
                        const tree = Ext.getCmp('modx-resource-tree');
                        if (tree) {
                            Ext.select('div.deleted', tree.getRootNode()).remove();
                        }
                        //noinspection JSUnresolvedFunction
                        this.refresh();
                    },
                    scope: this
                }
            }
        });
    },

});
Ext.reg('ms3-grid-products', ms3.grid.Products);
