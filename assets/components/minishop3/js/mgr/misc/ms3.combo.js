ms3.combo.ComboBoxDefault = function (config) {
    config = config || {};

    Ext.applyIf(config, {
        assertValue : function () {
            let val = this.getRawValue(),
                rec;
            if (this.valueField && Ext.isDefined(this.value)) {
                rec = this.findRecord(this.valueField, this.value);
            }
            if (rec && rec.get(this.displayField) != val) {
                rec = null;
            }
            if (!rec && this.forceSelection) {
                if (val.length > 0 && val != this.emptyText) {
                    this.el.dom.value = Ext.value(this.lastSelectionText, '');
                    this.applyEmptyText();
                } else {
                    this.clearValue();
                }
            } else {
                if (rec && this.valueField) {
                    if (this.value == val) {
                        return;
                    }
                    val = rec.get(this.valueField || this.displayField);
                }
                this.setValue(val);
            }
        },

    });
    ms3.combo.ComboBoxDefault.superclass.constructor.call(this, config);
};
Ext.extend(ms3.combo.ComboBoxDefault, MODx.combo.ComboBox);
Ext.reg('ms3-combo-combobox-default', ms3.combo.ComboBoxDefault);


ms3.combo.Search = function (config) {
    config = config || {};
    Ext.applyIf(config, {
        xtype: 'twintrigger',
        ctCls: 'x-field-search',
        allowBlank: true,
        msgTarget: 'under',
        emptyText: _('search'),
        name: 'query',
        triggerAction: 'all',
        clearBtnCls: 'x-field-search-clear',
        searchBtnCls: 'x-field-search-go',
        onTrigger1Click: this._triggerSearch,
        onTrigger2Click: this._triggerClear,
    });
    ms3.combo.Search.superclass.constructor.call(this, config);
    this.on('render', function () {
        this.getEl().addKeyListener(Ext.EventObject.ENTER, function () {
            this._triggerSearch();
        }, this);
    });
    this.addEvents('clear', 'search');
};
Ext.extend(ms3.combo.Search, Ext.form.TwinTriggerField, {

    initComponent: function () {
        Ext.form.TwinTriggerField.superclass.initComponent.call(this);
        this.triggerConfig = {
            tag: 'span',
            cls: 'x-field-search-btns',
            cn: [
                {tag: 'div', cls: 'x-form-trigger ' + this.searchBtnCls},
                {tag: 'div', cls: 'x-form-trigger ' + this.clearBtnCls}
            ]
        };
    },

    _triggerSearch: function () {
        this.fireEvent('search', this);
    },

    _triggerClear: function () {
        this.fireEvent('clear', this);
    },

});
Ext.reg('ms3-combo-search', ms3.combo.Search);
Ext.reg('ms3-field-search', ms3.combo.Search);


ms3.combo.User = function (config) {
    config = config || {};
    Ext.applyIf(config, {
        name: 'user',
        fieldLabel: config.name || 'createdby',
        hiddenName: config.name || 'createdby',
        displayField: 'fullname',
        valueField: 'id',
        anchor: '99%',
        fields: ['username', 'id', 'fullname'],
        pageSize: 20,
        typeAhead: false,
        editable: true,
        allowBlank: false,
        url: ms3.config.connector_url,
        baseParams: {
            action: 'MiniShop3\\Processors\\System\\User\\GetList',
            combo: true,
        },
        tpl: new Ext.XTemplate(
            '\
            <tpl for=".">\
                <div class="x-combo-list-item">\
                    <span>\
                        <small>({id})</small>\
                        <b>{username}</b>\
                        <tpl if="fullname && fullname != username"> - {fullname}</tpl>\
                    </span>\
                </div>\
            </tpl>',
            {compiled: true}
        ),
    });
    ms3.combo.User.superclass.constructor.call(this, config);
};
Ext.extend(ms3.combo.User, ms3.combo.ComboBoxDefault);
Ext.reg('ms3-combo-user', ms3.combo.User);

