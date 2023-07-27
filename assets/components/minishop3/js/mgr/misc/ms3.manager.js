Ext.override(MODx.window.QuickCreateResource, {
    listeners: {
        show: function () {
            let classKey = this.config.record.class_key,
                isProduct = classKey === 'MiniShop3\\Model\\msProduct',
                class_key = isProduct ? 'ms3_product' : 'ms3_category',
                windowTitle = _(class_key + '_create_here');

            if (['msProduct', 'msCategory'].includes(classKey)) {
                switch (this.action) {
                    case 'resource/create':
                        let form = this.fp.getForm(),
                            templateCmb = form.findField('template'),
                            templateCmbStore = templateCmb.getStore(),
                            templateSettingKey = isProduct ? 'ms3_template_product_default' : 'ms3_template_category_default',
                            templateVal = MODx.config[templateSettingKey] || MODx.config.default_template,
                            contentSettingKey = 'ms3_category_content_default',
                            contentCmb = form.findField('content'),
                            contentVal = MODx.config[contentSettingKey];

                        MODx.Ajax.request({
                            url: MODx.config.connector_url,
                            params: {
                                action: 'MODX\\Revolution\\Processors\\Context\\Setting\\GetList',
                                context_key: this.config.record.context_key
                            },
                            listeners: {
                                fn: function () {
                                }, scope: this
                            },
                            callback: function (options, success, response) {
                                let r = JSON.parse(response.responseText),
                                    settings = r.results;

                                if (success && settings) {
                                    for (const prop in settings) {
                                        if (!settings.hasOwnProperty(prop)) {
                                            continue;
                                        }
                                        switch (settings[prop].key) {
                                            case templateSettingKey:
                                                templateVal = settings[prop].value;
                                                continue;
                                            case contentSettingKey:
                                                contentVal = settings[prop].value;
                                                continue;
                                        }
                                    }
                                }

                                if (templateCmb && templateCmbStore) {
                                    templateCmbStore.on('load', function () {
                                        if (templateCmb.getValue() != templateVal) {
                                            templateCmb.setValue(templateVal);
                                        }
                                    });
                                    templateCmbStore.load();
                                }

                                if (contentCmb && !isProduct && contentCmb.getValue() != contentVal) {
                                    contentCmb.setValue(contentVal);
                                }
                            }
                        });

                        this._setTitle(_('quick_create'), windowTitle);
                        break;
                    case 'resource/update':
                        this._setTitle(_('quick_update'), windowTitle);
                        break;
                }
            }
        }
    },
    _setTitle: function (prefix, title) {
        if (title !== 'undefined') {
            this.setTitle(prefix + ' ' + title.toLowerCase());
        }
    }
});

Ext.override(MODx.tree.Resource, {
    _showContextMenu: function (n, e) {
        this.cm.activeNode = n;
        this.cm.removeAll();
        if (n.attributes.menu && n.attributes.menu.items) {
            this.addContextMenuItem(n.attributes.menu.items);
        } else {
            let m = [];
            switch (n.attributes.type) {
                case 'MODX\\Revolution\\modResource':
                case 'MODX\\Revolution\\modDocument':
                    m = this._getModResourceMenu(n);
                    break;
                case 'MiniShop3\\Model\\msCategory':
                    m = this._getMSMenu(n);
                    break;
                case 'MODX\\Revolution\\modContext':
                    m = this._getModContextMenu(n);
                    break;
            }

            this.addContextMenuItem(m);
        }
        this.cm.showAt(e.xy);
        e.stopEvent();
    },

    _getMSMenu: function(n) {
        let a = n.attributes;
        let ui = n.getUI();
        console.log(ui)
        let m = [];
        m.push({
            text: '<b>'+a.text+'</b>'
            ,handler: function() {return false;}
            ,header: true
        });
        m.push('-');
        if (ui.hasClass('pview')) {
            m.push({
                text: _('resource_overview')
                ,handler: this.overviewResource
            });
        }
        if (ui.hasClass('pedit')) {
            m.push({
                text: _('resource_edit')
                ,handler: this.editResource
            });
        }
        if (ui.hasClass('pqupdate')) {
            m.push({
                text: _('quick_update_resource')
                ,classKey: a.classKey
                ,handler: this.quickUpdateResource
            });
        }
        if (ui.hasClass('pduplicate')) {
            m.push({
                text: _('resource_duplicate')
                ,handler: this.duplicateResource
            });
        }
        m.push({
            text: _('resource_refresh')
            ,handler: this.refreshResource
            ,scope: this
        });

        if (ui.hasClass('pnew')) {
            m.push('-');
            this._getCreateMenus(m,null,ui);
        }

        if (ui.hasClass('psave')) {
            m.push('-');
            if (ui.hasClass('ppublish') && ui.hasClass('unpublished')) {
                m.push({
                    text: _('resource_publish')
                    ,handler: this.publishDocument
                });
            } else if (ui.hasClass('punpublish')) {
                m.push({
                    text: _('resource_unpublish')
                    ,handler: this.unpublishDocument
                });
            }
            if (ui.hasClass('pundelete') && ui.hasClass('deleted')) {
                m.push({
                    text: _('resource_undelete')
                    ,handler: this.undeleteDocument
                });
            } else if (ui.hasClass('pdelete') && !ui.hasClass('deleted')) {
                m.push({
                    text: _('resource_delete')
                    ,handler: this.deleteDocument
                });
            }
        }

        if(!ui.hasClass('x-tree-node-leaf')) {
            m.push('-');
            m.push(this._getSortMenu());
        }

        if (ui.hasClass('pview') && a.preview_url != '') {
            m.push('-');
            m.push({
                text: _('resource_view')
                ,handler: this.preview
            });
        }
        return m;
    }

    ,_getCreateMenus: function(m,pk,ui) {
        var types = MODx.config.resource_classes;
        var o = this.fireEvent('loadCreateMenus',types);
        if (Ext.isObject(o)) {
            Ext.apply(types,o);
        }
        var coreTypes = ['MODX\\Revolution\\modDocument'];
        var ct = [];
        var qct = [];
        for (var k in types) {
            if (coreTypes.indexOf(k) != -1) {
                if (!ui.hasClass('pnew_'+k)) {
                    continue;
                }
            }
            ct.push({
                text: types[k]['text_create_here']
                ,classKey: k
                ,usePk: pk ? pk : false
                ,handler: this.createResourceHere
                ,scope: this
            });
            if (ui && ui.hasClass('pqcreate')) {
                qct.push({
                    text: types[k]['text_create']
                    ,classKey: k
                    ,handler: this.createResource
                    ,scope: this
                });
            }
        }
        m.push({
            text: _('create')
            ,handler: function() {return false;}
            ,menu: {items: ct}
        });
        if (ui && ui.hasClass('pqcreate')) {
            m.push({
                text: _('quick_create')
                ,handler: function() {return false;}
                ,menu: {items: qct}
            });
        }

        return m;
    }
});
