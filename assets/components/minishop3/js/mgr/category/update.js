ms3.page.UpdateCategory = function (config) {
    config = config || {record: {}};
    config.record = config.record || {};
    Ext.applyIf(config, {
        panelXType: 'ms3-panel-category-update',
        mode: 'update',
        actions: {
            new: 'resource/create',
            edit: 'resource/update',
            preview: 'resource/preview',
        }
    });
    ms3.page.UpdateCategory.superclass.constructor.call(this, config);
};
Ext.extend(ms3.page.UpdateCategory, MODx.page.UpdateResource, {
    getButtons: function (config) {
        const buttons = [];
        const originals = MODx.page.UpdateResource.prototype.getButtons.call(this, config);
        for (const i in originals) {
            if (!originals.hasOwnProperty(i)) {
                continue;
            }
            const button = originals[i];
            switch (button.id) {
                case 'modx-abtn-save':
                    button.text = '<i class="icon icon-save"></i> ' + button.text;
                    break;
                case 'modx-abtn-delete':
                    break;
                case 'modx-abtn-preview':
                    button.text = '<i class="icon icon-eye"></i>';
                    break;
                case 'modx-abtn-duplicate':
                    button.text = '<i class="icon icon-files-o"></i> ' + button.text;
                    break;
                case 'modx-abtn-cancel':
                    continue;
                case 'modx-abtn-help':
                    buttons.push(this.getAdditionalButtons(config));
                    button.text = '<i class="icon icon-question-circle"></i>';
                    break;
            }
            buttons.push(button)
        }

        return buttons;
    },

    getAdditionalButtons: function (config) {
        return [{
            text: '<i class="icon icon-arrow-left"></i>',
            handler: this.prevPage,
            disabled: !config['prev_page'],
            scope: this,
            tooltip: _('ms3_btn_prev'),
            keys: [{key: 37, alt: true, scope: this, fn: this.prevPage}]
        }, {
            text: '<i class="icon icon-arrow-up"></i>',
            handler: this.cancel,
            scope: this,
            tooltip: _('ms3_btn_back'),
            keys: [{key: 38, alt: true, scope: this, fn: this.upPage}]
        }, {
            text: '<i class="icon icon-arrow-right"></i>',
            handler: this.nextPage,
            disabled: !config['next_page'],
            scope: this,
            tooltip: _('ms3_btn_next'),
            keys: [{key: 39, alt: true, scope: this, fn: this.nextPage}]
        }];
    },

    prevPage: function () {
        if (this.config['prev_page'] > 0) {
            MODx.loadPage('resource/update', 'id=' + this.config['prev_page'])
        }
    },

    nextPage: function () {
        if (this.config['next_page'] > 0) {
            MODx.loadPage('resource/update', 'id=' + this.config['next_page'])
        }
    },

    cancel: function () {
        const id = this.config['up_page'];
        const action = id != 0
            ? 'resource/update'
            : 'welcome';

        MODx.loadPage(action, 'id=' + id)
    },

});
Ext.reg('ms3-page-category-update', ms3.page.UpdateCategory);


ms3.panel.UpdateCategory = function (config) {
    config = config || {};
    ms3.panel.UpdateCategory.superclass.constructor.call(this, config);
};
Ext.extend(ms3.panel.UpdateCategory, ms3.panel.Category, {

    getFields: function (config) {
        const fields = [];
        const originals = ms3.panel.Category.prototype.getFields.call(this, config);
        for (const i in originals) {
            if (!originals.hasOwnProperty(i)) {
                continue;
            }
            const item = originals[i];
            if (item.id === 'modx-resource-tabs') {
                const tabs = [
                    this.getProducts(config)
                ];
                let pageSettingsTab, accessPermissionsTab;
                for (const i2 in item.items) {
                    if (!item.items.hasOwnProperty(i2)) {
                        continue;
                    }
                    const tab = item.items[i2];
                    if (tab.id !== 'modx-page-settings' && tab.id !== 'modx-resource-access-permissions') {
                        tabs.push(tab);
                    } else {
                        // Get the "Settings" and "Resource Groups" tabs
                        if (tab.id === 'modx-page-settings') {
                            // Add "Product Options" inside the "Settings" tab
                            tab.items = this.addOptions(config, tab.items);
                            pageSettingsTab = tab;
                        }
                        if (tab.id === 'modx-resource-access-permissions') {
                            accessPermissionsTab = tab;
                        }
                    }
                }
                // Move the "Settings" and "Resource Groups" to the end of tabs
                pageSettingsTab && tabs.push(pageSettingsTab);
                accessPermissionsTab && tabs.push(accessPermissionsTab);
                item.items = tabs;
            }
            fields.push(item);
        }
        return fields;
    },

    getProducts: function (config) {
        return {
            title: _('ms3_tab_products'),
            id: 'modx-ms3-products',
            layout: 'anchor',
            items: [{
                xtype: 'ms3-grid-products',
                resource: config.resource,
                border: false,
                listeners: {

                },
            }]
        };
    },

    addOptions: function (config, items) {
        return [{
            layout: 'form',
            items: [items, {
                html: String.format('<h3>{0}</h3>', _('ms3_product_options')),
                style: 'margin-top: 20px',
                border: false,
            }, {
                xtype: 'ms3-grid-category-option',
                border: false,
                record: config['record'],
            }]
        }];
    },

    handlePreview: function (action) {
        const previewBtn = Ext.getCmp('modx-abtn-preview');
        const deleteButton = Ext.getCmp('modx-abtn-delete');
        if (previewBtn === undefined || deleteButton === undefined) {
            Ext.defer(function () {
                this.handlePreview(action);
            }, 200, this);
        } else {
            previewBtn[action]();
            deleteButton[action]();
        }
    },

});
Ext.reg('ms3-panel-category-update', ms3.panel.UpdateCategory);