ms3.combo.Customer = function (config) {
    config = config || {};
    Ext.applyIf(config, {
        name: 'customer_id',
        fieldLabel: config.name || 'createdby',
        hiddenName: config.name || 'createdby',
        displayField: 'fullname',
        valueField: 'id',
        anchor: '99%',
        fields: ['id', 'fullname'],
        pageSize: 20,
        typeAhead: false,
        editable: true,
        allowBlank: false,
        url: ms3.config.connector_url,
        baseParams: {
            action: 'MiniShop3\\Processors\\Customer\\GetListCombobox',
            combo: true,
        },
        tpl: new Ext.XTemplate(
            '\
            <tpl for=".">\
                <div class="x-combo-list-item">\
                    <span>\
                        <small>({id})</small>\
                        <b>{fullname}</b>\
                    </span>\
                </div>\
            </tpl>',
            {compiled: true}
        ),
    });
    ms3.combo.Customer.superclass.constructor.call(this, config);
};
Ext.extend(ms3.combo.Customer, ms3.combo.ComboBoxDefault);
Ext.reg('ms3-combo-customer', ms3.combo.Customer);


ms3.combo.Category = function (config) {
    config = config || {};
    Ext.applyIf(config, {
        id: 'ms3-combo-section',
        fieldLabel: _('ms3_category'),
        description: '<b>[[*parent]]</b><br />' + _('ms3_product_parent_help'),
        fields: ['id', 'pagetitle', 'parents'],
        valueField: 'id',
        displayField: 'pagetitle',
        name: 'parent-cmb',
        hiddenName: 'parent-cmp',
        allowBlank: false,
        url: ms3.config.connector_url,
        baseParams: {
            action: 'MiniShop3\\Processors\\Category\\GetCats',
            combo: true,
            id: config.value
        },
        tpl: new Ext.XTemplate('\
            <tpl for="."><div class="x-combo-list-item ms3-category-list-item">\
                <tpl if="parents">\
                    <div class="parents">\
                        <tpl for="parents">\
                            <nobr><small>{pagetitle} / </small></nobr>\
                        </tpl>\
                    </div>\
                </tpl>\
                <span>\
                    <small>({id})</small> <b>{pagetitle}</b>\
                </span>\
            </div></tpl>', {
    compiled: true
        }),
    itemSelector: 'div.ms3-category-list-item',
    pageSize: 20,
    editable: true
    });
    ms3.combo.Category.superclass.constructor.call(this, config);
    this.on('expand', function () {
        if (!!this.pageTb) {
            this.pageTb.show();
        }
    });
};
Ext.extend(ms3.combo.Category, ms3.combo.ComboBoxDefault);
Ext.reg('ms3-combo-category', ms3.combo.Category);


ms3.combo.DateTime = function (config) {
    config = config || {};
    Ext.applyIf(config, {
        timePosition: 'right',
        allowBlank: true,
        hiddenFormat: 'Y-m-d H:i:s',
        dateFormat: MODx.config['manager_date_format'],
        timeFormat: MODx.config['manager_time_format'],
        dateWidth: 120,
        timeWidth: 120
    });
    ms3.combo.DateTime.superclass.constructor.call(this, config);
};
Ext.extend(ms3.combo.DateTime, Ext.ux.form.DateTime);
Ext.reg('ms3-xdatetime', ms3.combo.DateTime);


