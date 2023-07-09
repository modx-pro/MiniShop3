minishop.panel.Toolbar = function (config) {
    config = config || {};

    Ext.apply(config, {
        id: 'minishop-gallery-page-toolbar',
        items: [{
            id: 'minishop-resource-upload-btn',
            text: '<i class="icon icon-upload"></i> ' + _('ms_gallery_button_upload'),
        }, {
            text: '<i class="icon icon-cogs"></i> ',
            cls: 'minishop-btn-actions',
            menu: [{
                text: '<i class="icon icon-refresh"></i> ' + _('ms_gallery_file_generate_all'),
                cls: 'minishop-btn-action',
                handler: function () {
                    this.fileAction('generateAllThumbs')
                },
                scope: this,
            }, '-', {
                text: '<i class="icon icon-trash-o action-red"></i> ' + _('ms_gallery_file_delete_all'),
                cls: 'minishop-btn-action',
                handler: function () {
                    this.fileAction('deleteAllFiles')
                },
                scope: this,
            },]
        },'->', {
            xtype: 'displayfield',
            html: '<b>' + _('ms_product_source') + '</b>:&nbsp;&nbsp;'
        }, '-', {
            xtype: 'minishop-combo-source',
            id: 'minishop-resource-source',
            description: '<b>[[+source]]</b><br />' + _('ms_product_source_help'),
            value: config.record.source,
            name: 'source',
            hiddenName: 'source',
            listeners: {
                select: {
                    fn: this.sourceWarning,
                    scope: this
                }
            }
        }]
    });
    minishop.panel.Toolbar.superclass.constructor.call(this, config);
    this.config = config;
};
Ext.extend(minishop.panel.Toolbar, Ext.Toolbar, {

    sourceWarning: function (combo) {
        const source_id = this.config.record.source;
        const sel_id = combo.getValue();
        if (source_id !== sel_id) {
            Ext.Msg.confirm(_('warning'), _('ms_product_change_source_confirm'), function (e) {
                if (e === 'yes') {
                    combo.setValue(sel_id);
                    MODx.activePage.submitForm({
                        success: {
                            fn: function (r) {
                                var page = 'resource/update';
                                MODx.loadPage(page, 'id=' + r.result.object.id);
                            }, scope: this
                        }
                    });
                } else {
                    combo.setValue(source_id);
                }
            }, this);
        }
    },

    fileAction: function (method) {
        const view = Ext.getCmp('minishop-gallery-images-view');
        if (view && typeof view[method] === 'function') {
            return view[method].call(view, arguments);
        }
    },

});
Ext.reg('minishop-gallery-page-toolbar', minishop.panel.Toolbar);
