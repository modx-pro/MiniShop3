ms3.panel.Category = function (config) {
    config = config || {};
    ms3.panel.Category.superclass.constructor.call(this, config);
};
Ext.extend(ms3.panel.Category, MODx.panel.Resource, {

    getFields: function (config) {
        const fields = [];
        const originals = MODx.panel.Resource.prototype.getFields.call(this, config);

        for (const i in originals) {
            if (!originals.hasOwnProperty(i)) {
                continue;
            }
            const item = originals[i];
            if (item.id === "modx-header-breadcrumbs") {
                item.items[0].html = _('ms3_category_new');
            }
            if (item.id === 'modx-resource-tabs') {
                // item.stateful = MODx.config['ms3_category_remember_tabs'] === 1;
                // item.stateId = 'ms3-category-' + config.mode + '-tabpanel';
                // item.stateEvents = ['tabchange'];
                // item.collapsible = false;
                // item.getState = function () {
                //     return {activeTab: this.items.indexOf(this.getActiveTab())};
                // };
                let pageSettingsTab, accessPermissionsTab;
                for (const i2 in item.items) {
                    if (!item.items.hasOwnProperty(i2)) {
                        continue;
                    }
                    const tab = item.items[i2];
                    if (tab.id === 'modx-resource-settings') {
                        tab.title = _('ms3_tab_category');
                        tab.layout = "form";
                        //tab.items.push(this.getContent(config));
                    }
                    if (tab.id === 'modx-page-settings') {
                        tab.items = this.getCategorySettings(config);
                        pageSettingsTab = tab;
                        item.items.splice(i2, 1);
                    }
                    if (tab.id === 'modx-resource-access-permissions') {
                        accessPermissionsTab = tab;
                        item.items.splice(i2, 1);
                    }
                }
                // Move the "Settings" and "Resource Groups" to the end of tabs
                pageSettingsTab && item.items.push(pageSettingsTab);
                accessPermissionsTab && item.items.push(accessPermissionsTab);
            }
            if (item.id !== 'modx-resource-content') {
                fields.push(item);
            }
        }

        return fields;
    },

    getContent: function (config) {
        let fields = [];
        const originals = MODx.panel.Resource.prototype.getContentField.call(this, config);

        for (const i in originals) {
            if (!originals.hasOwnProperty(i)) {
                continue;
            }
            const item = originals[i];
            if (!Array.isArray(item)) {
                continue;
            }

            for (const j in item) {
                if (!item.hasOwnProperty(j)) {
                    continue;
                }
                const item_j = item[j];
                if (item_j.id === 'ta') {
                    item_j.hideLabel = false;
                    item_j.border = false;
                    item_j.fieldLabel = _('content');
                    item_j.itemCls = 'contentblocks_replacement';
                    item_j.msgTarget = "under";
                    item_j.description = '<b>[[*content]]</b>';
                    if (MODx.config['ms3_category_content_default'] && config['mode'] === 'create') {
                        item_j.value = MODx.config['ms3_category_content_default'];
                    }
                    item_j.value = "<p></p>"
                    //item_j.hidden = ms3.config.isHideContent;
                    item_j.hidden = false;
                }
                fields.push(item_j);
            }

        }
        return fields;
    },

    getCategorySettings: function (config) {
        const originals = MODx.panel.Resource.prototype.getSettingFields.call(this, config);

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

                if (field.id === "modx-page-settings-left") {
                    //content_type
                    field.items[1].xtype = 'hidden';
                    field.items[1].value = MODx.config['default_content_type'] || 1;
                }

                if (field.id === "modx-page-settings-box-left") {
                    //is_folder
                    field.items[0].disabled = true;
                    field.items[0].hidden = true;
                }

                //content-disposition
                if (field.id === "modx-page-settings-right") {
                    field.items[1].xtype = 'hidden';
                    field.items[1] = config.record['content_dispo'] || 0;
                }
                fields.push(field);
            }
            column.items = fields;
            items.push(column);
        }
        originals[0]['items'] = items;

        return originals[0];
    },

});
Ext.reg('ms3-panel-category', ms3.panel.Category);
