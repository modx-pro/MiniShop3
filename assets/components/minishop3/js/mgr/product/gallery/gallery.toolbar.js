ms3.panel.Toolbar = function (config) {
    config = config || {};

    Ext.apply(config, {
        id: 'ms3-gallery-page-toolbar',
        items: [{
            id: 'ms3-resource-upload-btn',
            text: '<i class="icon icon-upload"></i> ' + _('ms3_gallery_button_upload'),
        }, {
            text: '<i class="icon icon-cogs"></i> ',
            cls: 'ms3-btn-actions',
            menu: [{
                text: '<i class="icon icon-refresh"></i> ' + _('ms3_gallery_file_generate_all'),
                cls: 'ms3-btn-action',
                handler: function () {
                    this.fileAction('generateAllThumbs')
                },
                scope: this,
            }, '-', {
                text: '<i class="icon icon-trash-o action-red"></i> ' + _('ms3_gallery_file_delete_all'),
                cls: 'ms3-btn-action',
                handler: function () {
                    this.fileAction('deleteAllFiles')
                },
                scope: this,
            },]
        },'->', {
            xtype: 'displayfield',
            html: '<b>' + _('ms3_product_source') + '</b>:&nbsp;&nbsp;'
        }, '-', {
            xtype: 'ms3-combo-source',
            id: 'ms3-resource-source',
            description: '<b>[[+source_id]]</b><br />' + _('ms3_product_source_help'),
            value: config.record.source,
            name: 'source_id',
            hiddenName: 'source_id',
            listeners: {
                select: {
                    fn: this.sourceWarning,
                    scope: this
                }
            }
        }]
    });
    ms3.panel.Toolbar.superclass.constructor.call(this, config);
    this.config = config;
};
Ext.extend(ms3.panel.Toolbar, Ext.Toolbar, {

    sourceWarning: function (combo) {
        const source_id = this.config.record.source;
        const sel_id = combo.getValue();
        if (source_id !== sel_id) {
            Ext.Msg.confirm(_('warning'), _('ms3_product_change_source_confirm'), function (e) {
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
        const view = Ext.getCmp('ms3-gallery-images-view');
        if (view && typeof view[method] === 'function') {
            return view[method].call(view, arguments);
        }
    },

});
Ext.reg('ms3-gallery-page-toolbar', ms3.panel.Toolbar);