ms3.combo.Autocomplete = function (config) {
    config = config || {};

    Ext.applyIf(config, {
        name: config.name,
        fieldLabel: _('ms3_product_' + config.name),
        id: 'ms3-product-' + config.name,
        hiddenName: config.name,
        displayField: config.name,
        valueField: config.name,
        anchor: '99%',
        fields: [config.name],
        //pageSize: 20,
        forceSelection: false,
        url: ms3.config.connector_url,
        typeAhead: true,
        editable: true,
        allowBlank: true,
        baseParams: {
            action: 'MiniShop3\\Processors\\Product\\Autocomplete',
            name: config.name,
            combo: true,
            limit: 0
        },
        hideTrigger: false,
    });
    ms3.combo.Autocomplete.superclass.constructor.call(this, config);
};
Ext.extend(ms3.combo.Autocomplete, ms3.combo.ComboBoxDefault);
Ext.reg('ms3-combo-autocomplete', ms3.combo.Autocomplete);


ms3.combo.Vendor = function (config) {
    config = config || {};

    Ext.applyIf(config, {
        name: config.name || 'vendor',
        fieldLabel: _('ms3_product_' + config.name || 'vendor'),
        hiddenName: config.name || 'vendor',
        displayField: 'name',
        valueField: 'id',
        anchor: '99%',
        fields: ['name', 'id'],
        pageSize: 20,
        url: ms3.config.connector_url,
        typeAhead: true,
        editable: true,
        allowBlank: true,
        emptyText: _('no'),
        minChars: 1,
        forceSelection: false,
        baseParams: {
            action: 'MiniShop3\\Processors\\Settings\\Vendor\\GetList',
            combo: true,
            id: config.value,
        }
    });
    ms3.combo.Vendor.superclass.constructor.call(this, config);
    this.on('expand', function () {
        if (!!this.pageTb) {
            this.pageTb.show();
        }
    });
};
Ext.extend(ms3.combo.Vendor, ms3.combo.ComboBoxDefault);
Ext.reg('ms3-combo-vendor', ms3.combo.Vendor);


ms3.combo.Source = function (config) {
    config = config || {};

    Ext.applyIf(config, {
        name: config.name || 'source-cmb',
        id: 'ms3-product-source',
        hiddenName: 'source-cmb',
        displayField: 'name',
        valueField: 'id',
        width: 300,
        listWidth: 300,
        fieldLabel: _('ms3_product_' + config.name || 'source_id'),
        anchor: '99%',
        allowBlank: false
    });
    ms3.combo.Source.superclass.constructor.call(this, config);
};
Ext.extend(ms3.combo.Source, MODx.combo.MediaSource);
Ext.reg('ms3-combo-source', ms3.combo.Source);


