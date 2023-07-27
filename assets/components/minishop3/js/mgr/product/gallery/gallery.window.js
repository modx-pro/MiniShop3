ms3.window.Image = function (config) {
    config = config || {};

    Ext.applyIf(config, {
        title: config.record['name'],
        width: 700,
        baseParams: {
            action: 'MiniShop3\\Processors\\Gallery\\Update',
        },
        resizable: false,
        maximizable: false,
        minimizable: false,
    });
    ms3.window.Image.superclass.constructor.call(this, config);
};
Ext.extend(ms3.window.Image, ms3.window.Default, {

    getFields: function (config) {
        const src = config.record['type'] === 'image'
            ? config.record['url']
            : config.record['thumbnail'];
        const img = MODx.config['connectors_url'] + 'system/phpthumb.php?src='
            + src
            + '&w=333&h=198&f=jpg&q=90&zc=0&far=1&HTTP_MODAUTH='
            + MODx.siteId + '&wctx=mgr&source='
            + config.record['source'];
        const fields = {
            ms3_gallery_file_source: config.record['source_name'],
            ms3_gallery_file_size: config.record['size'],
            ms3_gallery_file_createdon: config.record['createdon'],
        };
        let details = '';
        for (const i in fields) {
            if (!fields.hasOwnProperty(i)) {
                continue;
            }
            if (fields[i]) {
                details += '<tr><th>' + _(i) + ':</th><td>' + fields[i] + '</td></tr>';
            }
        }

        return [
            {xtype: 'hidden', name: 'id', id: this.ident + '-id'},
            {
                layout: 'column',
                border: false,
                anchor: '100%',
                items: [{
                    columnWidth: .5,
                    layout: 'form',
                    defaults: {msgTarget: 'under'},
                    border: false,
                    items: [{
                        xtype: 'displayfield',
                        hideLabel: true,
                        html: '\
                            <a href="' + config.record['url'] + '" target="_blank" class="ms3-gallery-window-link">\
                                <img src="' + img + '" class="ms3-gallery-window-thumb"  />\
                            </a>\
                            <table class="ms3-gallery-window-details">' + details + '</table>'
                    }]
                }, {
                    columnWidth: .5,
                    layout: 'form',
                    defaults: {msgTarget: 'under'},
                    border: false,
                    items: [{
                        xtype: 'textfield',
                        fieldLabel: _('ms3_gallery_file_name'),
                        name: 'file',
                        id: this.ident + '-file',
                        anchor: '100%'
                    }, {
                        xtype: 'textfield',
                        fieldLabel: _('ms3_gallery_file_title'),
                        name: 'name',
                        id: this.ident + '-name',
                        anchor: '100%'
                    }, {
                        xtype: 'textarea',
                        fieldLabel: _('ms3_gallery_file_description'),
                        name: 'description',
                        id: this.ident + '-description',
                        anchor: '100%',
                        height: 100
                    }]
                }]
        }
        ];
    }

});
Ext.reg('ms3-gallery-image', ms3.window.Image);
