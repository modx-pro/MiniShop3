minishop.panel.Category = function (config) {
    config = config || {};
    minishop.panel.Category.superclass.constructor.call(this, config);
};
Ext.extend(minishop.panel.Category, MODx.panel.Resource, {

    getFields: function (config) {
        const fields = [];
        const originals = MODx.panel.Resource.prototype.getFields.call(this, config);

        for (const i in originals) {
            if (!originals.hasOwnProperty(i)) {
                continue;
            }
            const item = originals[i];
            if (item.id == 'modx-resource-header') {
                item.html = '<h2>' + _('ms_category_new') + '</h2>';
            } else if (item.id == 'modx-resource-tabs') {
                item.stateful = MODx.config['ms_category_remember_tabs'] == 1;
                item.stateId = 'minishop-category-' + config.mode + '-tabpanel';
                item.stateEvents = ['tabchange'];
                item.collapsible = false;
                item.getState = function () {
                    return {activeTab: this.items.indexOf(this.getActiveTab())};
                };
                let pageSettingsTab, accessPermissionsTab;
                for (const i2 in item.items) {
                    if (!item.items.hasOwnProperty(i2)) {
                        continue;
                    }
                    const tab = item.items[i2];
                    if (tab.id == 'modx-resource-settings') {
                        tab.title = _('ms_tab_category');
                        tab.items.push(this.getContent(config));
                    } else if (tab.id == 'modx-page-settings') {
                        tab.items = this.getCategorySettings(config);
                        pageSettingsTab = tab;
                        item.items.splice(i2, 1);
                    } else if (tab.id == 'modx-resource-access-permissions') {
                        accessPermissionsTab = tab;
                        item.items.splice(i2, 1);
                    }
                }
                // Move the "Settings" and "Resource Groups" to the end of tabs
                pageSettingsTab && item.items.push(pageSettingsTab);
                accessPermissionsTab && item.items.push(accessPermissionsTab);
            }
            if (item.id != 'modx-resource-content') {
                fields.push(item);
            }
        }

        return fields;
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
                if (MODx.config['ms_category_content_default'] && config['mode'] === 'create') {
                    item.value = MODx.config['ms_category_content_default'];
                }
                item.hidden = minishop.config.isHideContent;
            }
            fields.push(item);
        }

        return fields;
    },

    getCategorySettings: function (config) {
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
                    case undefined:
                        if (field.xtype == 'fieldset') {
                            this.findField(field, 'modx-resource-isfolder', function (f) {
                                f.disabled = true;
                                f.hidden = true;
                            });
                            field.items[0].items[0].items = [{
                                id: 'modx-resource-hide_children_in_tree',
                                xtype: 'xcheckbox',
                                name: 'hide_children_in_tree',
                                listeners: config.listeners,
                                enableKeyEvents: true,
                                msgTarget: 'under',
                                hideLabel: true,
                                boxLabel: _('ms_product_hide_children_in_tree'),
                                description: '<b>[[*hide_children_in_tree]]</b><br />' + _('ms_product_hide_children_in_tree_help'),
                            }].concat(field.items[0].items[0].items);
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

    findField: function (data, id, callback) {
        for (const i in data) {
            if (!data.hasOwnProperty(i)) {
                continue;
            }
            const item = data[i];
            if (typeof(item) == 'object') {
                if (item.id == id) {
                    return callback(item);
                } else {
                    this.findField(item, id, callback);
                }
            }
        }

        return false;
    },

});
Ext.reg('minishop-panel-category', minishop.panel.Category);