ms3.combo.Options = function (config) {
    config = config || {};

    if (config.mode == 'remote') {
        Ext.applyIf(config, {
            pageSize: 10,
            paging: true,
        });
    }

    Ext.applyIf(config, {
        xtype: 'superboxselect',
        allowBlank: true,
        msgTarget: 'under',
        allowAddNewData: true,
        addNewDataOnBlur: true,
        allowSorting: true,
        pinList: false,
        resizable: true,
        lazyInit: false,
        name: config.name || 'tags',
        anchor: '100%',
        minChars: 1,
        store: new Ext.data.JsonStore({
            id: (config.name || 'tags') + '-store',
            root: 'results',
            autoLoad: false,
            autoSave: false,
            totalProperty: 'total',
            fields: ['value'],
            url: ms3.config.connector_url,
            baseParams: {
                action: 'MiniShop3\\Processors\\Product\\GetOptions',
                key: config.name
            }
        }),
    mode: 'remote',
    displayField: 'value',
    valueField: 'value',
    triggerAction: 'all',
    extraItemCls: 'x-tag',
    expandBtnCls: 'x-form-trigger',
    clearBtnCls: 'x-form-trigger',
    displayFieldTpl: config.displayFieldTpl || '{value}',
        // fix for setValue
    addValue : function (value) {
        if (Ext.isEmpty(value)) {
            return;
        }
        let values = value;
        if (!Ext.isArray(value)) {
            value = '' + value;
            values = value.split(this.valueDelimiter);
        }
        Ext.each(values,function (val) {
            const record = this.findRecord(this.valueField, val);
            if (record) {
                this.addRecord(record);
            }
            this.remoteLookup.push(val);
        },this);
        if (this.mode === 'remote') {
            const q = this.remoteLookup.join(this.queryValuesDelimiter);
            this.doQuery(q,false, true);
        }
    },
        // fix similar queries
        shouldQuery : function (q) {
            if (this.lastQuery) {
                return (q !== this.lastQuery);
            }
            return true;
        },
        onRender: function (ct, position) {
            this.constructor.prototype.onRender.apply(this, arguments);
            if (config.allowSorting) {
                this.initSorting();
            }
        },
        setValueEx : function (data) {
            // fix for setValue
            if (this.rendered && this.valueField) {
                if (!Ext.isArray(data)) {
                    data = [data];
                }
                const values = [];
                Ext.each(data,function (value, i) {
                    if (typeof value == 'string' && value != '') {
                        value = {};
                        value[this.valueField] = data[i];
                    }
                    if (typeof value == 'object' && value[this.valueField]) {
                        values.push(value);
                    }
                },this);
                data = values;
            }

            this.constructor.prototype.setValueEx.apply(this, [data]);
        },
    });
    config.name += '[]';

    Ext.apply(config, {
        listeners: {
            beforequery: {
                fn: this.beforequery,
                scope: this
            },
            newitem: {
                fn: this.newitem,
                scope: this
            },
        }
    });

    ms3.combo.Options.superclass.constructor.call(this, config);
};
Ext.extend(ms3.combo.Options, Ext.ux.form.SuperBoxSelect, {

    beforequery: function (o) {
        // reset sort
        o.combo.store.sortInfo = '';
        if (o.forceAll !== false) {
            exclude = o.combo.getValue().split(o.combo.valueDelimiter);
        } else {
            exclude = [];
        }
        o.combo.store.baseParams.exclude = Ext.util.JSON.encode(exclude);
    },

    newitem: function (bs, v) {
        bs.addNewItem({value: v});
    },

    initSorting: function () {
        const _this = this;

        if (typeof Sortable !== undefined) {
            const item = document.querySelectorAll("#" + this.outerWrapEl.id + " ul")[0];
            if (item) {
                item.setAttribute("data-xcomponentid", this.id);
                new Sortable(item, {
                    onEnd: function (evt) {
                        if (evt.target) {
                            const cmpId = evt.target.getAttribute("data-xcomponentid");
                            const cmp = Ext.getCmp(cmpId);
                            if (cmp) {
                                _this.refreshSorting(cmp);
                                MODx.fireResourceFormChange();
                            } else {
                                console.log("Unable to reference xComponentContext.");
                            }
                        }
                    }
                });
            } else {
                console.log("Unable to find select element");
            }
        } else {
            console.log("Sortable undefined");
        }
    },

    refreshSorting: function (cmp) {
        const viewList = cmp.items.items;
        const dataInputList = document.querySelectorAll("#" + cmp.outerWrapEl.dom.id + " .x-superboxselect-input");
        const getElementIndex = function (item) {
            const nodeList = Array.prototype.slice.call(item.parentElement.children);
            return nodeList.indexOf(item);
        };
        const getElementByIndex = function (index) {
            return nodeList[index];
        };
        const getElementByValue = function (val, list) {
            for (let i = 0; i < list.length; i += 1) {
                if (list[i].value == val) {
                    return list[i];
                }
            }
        };
        const sortElementsByListIndex = function (list, callback) {
            list.sort(compare);
            if (callback instanceof Function) {
                callback();
            }
        };
        const syncElementsByValue = function (list1, list2, callback) {
            const targetListRootElement = list2[0].parentElement;
            if (targetListRootElement) {
                for (let i = 0; i < list1.length; i += 1) {
                    let targetItemIndex;
                    const item = list1[i];
                    const targetItem = getElementByValue(item.value, list2);
                    const initialTargetElement = list2[i];
                    if (targetItem !== null && initialTargetElement !== undefined) {
                        targetListRootElement.insertBefore(targetItem, initialTargetElement);
                    }
                }
            } else {
                console.debug("syncElementsByValue(), Unable to reference list root element.");
                return false;
            }
            if (callback instanceof Function) {
                callback();
            }
        };
        const compare = function (a, b) {
            const aIndex = getElementIndex(a.el.dom);
            const bIndex = getElementIndex(b.el.dom);
            if (aIndex < bIndex) {
                return -1;
            }
            if (aIndex > bIndex) {
                return 1;
            }
            return 0;
        };
        sortElementsByListIndex(viewList);
        syncElementsByValue(viewList, dataInputList[0].children);
        cmp.value = cmp.getValue();
    },

});
Ext.reg('ms3-combo-options', ms3.combo.Options);


