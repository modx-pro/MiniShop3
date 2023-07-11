minishop.panel.Product = function (config) {
    config = config || {};
    minishop.panel.Product.superclass.constructor.call(this, config);
};
Ext.extend(minishop.panel.Product, MODx.panel.Resource, {
    getFields: function (config) {
        const fields = [];
        const originals = MODx.panel.Resource.prototype.getFields.call(this, config);

        const thumbPanel = {
            anchor: '100%',
            cls: 'modx-resource-panel',
            collapsible: false,
            id: 'minishop-product-image-panel',
            items: [
                {
                    xtype: 'displayfield',
                    id: 'minishop-product-image-wrap',
                    html: String.format(
                      '<img src="{0}" id="minishop-product-image"/>',
                      config.record['thumb'] || minishop.config.default_thumb
                    ),
                    style: { 'textAlign': 'center' }
                }
            ],
            labelSeparator: '',
            layout: 'form'

        }

        for (const i in originals) {
            if (!originals.hasOwnProperty(i)) {
                continue;
            }
            const item = originals[i];

            if (item.id === "modx-header-breadcrumbs") {
                item.items[0].html = '<h2>' + _('ms_product_new') + '</h2>';
                fields.push(item)
            } else if (item.id === 'modx-resource-tabs') {
                item.stateful = parseInt(MODx.config.ms_product_remember_tabs) === 1;
                item.stateId = 'minishop2-product-' + config.mode + '-tabpanel';
                item.stateEvents = ['tabchange'];
                item.collapsible = false;
                item.getState = function () {
                    return { activeTab: this.items.indexOf(this.getActiveTab()) };
                };

                const tabs = [];

                item.items.forEach((tab, key) => {
                    switch (tab.id) {
                        case 'modx-resource-settings':
                            tab.items.forEach((tabItem, key_ti) => {
                                switch (tabItem.id) {
                                    case 'modx-resource-main-columns':
                                        tabItem.items.forEach((column, key_c) => {
                                            switch (column.id) {
                                                case 'modx-resource-main-left':
                                                    break;
                                                case 'modx-resource-main-right':
                                                    item.items[key].items[key_ti].items[key_c].items.unshift(thumbPanel)
                                                    break;
                                            }
                                        });
                                        break;
                                }
                            })
                            tabs.push(tab);
                            tabs.push(this.getProductFields(config));
                            tabs.push(this.getProductLinks(config));
                            tabs.push(this.getProductCategories(config));
                            break;
                        case 'modx-page-settings':
                        default:
                            tabs.push(tab);
                            break;
                    }

                });

                item.items = tabs;
                fields.push(item);
            }
        }
        return fields;

    },

    getProductFields: function (config) {
        const enabled = minishop.config.data_fields;
        const available = minishop.config.extra_fields;

        const product_fields = this.getAllProductFields(config);
        const col1 = [];
        const col2 = [];
        let tmp;
        for (let i = 0; i < available.length; i++) {
            const field = available[i];
            this.active_fields = [];
            if ((enabled.length > 0 && enabled.indexOf(field) === -1) || this.active_fields.indexOf(field) !== -1) {
                continue;
            }
            if (tmp = product_fields[field]) {
                this.active_fields.push(field);
                tmp = this.getExtField(config, field, tmp);
                if (i % 2) {
                    col2.push(tmp);
                } else {
                    col1.push(tmp);
                }
            }
        }

        return {
            title: _('ms_tab_product_data'),
            id: 'minishop-product-data',
            bodyCssClass: 'main-wrapper',
            items: [{
                layout: 'column',
                items: [{
                    columnWidth: .5,
                    layout: 'form',
                    id: 'minishop-product-data-left',
                    labelAlign: 'top',
                    items: col1,
                }, {
                    columnWidth: .5,
                    layout: 'form',
                    id: 'minishop-product-data-right',
                    labelAlign: 'top',
                    items: col2,
                }],
            }],
            listeners: {},
        };
    },

    getProductOptions: function (config) {
        const options = this.getOptionFields(config);
        if (!options.length) {
            return false;
        }

        const option_groups = [];
        for (let i = 0; i < options.length; i++) {
            let newGroup = true;
            for (let j = 0; j < option_groups.length; j++) {
                if (option_groups[j].category == options[i].category) {
                    option_groups[j].items.push(options[i]);
                    newGroup = false;
                    break;
                }
            }
            if (newGroup) {
                option_groups.push({
                    id: 'minishop-options-tab-' + options[i].category,
                    layout: 'form',
                    labelAlign: 'top',
                    category: options[i].category,
                    title: options[i].category_name
                        ? options[i].category_name
                        : _('ms_ft_nogroup'),
                    bodyCssClass: 'main-wrapper',
                    items: [options[i]],
                });
            }
        }

        return {
            title: _('ms_tab_product_options'),
            id: 'minishop-product-options',
            items: [{
                xtype: 'modx-vtabs',
                autoTabs: true,
                border: false,
                plain: true,
                deferredRender: false,
                id: 'minishop-options-vtabs',
                items: option_groups,
            }]
        };
    },

    getProductLinks: function (config) {
        return {
            title: _('ms_tab_product_links'),
            id: 'minishop-product-links',
            items: [{
                xtype: 'minishop-product-links',
                record: config.record,
            }]
        };
    },

    getProductCategories: function (config) {
        return {
            title: _('ms_tab_product_categories'),
            id: 'minishop-product-categories',
            items: [{
                xtype: 'minishop-tree-categories',
                parent: config.record['parent'] || 0,
                resource: config.record['id'] || 0,
            }]
        };
    },

    getContent: function (config) {
        const fields = [];
        const originals = MODx.panel.Resource.prototype.getContentField.call(this, config);
        for (const i in originals) {
            if (!originals.hasOwnProperty(i)) {
                continue;
            }
            const item = originals[i];

            if (item.id === 'ta') {
                item.hideLabel = false;
                item.fieldLabel = _('content');
                item.itemCls = 'contentblocks_replacement';
                item.description = '<b>[[*content]]</b>';
                item.hidden = minishop.config.isHideContent;
            }
            fields.push(item);
        }

        return fields;
    },

    getProductSettings: function (config) {
        const originals = MODx.panel.Resource.prototype.getSettingFields.call(this, config);

        const moved = {};
        const items = [];
        for (const i in originals[0]['items']) {
            if (!originals[0]['items'].hasOwnProperty(i)) {
                continue;
            }
            const column = originals[0]['items'][i];
            const fields = [];
            for (const i2 in column['items']) {
                if (!column['items'].hasOwnProperty(i2)) {
                    continue;
                }
                const field = column['items'][i2];
                switch (field.id) {
                    case 'modx-resource-content-type':
                        field.xtype = 'hidden';
                        field.value = MODx.config['default_content_type'] || 1;
                        break;
                    case 'modx-resource-content-dispo':
                        field.xtype = 'hidden';
                        field.value = config.record['content_dispo'] || 0;
                        break;
                    case 'modx-resource-menuindex':
                        moved.menuindex = field;
                        continue;
                    case 'modx-resource-parent':
                        field.xtype = 'minishop-combo-category';
                        field.listeners = {
                            select: {
                                fn: function (data) {
                                    Ext.getCmp('modx-resource-parent-hidden').setValue(data.value);
                                }
                            }
                        };
                        break;
                    case undefined:
                        if (field.xtype == 'fieldset') {
                            this.findField(field, 'modx-resource-isfolder', function (f) {
                                f.disabled = true;
                                f.hidden = true;
                            });
                            field.items[0].items[0].items = [
                                this.getExtField(config, 'show_in_tree', {xtype: 'xcheckbox'})
                            ].concat(field.items[0].items[0].items);
                            moved.checkboxes = field;
                            continue;
                        } else {
                            break;
                        }
                }
                fields.push(field);
            }
            column.items = fields;
            items.push(column);
        }
        if (moved.checkboxes != undefined) {
            items[0]['items'].push(moved.checkboxes);
        }
        if (moved.menuindex != undefined) {
            items[1]['items'].push(moved.menuindex);
        }
        originals[0]['items'] = items;

        return originals[0];
    },

    getExtField: function (config, name, field) {
        return minishop.utils.getExtField(config, name, field);
    },

    getAllProductFields: function (config) {
        const fields = {
            pagetitle: {
                xtype: 'textfield',
                fieldLabel: _('ms_product_pagetitle'),
                maxLength: 255,
                allowBlank: false,
                listeners: {
                    'keyup': {
                        scope: this, fn: function (f) {
                            const title = Ext.util.Format.stripTags(f.getValue());
                            Ext.getCmp('modx-resource-header').getEl().update('<h2>' + title + '</h2>');
                            MODx.fireResourceFormChange();
                        }
                    }
                }
            },
            longtitle: {xtype: 'textfield'},
            description: {xtype: 'textarea'},
            introtext: {xtype: 'textarea', description: '<b>[[*introtext]]</b><br />' + _('resource_summary_help')},
            content: {
                xtype: 'textarea',
                name: 'ta',
                id: 'ta',
                description: '',
                height: 400,
                grow: false,
                value: (config.record.content || config.record.ta) || ''
            },
            createdby: {
                xtype: 'minishop-combo-user',
                value: config.record.createdby,
                description: '<b>[[*createdby]]</b><br/>' + _('ms_product_createdby_help')
            },
            publishedby: {
                xtype: 'minishop-combo-user',
                value: config.record.publishedby,
                description: '<b>[[*publishedby]]</b><br/>' + _('ms_product_publishedby_help')
            },
            deletedby: {
                xtype: 'minishop-combo-user',
                value: config.record.deletedby,
                description: '<b>[[*deletedby]]</b><br/>' + _('ms_product_deletedby_help')
            },
            editedby: {
                xtype: 'minishop-combo-user',
                value: config.record.deletedby,
                description: '<b>[[*editedby]]</b><br/>' + _('ms_product_editedby_help')
            },
            publishedon: {
                xtype: 'minishop-xdatetime',
                value: config.record.publishedon,
                description: '<b>[[*publishedon]]</b><br/>' + _('ms_product_publishedon_help')
            },
            createdon: {
                xtype: 'minishop-xdatetime',
                value: config.record.createdon,
                description: '<b>[[*createdon]]</b><br/>' + _('ms_product_createdon_help')
            },
            deletedon: {
                xtype: 'minishop-xdatetime',
                value: config.record.deletedon,
                description: '<b>[[*deletedon]]</b><br/>' + _('ms_product_deletedon_help')
            },
            editedon: {
                xtype: 'minishop-xdatetime',
                value: config.record.editedon,
                description: '<b>[[*editedon]]</b><br/>' + _('ms_product_editedon_help')
            },
            pub_date: {
                xtype: MODx.config.publish_document ? 'minishop-xdatetime' : 'hidden',
                description: '<b>[[*pub_date]]</b><br />' + _('resource_publishdate_help'),
                id: 'modx-resource-pub-date',
                value: config.record.pub_date
            },
            unpub_date: {
                xtype: MODx.config.publish_document ? 'minishop-xdatetime' : 'hidden',
                description: '<b>[[*unpub_date]]</b><br />' + _('resource_unpublishdate_help'),
                id: 'modx-resource-unpub-date',
                value: config.record.unpub_date
            },

            template: {
                xtype: 'modx-combo-template',
                editable: false,
                baseParams: {
                    action: 'MiniShop3\\Processors\\Element\\Template\\GetList',
                    combo: '1'
                },
                listeners: {select: {fn: this.templateWarning, scope: this}}
            },
            parent: {
                xtype: 'minishop-combo-category',
                value: config.record.parent,
                listeners: {
                    select: {
                        fn: function (data) {
                            Ext.getCmp('modx-resource-parent-hidden').setValue(data.value);
                            MODx.fireResourceFormChange();
                        }
                    }
                }
            },
            alias: {xtype: 'textfield', value: config.record.alias || ''},
            menutitle: {xtype: 'textfield', value: config.record.menutitle || ''},
            menuindex: {xtype: 'numberfield', value: config.record.menuindex || 0, anchor: '50%'},
            link_attributes: {
                xtype: 'textfield',
                value: config.record.link_attributes || '',
                id: 'modx-resource-link-attributes'
            },
            searchable: {xtype: 'xcheckbox', inputValue: 1, checked: parseInt(config.record.searchable)},
            cacheable: {xtype: 'xcheckbox', inputValue: 1, checked: parseInt(config.record.cacheable)},
            richtext: {xtype: 'xcheckbox', inputValue: 1, checked: parseInt(config.record.richtext)},
            hidemenu: {
                xtype: 'xcheckbox',
                inputValue: 1,
                checked: parseInt(config.record.hidemenu),
                description: '<b>[[*hidemenu]]</b><br/>' + _('resource_hide_from_menus_help')
            },
            uri_override: {
                xtype: 'xcheckbox',
                inputValue: 1,
                checked: parseInt(config.record.uri_override),
                id: 'modx-resource-uri-override'
            },
            show_in_tree: {
                xtype: 'xcheckbox',
                inputValue: 1,
                description: '<b>[[*show_in_tree]]</b><br/>' + _('ms_product_show_in_tree_help'),
                checked: parseInt(config.record.show_in_tree)
            },
            article: {xtype: 'textfield', description: '<b>[[+article]]</b><br />' + _('ms_product_article_help')},
            price: {
                xtype: 'numberfield',
                decimalPrecision: 2,
                description: '<b>[[+price]]</b><br />' + _('ms_product_price_help')
            },
            old_price: {
                xtype: 'numberfield',
                decimalPrecision: 2,
                description: '<b>[[+old_price]]</b><br />' + _('ms_product_old_price_help')
            },
            weight: {
                xtype: 'numberfield',
                decimalPrecision: 3,
                description: '<b>[[+weight]]</b><br />' + _('ms_product_weight_help')
            },
            vendor_id: {
                xtype: 'minishop-combo-vendor',
                description: '<b>[[+vendor_id]]</b><br />' + _('ms_product_vendor_help')
            },
            made_in: {
                xtype: 'minishop-combo-autocomplete',
                description: '<b>[[+made_in]]</b><br />' + _('ms_product_made_in_help')
            },
            source_id: {
                xtype: config.mode == 'update' ? 'hidden' : 'minishop-combo-source',
                name: 'source-cmb',
                disabled: config.mode == 'update',
                value: config.record.source_id || 1,
                description: '<b>[[+source_id]]</b><br />' + _('ms_product_source_id_help'),
                listeners: {
                    select: {
                        fn: function (data) {
                            Ext.getCmp('modx-resource-source-hidden').setValue(data.value);
                            MODx.fireResourceFormChange();
                        }
                    }
                }
            },
            'new': {
                xtype: 'xcheckbox',
                inputValue: 1,
                checked: parseInt(config.record.new),
                description: '<b>[[+new]]</b><br />' + _('ms_product_new_help')
            },
            favorite: {
                xtype: 'xcheckbox',
                inputValue: 1,
                checked: parseInt(config.record.favorite),
                description: '<b>[[+favorite]]</b><br />' + _('ms_product_favorite_help')
            },
            popular: {
                xtype: 'xcheckbox',
                inputValue: 1,
                checked: parseInt(config.record.popular),
                description: '<b>[[+popular]]</b><br />' + _('ms_product_popular_help')
            },
            tags: {
                xtype: 'minishop-combo-options',
                description: '<b>[[+tags]]</b><br />' + _('ms_product_tags_help')
            },
            color: {
                xtype: 'minishop-combo-options',
                description: '<b>[[+color]]</b><br />' + _('ms_product_color_help')
            },
            size: {xtype: 'minishop-combo-options', description: '<b>[[+size]]</b><br />' + _('ms_product_size_help')}
        };

        for (const i in minishop.plugin) {
            if (!minishop.plugin.hasOwnProperty(i)) {
                continue;
            }
            if (typeof (minishop.plugin[i]['getFields']) == 'function') {
                const add = minishop.plugin[i].getFields(config);
                Ext.apply(fields, add);
            }
        }

        return fields;
    },

    getOptionFields: function (config) {
        const options = minishop.config.option_fields;
        const fields = [];
        for (let i = 0; i < options.length; i++) {
            const field = minishop.utils.getExtField(config, options[i].key, options[i], 'extra-field');
            if (field) {
                fields.push(field);
            }
        }

        return fields;
    }

});


