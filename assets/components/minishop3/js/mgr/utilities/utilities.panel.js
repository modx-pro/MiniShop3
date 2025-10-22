ms3.panel.Utilities = function (config) {
    config = config || {};
    Ext.apply(config, {
        cls: 'container',
        items: [{
            html: '<h2>' + _('ms3_header') + ' :: ' + _('ms3_utilities') + '</h2>',
            cls: 'modx-page-header',
        }, {
            xtype: 'modx-tabs',
            id: 'ms3-utilities-tabs',
            stateful: true,
            stateId: 'ms3-utilities-tabs',
            stateEvents: ['tabchange'],
            cls: 'ms3-panel',
            getState: function () {
                return {
                    activeTab: this.items.indexOf(this.getActiveTab())
                };
            },
            items: [{
                title: _('ms3_utilities_gallery'),
                layout: 'anchor',
                items: [{
                    html: _('ms3_utilities_gallery_intro'),
                    bodyCssClass: 'panel-desc',
                }, {
                    xtype: 'ms3-utilities-gallery',
                    cls: 'main-wrapper',
                }]
            },
                {
                    title: _('ms3_utilities_import'),
                    layout: 'anchor',
                    items: [{
                        html: _('ms3_utilities_import_intro'),
                        bodyCssClass: 'panel-desc',
                    }, {
                        xtype: 'ms3-utilities-import',
                        cls: 'main-wrapper',
                    }]
                },
                {
                    title: 'Управление полями товара',
                    id: 'ms3-utilities-fields-management-tab',
                    layout: 'fit',
                    autoScroll: true,
                    items: [{
                        xtype: 'panel',
                        id: 'ms3-vue-fields-management-panel',
                        border: false,
                        autoHeight: true,
                        html: '<div id="ms3-vue-fields-management" class="vueApp" style="min-height: 600px;"></div>',
                        listeners: {
                            afterrender: function() {
                                // Монтируем Vue приложение после рендера панели
                                const event = new CustomEvent('ms3:mountVueFieldsManagement', {
                                    detail: {
                                        targetId: '#ms3-vue-fields-management'
                                    }
                                });
                                document.dispatchEvent(event);
                            }
                        }
                    }]
                },
                {
                    title: _('ms3_extrafields'),
                    layout: 'form',
                    autoHeight: true,
                    items: [{
                        html: _('ms3_extrafields_intro'),
                        bodyCssClass: 'panel-desc',
                    }, {
                        layout: 'border',
                        id: 'ms3-tree-panel-extrafield',
                        height: 500,
                        flex: 0,
                        border: false,
                        defaults: {
                            border: false,
                            bodyStyle: 'background-color:transparent;'
                        }
                        , items: [
                            {
                                region: 'west',
                                cls: 'main-wrapper',
                                collapseMode: 'mini',
                                split: true,
                                useSplitTips: true,
                                monitorResize: true,
                                width: 280,
                                minWidth: 280,
                                minSize: 280,
                                maxSize: 400,
                                layout: 'fit',
                                items: [{
                                    xtype: 'ms3-tree-extrafieldclass'
                                }]
                            }, {
                                region: 'center',
                                id: 'ms3-grid-extrafields',
                                xtype: 'ms3-grid-extrafields',
                                hidden: true,
                                layout: 'fit',
                                cls: 'main-wrapper'
                            }
                        ]
                    }]
                }
            ]
        }]

    });
    ms3.panel.Utilities.superclass.constructor.call(this, config);

    Ext.getCmp('ms3-grid-extrafields').store.on('load', this.fixExtraFieldsPanelHeight);

};
Ext.extend(ms3.panel.Utilities, MODx.Panel, {
    fixExtraFieldsPanelHeight: function () {
        var gridExtraFields = Ext.getCmp('ms3-grid-extrafields');
        var extraFieldsPanel = Ext.getCmp('ms3-tree-panel-extrafield');

        if (gridExtraFields.rendered && extraFieldsPanel.rendered) {
            var gridHeight = gridExtraFields.getHeight();
            var maxHeight = (gridHeight > 500) ? gridHeight : 500;
            extraFieldsPanel.setHeight(maxHeight);
            Ext.getCmp('modx-content').doLayout();
        }
    }
});
Ext.reg('ms3-panel-utilities', ms3.panel.Utilities);