ms3.combo.Chunk = function (config) {
    config = config || {};
    Ext.applyIf(config, {
        name: 'chunk',
        hiddenName: config.name || 'chunk',
        displayField: 'name',
        valueField: 'id',
        editable: true,
        fields: ['id', 'name'],
        pageSize: 20,
        emptyText: _('ms3_combo_select'),
        hideMode: 'offsets',
        url: ms3.config.connector_url,
        baseParams: {
            action: 'MiniShop3\\Processors\\System\\Element\\Chunk\\GetList',
            mode: 'chunks'
        },
        tpl: new Ext.XTemplate(
            '\
            <tpl for=".">\
                <div class="x-combo-list-item">\
                    <span>\
                        <small>({id})</small>\
                        <b>{name}</b>\
                    </span>\
                </div>\
            </tpl>',
            {compiled: true}
        ),
    });
    ms3.combo.Chunk.superclass.constructor.call(this, config);
};
Ext.extend(ms3.combo.Chunk, ms3.combo.ComboBoxDefault);
Ext.reg('ms3-combo-chunk', ms3.combo.Chunk);


ms3.combo.Resource = function (config) {
    config = config || {};
    Ext.applyIf(config, {
        name: 'resource',
        hiddenName: 'resource',
        displayField: 'pagetitle',
        valueField: 'id',
        editable: true,
        fields: ['id', 'pagetitle'],
        pageSize: 20,
        emptyText: _('ms3_combo_select'),
        hideMode: 'offsets',
        url: ms3.config.connector_url,
        baseParams: {
            action: 'MiniShop3\\Processors\\System\\Element\\Resource\\GetList',
            combo: true
        }
    });
    ms3.combo.Resource.superclass.constructor.call(this, config);
};
Ext.extend(ms3.combo.Resource, ms3.combo.ComboBoxDefault);
Ext.reg('ms3-combo-resource', ms3.combo.Resource);


ms3.combo.Context = function (config) {
    config = config || {};
    Ext.applyIf(config, {
        name: 'context',
        hiddenName: 'context',
        displayField: 'name',
        valueField: 'key',
        editable: true,
        fields: ['key', 'name'],
        pageSize: 20,
        emptyText: _('ms3_combo_select'),
        hideMode: 'offsets',
        url: ms3.config.connector_url,
        baseParams: {
            action: 'MiniShop3\\Processors\\System\\Element\\Context\\GetList',
            combo: true
        }
    });
    ms3.combo.Context.superclass.constructor.call(this, config);
};
Ext.extend(ms3.combo.Context, ms3.combo.ComboBoxDefault);
Ext.reg('ms3-combo-context', ms3.combo.Context);


