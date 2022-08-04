minishop.page.UpdateProduct = function (config) {
    config = config || {record: {}};
    config.record = config.record || {};

    Ext.applyIf(config, {
        panelXType: 'minishop-panel-product-update',
        mode: 'update'
    });
    minishop.page.UpdateProduct.superclass.constructor.call(this, config);
};
Ext.extend(minishop.page.UpdateProduct, MODx.page.UpdateResource, {

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
                    button.text = '<i class="icon icon-times"></i> ' + button.text;
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
            tooltip: _('ms_btn_prev'),
            keys: [{key: 37, alt: true, scope: this, fn: this.prevPage}]
        }, {
            text: '<i class="icon icon-arrow-up"></i>',
            handler: this.cancel,
            scope: this,
            tooltip: _('ms_btn_back'),
            keys: [{key: 38, alt: true, scope: this, fn: this.upPage}]
        }, {
            text: '<i class="icon icon-arrow-right"></i>',
            handler: this.nextPage,
            disabled: !config['next_page'],
            scope: this,
            tooltip: _('ms_btn_next'),
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
Ext.reg('minishop-page-product-update', minishop.page.UpdateProduct);


minishop.panel.UpdateProduct = function (config) {
    config = config || {};
    minishop.panel.UpdateProduct.superclass.constructor.call(this, config);
};
Ext.extend(minishop.panel.UpdateProduct, minishop.panel.Product, {

    getFields: function (config) {
        const fields = [];
        const originals = minishop.panel.Product.prototype.getFields.call(this, config);

        for (const i in originals) {
            if (!originals.hasOwnProperty(i)) {
                continue;
            }
            const item = originals[i];
            if (item.id == 'modx-resource-tabs') {
                let accessPermissionsTab;

                // Additional "Gallery" and "Comments" tabs
                if (minishop.config['show_gallery'] != 0) {
                    item.items.push(this.getGallery(config));
                }
                if (minishop.config['show_comments'] != 0) {
                    item.items.push(this.getComments(config));
                }
                // Get the "Resource Groups" tab and move it to the end
                if (minishop.config['show_gallery'] != 0 || minishop.config['show_comments'] != 0) {
                    const index = item.items.findIndex(function (tab) {
                        return tab.id == 'modx-resource-access-permissions';
                    });
                    if (index != -1) {
                        accessPermissionsTab = item.items.splice(index, 1);
                        accessPermissionsTab && item.items.push(accessPermissionsTab);
                    }
                }
            }
            fields.push(item);
        }

        return fields;
    },

    getComments: function (config) {
        return {
            title: _('ms_tab_comments'),
            layout: 'anchor',
            items: [{
                xtype: 'tickets-panel-comments',
                record: config.record,
                parents: config.record.id,
                border: false,
            }]
        };
    },

    getGallery: function (config) {
        return {
            title: _('ms_tab_product_gallery'),
            layout: 'anchor',
            items: [{
                xtype: 'minishop-gallery-page',
                record: config.record,
                pageSize: 50,
                border: false,
            }]
        };
    },

});
Ext.reg('minishop-panel-product-update', minishop.panel.UpdateProduct);