ms3.combo.Browser = function (config) {
    config = config || {};

    if (config.length != 0 && config.openTo != undefined) {
        if (!/^\//.test(config.openTo)) {
            config.openTo = '/' + config.openTo;
        }
        if (!/$\//.test(config.openTo)) {
            let tmp = config.openTo.split('/');
            delete tmp[tmp.length - 1];
            tmp = tmp.join('/');
            config.openTo = tmp.substr(1)
        }
    }

    Ext.applyIf(config, {
        width: 300,
        triggerAction: 'all'
    });
    ms3.combo.Browser.superclass.constructor.call(this, config);
    this.config = config;
};
Ext.extend(ms3.combo.Browser, Ext.form.TriggerField, {
    browser: null,

    onTriggerClick: function () {
        if (this.disabled) {
            return false;
        }
        const browser = MODx.load({
            xtype: 'modx-browser',
            id: Ext.id(),
            multiple: true,
            source: this.config.source || MODx.config['default_media_source'],
            rootVisible: this.config.rootVisible || false,
            allowedFileTypes: this.config.allowedFileTypes || '',
            wctx: this.config.wctx || 'web',
            openTo: this.config.openTo || '',
            rootId: this.config.rootId || '/',
            hideSourceCombo: this.config.hideSourceCombo || false,
            hideFiles: this.config.hideFiles || true,
            listeners: {
                select: {
                    fn: function (data) {
                        this.setValue(data.fullRelativeUrl);
                        this.fireEvent('select', data);
                    }, scope: this
                }
            },
        });
        browser.win.buttons[0].on('disable', function () {
            this.enable()
        });
        browser.win.tree.on('click', function (n) {
            this.setValue(this.getPath(n));
        }, this);
        browser.win.tree.on('dblclick', function (n) {
            this.setValue(this.getPath(n));
            browser.hide()
        }, this);
        browser.show();
    },

    getPath: function (n) {
        if (n.id == '/') {
            return '';
        }

        return n.attributes.path + '/';
    }
});
Ext.reg('ms3-combo-browser', ms3.combo.Browser);


ms3.combo.listeners_disable = {
    render: function () {
        this.store.on('load', function () {
            if (this.store.getTotalCount() == 1 && this.store.getAt(0).id == this.value) {
                this.readOnly = true;
                this.addClass('disabled');
            } else {
                this.readOnly = false;
                this.removeClass('disabled');
            }
        }, this);
    }
};


ms3.combo.Status = function (config) {
    config = config || {};

    Ext.applyIf(config, {
        name: 'status_id',
        id: 'ms3-combo-status',
        hiddenName: 'status_id',
        displayField: 'name',
        valueField: 'id',
        fields: ['id', 'name'],
        pageSize: 10,
        emptyText: _('ms3_combo_select_status'),
        url: ms3.config.connector_url,
        baseParams: {
            action: 'MiniShop3\\Processors\\Settings\\Status\\GetList',
            combo: true,
            addall: config.addall || 0,
            order_id: config.order_id || 0
        },
        listeners: ms3.combo.listeners_disable
    });
    ms3.combo.Status.superclass.constructor.call(this, config);
};
Ext.extend(ms3.combo.Status, ms3.combo.ComboBoxDefault);
Ext.reg('ms3-combo-status', ms3.combo.Status);


ms3.combo.Delivery = function (config) {
    config = config || {};

    Ext.applyIf(config, {
        name: 'delivery_id',
        id: 'ms3-combo-delivery',
        hiddenName: 'delivery_id',
        displayField: 'name',
        valueField: 'id',
        fields: ['id', 'name'],
        pageSize: 10,
        emptyText: _('ms3_combo_select'),
        url: ms3.config.connector_url,
        baseParams: {
            action: 'MiniShop3\\Processors\\Settings\\Delivery\\GetList',
            combo: true
        },
        listeners: ms3.combo.listeners_disable
    });
    ms3.combo.Delivery.superclass.constructor.call(this, config);
};
Ext.extend(ms3.combo.Delivery, ms3.combo.ComboBoxDefault);
Ext.reg('ms3-combo-delivery', ms3.combo.Delivery);


ms3.combo.Payment = function (config) {
    config = config || {};

    Ext.applyIf(config, {
        name: 'payment_id',
        id: 'ms3-combo-payment',
        hiddenName: 'payment_id',
        displayField: 'name',
        valueField: 'id',
        fields: ['id', 'name'],
        pageSize: 10,
        emptyText: _('ms3_combo_select'),
        url: ms3.config.connector_url,
        baseParams: {
            action: 'MiniShop3\\Processors\\Settings\\Payment\\GetList',
            combo: true,
            delivery_id: config.delivery_id || 0
        },
        listeners: ms3.combo.listeners_disable
    });
    ms3.combo.Payment.superclass.constructor.call(this, config);
};
Ext.extend(ms3.combo.Payment, ms3.combo.ComboBoxDefault);
Ext.reg('ms3-combo-payment', ms3.combo.Payment);


MODx.combo.LinkType = function (config) {
    config = config || {};
    Ext.applyIf(config, {
        store: new Ext.data.SimpleStore({
            fields: ['type', 'name', 'description'],
            data: this.getTypes()
        }),
    emptyText: _('ms3_combo_select'),
    displayField: 'name',
    valueField: 'type',
    hiddenName: 'type',
    mode: 'local',
    triggerAction: 'all',
    editable: false,
    selectOnFocus: false,
    preventRender: true,
    forceSelection: true,
    enableKeyEvents: true
    });
    MODx.combo.LinkType.superclass.constructor.call(this, config);
};
Ext.extend(MODx.combo.LinkType, ms3.combo.ComboBoxDefault, {

    getTypes: function () {
        const array = [];
        const types = ['many_to_many', 'one_to_many', 'many_to_one', 'one_to_one'];
        types.forEach(t => {
            array.push([t, _('ms3_link_' + t), _('ms3_link_' + t + '_desc')]);
        })
        return array;
    }
});
Ext.reg('ms3-combo-link-type', MODx.combo.LinkType);


ms3.combo.Link = function (config) {
    config = config || {};

    Ext.applyIf(config, {
        name: 'link',
        id: 'ms3-combo-link',
        hiddenName: 'link',
        displayField: 'name',
        valueField: 'id',
        fields: ['id', 'name'],
        pageSize: 10,
        editable: true,
        emptyText: _('ms3_combo_select'),
        url: ms3.config.connector_url,
        baseParams: {
            action: 'MiniShop3\\Processors\\Settings\\Link\\GetList',
            combo: true
        }
    });
    ms3.combo.Link.superclass.constructor.call(this, config);
};
Ext.extend(ms3.combo.Link, ms3.combo.ComboBoxDefault);
Ext.reg('ms3-combo-link', ms3.combo.Link);


ms3.combo.Product = function (config) {
    config = config || {};
    Ext.applyIf(config, {
        id: 'ms3-combo-product',
        fieldLabel: _('ms3_product_name'),
        fields: ['id', 'pagetitle', 'parents'],
        valueField: 'id',
        displayField: 'pagetitle',
        name: 'product',
        hiddenName: 'product',
        allowBlank: false,
        url: ms3.config.connector_url,
        baseParams: {
            action: 'MiniShop3\\Processors\\Product\\GetList',
            combo: true,
            id: config.value
        },
        tpl: new Ext.XTemplate('\
            <tpl for=".">\
                <div class="x-combo-list-item ms3-product-list-item" ext:qtip="{pagetitle}">\
                    <tpl if="parents">\
                        <span class="parents">\
                            <tpl for="parents">\
                                <nobr><small>{pagetitle} / </small></nobr>\
                            </tpl>\
                        </span><br/>\
                    </tpl>\
                    <span><small>({id})</small> <b>{pagetitle}</b></span>\
                </div>\
            </tpl>', {compiled: true}),
    pageSize: 5,
    emptyText: _('ms3_combo_select'),
    editable: true,
    });
    ms3.combo.Product.superclass.constructor.call(this, config);
};
Ext.extend(ms3.combo.Product, ms3.combo.ComboBoxDefault);
Ext.reg('ms3-combo-product', ms3.combo.Product);


ms3.combo.ExtraOptions = function (config) {
    config = config || {};
    Ext.applyIf(config, {
        id: 'ms3-combo-extra-options',
        fieldLabel: _('ms3_option'),
        name: 'option',
        hiddenName: 'option',
        displayField: 'key',
        valueField: 'id',
        pageSize: 20,
        fields: ['id', 'key', 'caption', 'type'],
        url: ms3.config.connector_url,
        baseParams: {
            action: 'MiniShop3\\Processors\\Settings\\Option\\GetList',
        },
        tpl: new Ext.XTemplate('<tpl for="."><div class="x-combo-list-item"><span style="font-weight: bold">{caption}</span>'
            , ' - <span style="font-style:italic">{key}</span><br />{[this.getLang(values.type)]}</div></tpl>', {
                getLang: function (type) {
                    return _("ms3_ft_" + type);
                }
            }),
    allowBlank: false,
    editable: true,
    });
    ms3.combo.ExtraOptions.superclass.constructor.call(this, config);
};
Ext.extend(ms3.combo.ExtraOptions, ms3.combo.ComboBoxDefault);
Ext.reg('ms3-combo-extra-options', ms3.combo.ExtraOptions);


ms3.combo.OptionTypes = function (config) {
    config = config || {};
    Ext.applyIf(config, {
        id: 'ms3-combo-option-type',
        fieldLabel: _('ms3_option_type'),
        name: 'type',
        hiddenName: 'type',
        displayField: 'caption',
        valueField: 'name',
        pageSize: 20,
        fields: ['name', 'caption', 'xtype'],
        url: ms3.config.connector_url,
        baseParams: {
            action: 'MiniShop3\\Processors\\Settings\\Option\\GetTypes',
        },
        allowBlank: false,
        editable: true
    });
    ms3.combo.OptionTypes.superclass.constructor.call(this, config);
};
Ext.extend(ms3.combo.OptionTypes, ms3.combo.ComboBoxDefault);
Ext.reg('ms3-combo-option-types', ms3.combo.OptionTypes);


ms3.combo.Classes = function (config) {
    config = config || {};
    Ext.applyIf(config, {
        id: 'ms3-combo-classes',
        fieldLabel: _('ms3_class'),
        name: 'class',
        hiddenName: 'class',
        fields: ['name', 'class'],
        displayField: 'name',
        valueField: 'class',
        pageSize: 20,
        url: ms3.config.connector_url,
        baseParams: {
            action: 'MiniShop3\\Processors\\Settings\\GetClass',
            type: config.type || '',
        },
        allowBlank: true,
        editable: true,
    });
    ms3.combo.Classes.superclass.constructor.call(this, config);
};
Ext.extend(ms3.combo.Classes, ms3.combo.ComboBoxDefault);
Ext.reg('ms3-combo-classes', ms3.combo.Classes);


ms3.combo.ModCategory = function (config) {
    config = config || {};

    Ext.applyIf(config, {
        name: config.name || 'modcategory',
        hiddenName: config.name || 'modcategory',
        displayField: 'category',
        valueField: 'id',
        anchor: '99%',
        fields: ['category', 'id'],
        pageSize: 20,
        url: ms3.config.connector_url,
        typeAhead: false,
        editable: false,
        allowBlank: true,
        emptyText: _('category'),
        baseParams: {
            action: 'MiniShop3\\Processors\\Settings\\Option\\GetCategories',
            combo: true,
            id: config.value,
        }
    });
    ms3.combo.ModCategory.superclass.constructor.call(this, config);
    this.on('expand', function () {
        if (!!this.pageTb && this.pageSize < this.getStore().totalLength) {
            this.pageTb.show();
        }
    });
};
Ext.extend(ms3.combo.ModCategory, ms3.combo.ComboBoxDefault);
Ext.reg('ms3-combo-modcategory', ms3.combo.ModCategory);
